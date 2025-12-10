<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupTemplate;
use App\Models\ConfigBackup;
use App\Models\Device;
use App\Models\DeviceWifiCredential;
use App\Models\SpeedTestResult;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use App\Services\Tr181MigrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeviceController extends Controller
{
    protected ConnectionRequestService $connectionRequestService;

    public function __construct(ConnectionRequestService $connectionRequestService)
    {
        $this->connectionRequestService = $connectionRequestService;
    }

    /**
     * Check if a device only processes one TR-069 RPC per CWMP session.
     * SmartRG/Sagemcom devices have this limitation.
     * When multiple tasks are queued, only the first executes in each session.
     */
    private function isOneTaskPerSessionDevice(Device $device): bool
    {
        return $device->isOneTaskPerSession();
    }

    /**
     * Display a listing of all devices
     */
    public function index(): JsonResponse
    {
        $devices = Device::orderBy('last_inform', 'desc')->get();

        return response()->json($devices);
    }

    /**
     * Display the specified device
     */
    public function show(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Auto-backup is now handled in CwmpController on first TR-069 Inform
        // to ensure configuration is backed up before any changes can be made

        return response()->json($device);
    }

    /**
     * Get all parameters for a device
     */
    public function parameters(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $query = $device->parameters()->orderBy('name');

        // Add search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('value', 'LIKE', "%{$search}%");
            });
        }

        $parameters = $query->get();

        // Add human-readable timestamps
        $parameters->transform(function ($param) {
            $param->last_updated_human = $param->last_updated
                ? $param->last_updated->diffForHumans()
                : null;
            return $param;
        });

        return response()->json([
            'data' => $parameters,
            'total' => $parameters->count(),
        ]);
    }

    /**
     * Export device parameters to CSV
     */
    public function exportParameters(Request $request, string $id)
    {
        $device = Device::findOrFail($id);

        $query = $device->parameters()->orderBy('name');

        // Apply search filter if provided
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('value', 'LIKE', "%{$search}%");
            });
        }

        $parameters = $query->get();

        // Generate CSV
        $filename = 'device_' . $device->serial_number . '_parameters_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($parameters) {
            $file = fopen('php://output', 'w');

            // Add CSV header
            fputcsv($file, ['Parameter Name', 'Value', 'Type', 'Last Updated']);

            // Add data rows
            foreach ($parameters as $param) {
                fputcsv($file, [
                    $param->name,
                    $param->value,
                    $param->type ?? '',
                    $param->last_updated ? $param->last_updated->toDateTimeString() : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get all tasks for a device
     */
    public function tasks(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Get active task (sent but not completed)
        $activeTask = $device->tasks()
            ->where('status', 'sent')
            ->orderBy('created_at', 'asc')
            ->first();

        // Get queued tasks (pending, not yet sent)
        $queuedTasks = $device->tasks()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        // Get recent completed tasks (last 3)
        $recentCompleted = $device->tasks()
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->orderBy('completed_at', 'desc')
            ->limit(3)
            ->get();

        // Transform tasks to include computed fields
        $transformTask = function ($task) {
            if (!$task) return null;

            return [
                'id' => $task->id,
                'task_type' => $task->task_type,
                'description' => $task->getFriendlyDescription(),
                'status' => $task->status,
                'progress' => $task->getProgressDetails(),
                'elapsed' => $task->getElapsedSeconds(),
                'estimated_remaining' => $task->getEstimatedTimeRemaining(),
                'can_cancel' => $task->isCancellable(),
                'created_at' => $task->created_at,
                'completed_at' => $task->completed_at,
                'error' => $task->error,
            ];
        };

        return response()->json([
            'active' => $transformTask($activeTask),
            'queued' => $queuedTasks->map($transformTask)->values(),
            'queued_total' => $queuedTasks->count(),
            'recent_completed' => $recentCompleted->map($transformTask)->values(),
        ]);
    }

    /**
     * Create a new task for a device
     */
    public function createTask(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|in:get_params,set_params,reboot,factory_reset',
            'parameters' => 'nullable|array',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => $validated['type'],
            'parameters' => $validated['parameters'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($task, 201);
    }

    /**
     * Cancel a pending task
     */
    public function cancelTask(string $deviceId, string $taskId): JsonResponse
    {
        $device = Device::findOrFail($deviceId);
        $task = Task::where('device_id', $device->id)
            ->where('id', $taskId)
            ->firstOrFail();

        if (!$task->isCancellable()) {
            return response()->json([
                'error' => 'Task cannot be cancelled (status: ' . $task->status . ')'
            ], 400);
        }

        $task->update([
            'status' => 'cancelled',
            'error' => 'Cancelled by user',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Task cancelled successfully',
            'task' => $task,
        ]);
    }

    /**
     * Query device info (refresh basic device parameters)
     */
    public function query(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Determine data model prefix
        $dataModel = $device->getDataModel();
        $prefix = $dataModel === 'TR-181' ? 'Device.' : 'InternetGatewayDevice.';

        // Query essential device info parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'parameters' => [
                'names' => [
                    $prefix . 'DeviceInfo.Manufacturer',
                    $prefix . 'DeviceInfo.ManufacturerOUI',
                    $prefix . 'DeviceInfo.ProductClass',
                    $prefix . 'DeviceInfo.SerialNumber',
                    $prefix . 'DeviceInfo.HardwareVersion',
                    $prefix . 'DeviceInfo.SoftwareVersion',
                    $prefix . 'ManagementServer.ConnectionRequestURL',
                ],
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Query device info task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Refresh troubleshooting info (WAN, LAN, WiFi, Connected Devices)
     * Uses two-stage discovery to handle manufacturer-specific instance numbering
     */
    public function refreshTroubleshooting(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Determine data model
        $dataModel = $device->getDataModel();

        // Stage 1: Create discovery task to find instance numbers
        // Pass device for manufacturer-specific handling (some devices don't support partial paths)
        $discoveryParams = $this->buildDiscoveryParameters($dataModel, $device);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'discover_troubleshooting',
            'parameters' => [
                'names' => $discoveryParams,
                'data_model' => $dataModel,
            ],
            'status' => 'pending',
        ]);

        // Update last refresh timestamp
        $device->update(['last_refresh_at' => now()]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Troubleshooting discovery task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Build discovery parameters to find WAN, WiFi, and Host instances
     * Enhanced to query full device trees for better coverage and vendor extensions
     *
     * Note: Some devices (Calix TR-098, Nokia Beacon G6 TR-098) do NOT support
     * GetParameterValues with partial paths (ending with .). For these devices,
     * we must use explicit full parameter names instead.
     */
    private function buildDiscoveryParameters(string $dataModel, ?Device $device = null): array
    {
        $isDevice2 = $dataModel === 'TR-181';

        // Check if this device supports partial path queries
        // Calix TR-098 and Nokia Beacon G6 TR-098 do NOT support partial paths
        $isCalix = $device && (
            strtolower($device->manufacturer ?? '') === 'calix' ||
            strtoupper($device->oui ?? '') === 'CCBE59' ||
            strtoupper($device->oui ?? '') === 'D0768F'
        );

        // Nokia Beacon G6 in TR-098 mode (OUI 80AB4D) doesn't support partial paths
        $isNokiaTR098 = $device && !$isDevice2 && (
            strtoupper($device->oui ?? '') === '80AB4D'
        );

        // These devices require explicit parameter names - no partial paths
        $requiresExplicitParams = ($isCalix && !$isDevice2) || $isNokiaTR098;

        if ($isDevice2) {
            return [
                // Query entire DeviceInfo tree (gets ALL device info + vendor extensions automatically)
                'Device.DeviceInfo.',

                // Query entire ManagementServer tree (STUN, periodic inform, connection settings)
                'Device.ManagementServer.',

                // Always include static LAN parameters
                'Device.IP.Interface.2.IPv4Address.1.IPAddress',
                'Device.IP.Interface.2.IPv4Address.1.SubnetMask',
                'Device.DHCPv4.Server.Pool.1.Enable',
                'Device.DHCPv4.Server.Pool.1.MinAddress',
                'Device.DHCPv4.Server.Pool.1.MaxAddress',

                // Discovery counters
                'Device.WiFi.RadioNumberOfEntries',
                'Device.WiFi.SSIDNumberOfEntries',
                'Device.Hosts.HostNumberOfEntries',
            ];
        } elseif ($requiresExplicitParams) {
            // Calix TR-098 and Nokia Beacon G6 TR-098 - use explicit parameter names only
            // These devices return SOAP Fault 9000 "Method not supported" for partial paths
            // Also, Calix rejects entire request if ANY parameter doesn't exist
            return [
                // Device Info (explicit params only - no MemoryStatus as it doesn't exist on all models)
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'InternetGatewayDevice.DeviceInfo.ManufacturerOUI',
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'InternetGatewayDevice.DeviceInfo.SerialNumber',
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                'InternetGatewayDevice.DeviceInfo.UpTime',

                // Management Server (explicit params instead of ManagementServer. tree)
                'InternetGatewayDevice.ManagementServer.URL',
                'InternetGatewayDevice.ManagementServer.PeriodicInformEnable',
                'InternetGatewayDevice.ManagementServer.PeriodicInformInterval',
                'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
                'InternetGatewayDevice.ManagementServer.STUNEnable',
                'InternetGatewayDevice.ManagementServer.NATDetected',
                'InternetGatewayDevice.ManagementServer.UDPConnectionRequestAddress',

                // LAN parameters
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceSubnetMask',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPServerEnable',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MinAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MaxAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DNSServers',

                // Discovery counters
                'InternetGatewayDevice.WANDeviceNumberOfEntries',
                'InternetGatewayDevice.LANDevice.1.LANWLANConfigurationNumberOfEntries',
                'InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries',
            ];
        } else {
            // SmartRG and other devices that support partial path queries
            return [
                // Query entire DeviceInfo tree (gets ALL device info + vendor extensions automatically)
                'InternetGatewayDevice.DeviceInfo.',

                // Query entire ManagementServer tree (STUN, periodic inform, connection settings)
                'InternetGatewayDevice.ManagementServer.',

                // Always include static LAN parameters
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceSubnetMask',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPServerEnable',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MinAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MaxAddress',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DNSServers',

                // Discovery counters
                'InternetGatewayDevice.WANDeviceNumberOfEntries',
                'InternetGatewayDevice.LANDevice.1.LANWLANConfigurationNumberOfEntries',
                'InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries',
            ];
        }
    }

    /**
     * Find the active WAN connection for a device
     * Looks for WANIPConnection with ConnectionStatus=Connected and valid ExternalIPAddress
     */
    private function findActiveWanConnection(Device $device): ?array
    {
        // Search for WANIPConnection with Connected status and valid external IP
        $connectedParams = $device->parameters()
            ->where('name', 'LIKE', '%WANIPConnection%ConnectionStatus')
            ->where('value', 'Connected')
            ->get();

        foreach ($connectedParams as $statusParam) {
            // Extract the path: InternetGatewayDevice.WANDevice.X.WANConnectionDevice.Y.WANIPConnection.Z
            if (preg_match('/WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)/', $statusParam->name, $matches)) {
                $wanDevice = (int) $matches[1];
                $wanConnDevice = (int) $matches[2];
                $wanConn = (int) $matches[3];

                // Check if this connection has a valid external IP (not 0.0.0.0)
                $ipParam = $device->parameters()
                    ->where('name', "InternetGatewayDevice.WANDevice.{$wanDevice}.WANConnectionDevice.{$wanConnDevice}.WANIPConnection.{$wanConn}.ExternalIPAddress")
                    ->first();

                if ($ipParam && $ipParam->value && $ipParam->value !== '0.0.0.0') {
                    // Also check if NAT is enabled on this connection (for port forwarding)
                    $natParam = $device->parameters()
                        ->where('name', "InternetGatewayDevice.WANDevice.{$wanDevice}.WANConnectionDevice.{$wanConnDevice}.WANIPConnection.{$wanConn}.NATEnabled")
                        ->first();

                    // Prefer connections with NAT enabled, but accept any connected one
                    if (!$natParam || $natParam->value === '1' || $natParam->value === 'true') {
                        return [
                            'wanDevice' => $wanDevice,
                            'wanConnDevice' => $wanConnDevice,
                            'wanConn' => $wanConn,
                            'externalIp' => $ipParam->value,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Build detailed troubleshooting parameters from discovery results
     */
    public function buildDetailedParametersFromDiscovery(array $discoveryResults, string $dataModel, ?Device $device = null): array
    {
        $isDevice2 = $dataModel === 'TR-181';
        $parameters = [];

        // Detect manufacturer for device-specific instance handling
        $isCalix = $device && (
            strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F'
        );

        // SmartRG devices work best with partial path queries (trailing dot)
        // This returns all parameters under a path in one request - much faster
        $isSmartRG = $device && (
            strtolower($device->manufacturer ?? '') === 'smartrg' ||
            strtoupper($device->oui ?? '') === 'E82C6D'
        );

        // Calix GigaCenters (TR-098) also support partial path queries
        // Tested: CXNK0020A87F (OUI EC4F82) successfully returned all params with InternetGatewayDevice.
        // GigaCenters use WANDevice.1 for fiber WAN connections
        $isCalixGigaCenter = $isCalix && !$isDevice2;  // Calix TR-098 devices

        if ($isSmartRG) {
            // Use partial paths like USS does - gets all params under each path
            // SmartRG uses WANDevice.3 based on USS traces
            return [
                'InternetGatewayDevice.DeviceInfo.',
                'InternetGatewayDevice.Time.',
                'InternetGatewayDevice.ManagementServer.',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.',
                'InternetGatewayDevice.LANDevice.1.Hosts.',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.',
                'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.',
                'InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.2.',
                'InternetGatewayDevice.WANDevice.3.WANDSLInterfaceConfig.',
            ];
        }

        if ($isCalixGigaCenter) {
            // Calix GigaCenters (844E, 844G, 854G, 804Mesh) support partial path queries
            // IMPORTANT: Calix rejects entire request if ANY parameter doesn't exist
            // Using single root path is safest - tested CXNK0020A87F returned 6,178 params
            return [
                'InternetGatewayDevice.',
            ];
        }

        if ($isDevice2) {
            // WAN Information (Device:2 uses standard instances)
            $parameters = array_merge($parameters, [
                'Device.IP.Interface.1.Status',
                'Device.IP.Interface.1.IPv4Address.1.IPAddress',
                'Device.IP.Interface.1.IPv4Address.1.SubnetMask',
                'Device.IP.Interface.1.IPv4Address.1.DNSServers',
                'Device.IP.Interface.1.MACAddress',
                'Device.IP.Interface.1.Uptime',
                'Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress',
                // WAN traffic statistics for analytics
                'Device.IP.Interface.1.Stats.BytesSent',
                'Device.IP.Interface.1.Stats.BytesReceived',
                'Device.IP.Interface.1.Stats.PacketsSent',
                'Device.IP.Interface.1.Stats.PacketsReceived',
            ]);

            // WiFi Radios - Use discovered count
            $radioCount = (int) ($discoveryResults['Device.WiFi.RadioNumberOfEntries']['value'] ?? 2);
            for ($i = 1; $i <= min($radioCount, 4); $i++) {
                $parameters[] = "Device.WiFi.Radio.{$i}.Enable";
                $parameters[] = "Device.WiFi.Radio.{$i}.Status";
                $parameters[] = "Device.WiFi.Radio.{$i}.Channel";
                $parameters[] = "Device.WiFi.Radio.{$i}.OperatingFrequencyBand";
                $parameters[] = "Device.WiFi.Radio.{$i}.OperatingStandards";
            }

            // WiFi SSIDs - Use discovered count
            $ssidCount = (int) ($discoveryResults['Device.WiFi.SSIDNumberOfEntries']['value'] ?? 4);
            for ($i = 1; $i <= min($ssidCount, 8); $i++) {
                $parameters[] = "Device.WiFi.SSID.{$i}.Enable";
                $parameters[] = "Device.WiFi.SSID.{$i}.SSID";
                $parameters[] = "Device.WiFi.SSID.{$i}.Status";
                // WiFi traffic statistics for analytics
                $parameters[] = "Device.WiFi.SSID.{$i}.Stats.BytesSent";
                $parameters[] = "Device.WiFi.SSID.{$i}.Stats.BytesReceived";
                $parameters[] = "Device.WiFi.SSID.{$i}.Stats.PacketsSent";
                $parameters[] = "Device.WiFi.SSID.{$i}.Stats.PacketsReceived";
            }

            // Hosts - Query ALL hosts (no limit) for comprehensive troubleshooting
            $hostCount = (int) ($discoveryResults['Device.Hosts.HostNumberOfEntries']['value'] ?? 0);
            for ($i = 1; $i <= $hostCount; $i++) {
                $parameters[] = "Device.Hosts.Host.{$i}.HostName";
                $parameters[] = "Device.Hosts.Host.{$i}.IPAddress";
                $parameters[] = "Device.Hosts.Host.{$i}.PhysAddress";
                $parameters[] = "Device.Hosts.Host.{$i}.Active";
                // Additional host info for AP topology mapping
                $parameters[] = "Device.Hosts.Host.{$i}.Layer2Interface";
                $parameters[] = "Device.Hosts.Host.{$i}.AssociatedDevice";
            }
        } else {
            // InternetGatewayDevice data model

            // WAN - Query manufacturer-specific instances
            // NumberOfEntries doesn't tell us which instance numbers exist, just the count
            $wanDeviceCount = (int) ($discoveryResults['InternetGatewayDevice.WANDeviceNumberOfEntries']['value'] ?? 0);

            // DISABLED AGAIN: Second 844E doesn't support WANDevice.1 or WANDevice.3
            // Different 844E models have completely different WAN structures
            // Need device-specific WAN parameter discovery
            if (false && $wanDeviceCount > 0) {
                // Use standard WANDevice.1 for all devices (more universal than WANDevice.3)
                // Some Calix models use WANDevice.3, but WANDevice.1 works on most
                if ($isCalix) {
                    // Try standard instance first (works on most 844E models)
                    $wanIdx = 1;
                    $connIdx = 1;
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ExternalIPAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.SubnetMask";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DefaultGateway";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DNSServers";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.MACAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ConnectionStatus";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.Uptime";
                    // Note: WAN Stats excluded - don't exist on all device models
                } else {
                    // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
                    $wanIdx = 1;
                    $connIdx = 1;
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ExternalIPAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.SubnetMask";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DefaultGateway";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DNSServers";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.MACAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ConnectionStatus";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.Uptime";
                    // Note: WAN Stats excluded - don't exist on all device models
                }
            }

            // WiFi - Use discovered count, query enabled instances
            $wlanCount = (int) ($discoveryResults['InternetGatewayDevice.LANDevice.1.LANWLANConfigurationNumberOfEntries']['value'] ?? 0);

            // Query first 8 WLAN configurations (covers most common use cases)
            // Balance between getting enough data and avoiding device timeouts
            $wlanInstances = [];
            if ($wlanCount > 0) {
                // Query first 8 instances (2.4GHz SSIDs typically)
                for ($i = 1; $i <= min(8, $wlanCount); $i++) {
                    $wlanInstances[] = $i;
                }
            }

            foreach ($wlanInstances as $i) {
                // Standard TR-069 WiFi parameters (avoiding vendor-specific extensions)

                // Basic info
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Enable";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.SSID";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Status";

                // Radio settings
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Channel";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.AutoChannelEnable";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.RadioEnabled";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.SSIDAdvertisementEnabled";

                // Security (standard parameters only)
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.BeaconType";

                // MAC address
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.BSSID";

                // WiFi password - vendor-specific parameters
                // Different vendors use different parameter names for readable passwords
                // Only query parameters that we know exist on this device to avoid crashes
                if ($device) {
                    // Check Calix parameter (OUI 00:06:31)
                    $calixParam = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.PreSharedKey.1.X_000631_KeyPassphrase";
                    if ($device->parameters()->where('name', 'LIKE', "%.X_000631_KeyPassphrase")->exists()) {
                        $parameters[] = $calixParam;
                    }

                    // Check Broadcom parameter
                    $broadcomParam = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.X_BROADCOM_COM_WlanAdapter.WlVirtIntfCfg.1.WlWpaPsk";
                    if ($device->parameters()->where('name', 'LIKE', "%.X_BROADCOM_COM_WlanAdapter.WlVirtIntfCfg.1.WlWpaPsk")->exists()) {
                        $parameters[] = $broadcomParam;
                    }
                } else {
                    // No device object - try both (fallback for new devices)
                    $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.PreSharedKey.1.X_000631_KeyPassphrase";
                    $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.X_BROADCOM_COM_WlanAdapter.WlVirtIntfCfg.1.WlWpaPsk";
                }

                // Note: Stats parameters excluded to reduce request size
            }

            // Hosts - Basic parameters only (Layer1/Layer3Interface excluded - not universal)
            $hostCount = (int) ($discoveryResults['InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries']['value'] ?? 0);
            for ($i = 1; $i <= $hostCount; $i++) {
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.HostName";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.IPAddress";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.MACAddress";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.Active";
                // Note: InterfaceType, Layer1Interface, Layer3Interface excluded - cause faults on some models
            }
        }

        return $parameters;
    }

    /**
     * Reboot a device
     */
    public function reboot(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Create safety backup before reboot
        $this->createPreOperationBackup($device, 'Reboot');

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'reboot',
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Reboot task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Factory reset a device
     */
    public function factoryReset(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Check if this is a Calix GigaSpire device (TR-098) that doesn't expose WiFi passwords
        // These devices need stored ACS credentials to restore WiFi after factory reset
        $isGigaSpire = $device->isCalix() &&
            $device->getDataModel() === 'TR-098' &&
            str_contains(strtolower($device->model_name ?? ''), 'gigaspire');

        if ($isGigaSpire) {
            $storedCredentials = DeviceWifiCredential::where('device_id', $device->id)->first();

            // Block factory reset if no WiFi password is stored (unless force is specified)
            if (!$storedCredentials || !$storedCredentials->main_password) {
                $forceReset = $request->boolean('force', false);

                if (!$forceReset) {
                    return response()->json([
                        'error' => 'WiFi password not stored in ACS',
                        'message' => 'This GigaSpire device does not expose WiFi passwords in backups. ' .
                            'No ACS-stored WiFi password found - WiFi will not work after factory reset. ' .
                            'Please use Standard WiFi Setup to set and store the password first, ' .
                            'or send force=true to proceed anyway.',
                        'requires_wifi_setup' => true,
                        'device_model' => $device->model_name,
                    ], 422);
                }

                Log::warning("Factory reset forced on {$device->serial_number} without stored WiFi credentials");
            }
        }

        // Create safety backup before factory reset (CRITICAL - this cannot be undone!)
        $this->createPreOperationBackup($device, 'Factory Reset');

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'factory_reset',
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Factory reset task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Get specific parameters from a device
     */
    public function getParameters(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'names' => 'required|array',
            'names.*' => 'required|string',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'parameters' => [
                'names' => $validated['names'],
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Get parameters task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Set parameters on a device
     */
    public function setParameters(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'values' => 'required|array',
        ]);

        // Create safety backup before bulk parameter changes (5+ parameters)
        if (count($validated['values']) >= 5) {
            $this->createPreOperationBackup($device, 'Bulk Parameter Change');
        }

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_params',
            'parameters' => [
                'values' => $validated['values'],
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Set parameters task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Get ALL parameters from device (like NISC USS "Get Everything")
     *
     * For TR-098 devices (Nokia Beacon G6, SmartRG):
     *   Uses GetParameterValues with partial path 'InternetGatewayDevice.'
     *   This returns ALL parameters with values in a single response (~15 seconds)
     *   This matches USS behavior and is much faster than parameter discovery
     *
     * Note: All Calix devices (GigaSpire, GigaCenter, etc.) use TR-098 (InternetGatewayDevice.)
     * Nokia Beacon G6 devices can be either TR-098 or have Device. prefix parameters
     */
    public function getAllParameters(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Determine data model and root path
        $dataModel = $device->getDataModel();
        $root = $dataModel === 'TR-181' ? 'Device.' : 'InternetGatewayDevice.';

        // TR-098 devices support GetParameterValues with partial path
        // This returns ALL parameters with values in one response (like USS does)
        if ($dataModel === 'TR-098') {
            $task = Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_params',
                'parameters' => [
                    'names' => [$root],  // Partial path returns all parameters below it
                ],
                'status' => 'pending',
            ]);

            $message = 'Get all parameters task created - fetching all parameters with values in single request';
        } else {
            // TR-181 devices: use GetParameterNames discovery approach
            $task = Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_parameter_names',
                'parameters' => [
                    'path' => $root,
                    'next_level' => false, // Get ALL parameters recursively
                ],
                'status' => 'pending',
            ]);

            $message = 'Get all parameters task created - discovering all device parameters';
        }

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => $message,
            'task' => $task,
            'approach' => $dataModel === 'TR-098' ? 'partial_path_gpv' : 'parameter_discovery',
        ], 201);
    }

    /**
     * Enable STUN on device for UDP Connection Request
     */
    public function enableStun(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Set STUN parameters using Google's public STUN server
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_params',
            'parameters' => [
                'values' => [
                    'InternetGatewayDevice.ManagementServer.STUNEnable' => [
                        'value' => true,
                        'type' => 'xsd:boolean',
                    ],
                    'InternetGatewayDevice.ManagementServer.STUNServerAddress' => 'stun.l.google.com',
                    'InternetGatewayDevice.ManagementServer.STUNServerPort' => [
                        'value' => 19302,
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        // Mark device as STUN enabled
        $device->update(['stun_enabled' => true]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'STUN enabled on device. Device will report UDPConnectionRequestAddress in next Inform.',
            'task' => $task,
        ], 201);
    }

    /**
     * Update device tags/metadata
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'tags' => 'nullable|array',
        ]);

        $device->update($validated);

        return response()->json($device);
    }

    /**
     * Firmware upgrade (Download RPC)
     */
    public function firmwareUpgrade(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'url' => 'nullable|url',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $downloadUrl = $validated['url'] ?? null;

        // If no URL provided, try to find active firmware for this device type
        if (!$downloadUrl) {
            $deviceType = \App\Models\DeviceType::where('product_class', $device->product_class)->first();

            if ($deviceType) {
                $activeFirmware = $deviceType->firmware()->where('is_active', true)->first();

                if ($activeFirmware) {
                    $downloadUrl = $activeFirmware->getFullDownloadUrl();
                } else {
                    return response()->json([
                        'error' => 'No active firmware found for this device type. Please set an active firmware version first.',
                    ], 400);
                }
            } else {
                return response()->json([
                    'error' => 'Device type not found. Please create a device type for this product class first.',
                ], 400);
            }
        }

        // Create safety backup before firmware upgrade (CRITICAL - firmware can fail)
        $this->createPreOperationBackup($device, 'Firmware Upgrade');

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'download',
            'parameters' => [
                'url' => $downloadUrl,
                'file_type' => '1 Firmware Upgrade Image',
                'username' => $validated['username'] ?? '',
                'password' => $validated['password'] ?? '',
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Firmware upgrade task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Upload logs/config from device
     */
    public function uploadFile(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'url' => 'nullable|url',
            'file_type' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        // Generate secure token for this upload
        $uploadToken = bin2hex(random_bytes(16));

        // Create task first to get task ID
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'upload',
            'parameters' => [
                'url' => '', // Will be set below
                'file_type' => $validated['file_type'] ?? '1 Vendor Configuration File',
                'username' => '',
                'password' => '',
                'upload_token' => $uploadToken,
            ],
            'status' => 'pending',
        ]);

        // Generate URL if not provided - device will PUT file to this URL
        // URL-encode the device ID (it may contain spaces)
        $encodedDeviceId = urlencode($device->id);
        $url = $validated['url'] ?? url("/device-upload/{$encodedDeviceId}/{$task->id}?token={$uploadToken}");

        // Update task with the URL
        $task->update([
            'parameters' => array_merge($task->parameters, ['url' => $url]),
        ]);

        // Trigger connection request so device connects immediately
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Upload task created successfully',
            'task' => $task->fresh(),
            'upload_url' => $url,
        ], 201);
    }

    /**
     * Request config backup from device
     * Uses TR-069 Upload RPC to have device send its configuration file
     */
    public function requestConfigBackup(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Generate secure token for this upload
        $uploadToken = bin2hex(random_bytes(16));

        // Create task first to get task ID
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'upload',
            'description' => 'Config backup request',
            'parameters' => [
                'url' => '', // Will be set below
                'file_type' => '3 Vendor Configuration File',
                'username' => '',
                'password' => '',
                'upload_token' => $uploadToken,
            ],
            'status' => 'pending',
        ]);

        // Generate the upload URL - device will PUT file to this URL
        // Use HTTP since some devices may not support HTTPS
        $baseUrl = config('app.url');
        // If using HTTPS, try to use HTTP variant for device uploads
        if (str_starts_with($baseUrl, 'https://')) {
            $httpUrl = str_replace('https://', 'http://', $baseUrl);
        } else {
            $httpUrl = $baseUrl;
        }
        // URL-encode the device ID (it may contain spaces)
        $encodedDeviceId = urlencode($device->id);
        $url = "{$httpUrl}/device-upload/{$encodedDeviceId}/{$task->id}?token={$uploadToken}";

        // Update task with the URL
        $task->update([
            'parameters' => array_merge($task->parameters, ['url' => $url]),
        ]);

        Log::info('Config backup requested', [
            'device_id' => $device->id,
            'task_id' => $task->id,
            'upload_url' => $url,
        ]);

        // Trigger connection request so device connects immediately
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Config backup request sent to device',
            'task' => $task->fresh(),
            'upload_url' => $url,
        ], 201);
    }

    /**
     * Run ping diagnostic test
     */
    public function pingTest(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'host' => 'nullable|string',
            'count' => 'nullable|integer|min:1|max:10',
            'timeout' => 'nullable|integer|min:1000|max:10000',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'ping_diagnostics',
            'parameters' => [
                'host' => $validated['host'] ?? '8.8.8.8',
                'count' => $validated['count'] ?? 4,
                'timeout' => $validated['timeout'] ?? 5000,
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Ping test task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Run traceroute diagnostic test
     */
    public function tracerouteTest(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'host' => 'nullable|string',
            'max_hops' => 'nullable|integer|min:1|max:30',
            'timeout' => 'nullable|integer|min:1000|max:10000',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'traceroute_diagnostics',
            'parameters' => [
                'host' => $validated['host'] ?? '8.8.8.8',
                'max_hops' => $validated['max_hops'] ?? 30,
                'timeout' => $validated['timeout'] ?? 5000,
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Traceroute test task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Send connection request to device (force immediate connection)
     */
    public function connectionRequest(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $result = $this->connectionRequestService->sendConnectionRequest($device);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'success' => true,
            ]);
        }

        return response()->json([
            'message' => $result['message'],
            'success' => false,
        ], 400);
    }

    /**
     * Helper: Trigger connection request after creating a task
     * This makes tasks execute immediately instead of waiting for next periodic inform
     * Uses UDP connection request if STUN is enabled, falls back to HTTP
     */
    private function triggerConnectionRequestForTask(Device $device): void
    {
        // Only trigger if device has CR URL configured
        if ($device->connection_request_url) {
            // Trigger in background - don't wait for response
            try {
                $this->connectionRequestService->sendConnectionRequestWithFallback($device);
            } catch (\Exception $e) {
                // Log but don't fail the task creation
                \Log::warning('Failed to trigger connection request after task creation', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update WiFi configuration for a WLAN instance
     */
    public function updateWifi(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'instance' => 'required|integer|min:1',
            'ssid' => 'nullable|string|max:32',
            'enabled' => 'nullable|boolean',
            'password' => 'nullable|string|min:8|max:63',
            'security_type' => 'nullable|in:none,wpa2',
            'auto_channel' => 'nullable|boolean',
            'channel' => 'nullable|integer',
            'auto_channel_bandwidth' => 'nullable|boolean',
            'channel_bandwidth' => 'nullable|in:20MHz,40MHz,80MHz',
            'ssid_broadcast' => 'nullable|boolean',
        ]);

        $instance = $validated['instance'];
        $prefix = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$instance}";

        // Build parameter values array
        $values = [];

        // SSID Name
        if (isset($validated['ssid'])) {
            $values["{$prefix}.SSID"] = $validated['ssid'];
        }

        // SSID Enable/Disable
        if (isset($validated['enabled'])) {
            $values["{$prefix}.Enable"] = [
                'value' => $validated['enabled'] ? 1 : 0,
                'type' => 'xsd:boolean',
            ];
        }

        // WiFi Password - Use standard TR-069 parameter (same as NISC USS)
        // USS writes to: PreSharedKey.1.KeyPassphrase (standard parameter)
        // Device stores in: PreSharedKey.1.X_000631_KeyPassphrase (vendor read parameter)
        if (isset($validated['password'])) {
            $values["{$prefix}.PreSharedKey.1.KeyPassphrase"] = $validated['password'];
        }

        // Security Type
        if (isset($validated['security_type'])) {
            if ($validated['security_type'] === 'wpa2') {
                // WPA2-PSK with AES (most secure)
                $values["{$prefix}.BeaconType"] = '11i';
                $values["{$prefix}.IEEE11iAuthenticationMode"] = 'PSKAuthentication';
                $values["{$prefix}.IEEE11iEncryptionModes"] = 'AESEncryption';
                $values["{$prefix}.BasicAuthenticationMode"] = 'None';
                $values["{$prefix}.BasicEncryptionModes"] = 'None';
            } else {
                // No security
                $values["{$prefix}.BeaconType"] = 'Basic';
                $values["{$prefix}.BasicAuthenticationMode"] = 'None';
                $values["{$prefix}.BasicEncryptionModes"] = 'None';
            }
        }

        // Auto Channel
        if (isset($validated['auto_channel'])) {
            $values["{$prefix}.AutoChannelEnable"] = [
                'value' => $validated['auto_channel'] ? 1 : 0,
                'type' => 'xsd:boolean',
            ];
        }

        // Manual Channel (only if auto channel is disabled)
        if (isset($validated['channel']) && !($validated['auto_channel'] ?? false)) {
            $values["{$prefix}.Channel"] = [
                'value' => $validated['channel'],
                'type' => 'xsd:unsignedInt',
            ];
        }

        // Channel Bandwidth
        if (isset($validated['auto_channel_bandwidth']) && $validated['auto_channel_bandwidth']) {
            $values["{$prefix}.X_000631_OperatingChannelBandwidth"] = 'Auto';
        } elseif (isset($validated['channel_bandwidth'])) {
            $values["{$prefix}.X_000631_OperatingChannelBandwidth"] = $validated['channel_bandwidth'];
        }

        // SSID Broadcast
        if (isset($validated['ssid_broadcast'])) {
            $values["{$prefix}.SSIDAdvertisementEnabled"] = [
                'value' => $validated['ssid_broadcast'] ? 1 : 0,
                'type' => 'xsd:boolean',
            ];
        }

        // Store password in DeviceWifiCredential for display in Standard WiFi section
        if (isset($validated['password'])) {
            $this->storeWifiPasswordFromAdvanced($device, $instance, $validated['password']);
        }

        // Create task to set parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_params',
            'parameters' => [
                'values' => $values,
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => "WiFi configuration updated for WLAN instance {$instance}",
            'task' => $task,
            'values_set' => array_keys($values),
        ], 201);
    }

    /**
     * Get WiFi configuration for all WLAN instances
     */
    public function getWifiConfig(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $dataModel = $device->getDataModel();

        if ($dataModel === 'TR-181') {
            return $this->getWifiConfigTr181($device);
        }

        return $this->getWifiConfigTr098($device);
    }

    /**
     * Get WiFi configuration for TR-181 devices
     */
    private function getWifiConfigTr181(Device $device): JsonResponse
    {
        $instances = [];

        // Get radio information to map SSIDs to bands
        $radioParams = $device->parameters()
            ->where('name', 'LIKE', 'Device.WiFi.Radio.%')
            ->get()
            ->keyBy('name');

        // Build radio band mapping
        $radioBands = [];
        foreach ($radioParams as $name => $param) {
            if (preg_match('/Device\.WiFi\.Radio\.(\d+)\.OperatingFrequencyBand/', $name, $matches)) {
                $radioBands[(int)$matches[1]] = $param->value;
            }
        }

        // Get SSID parameters
        $ssidParams = $device->parameters()
            ->where('name', 'LIKE', 'Device.WiFi.SSID.%')
            ->get();

        // Get AccessPoint parameters
        $apParams = $device->parameters()
            ->where('name', 'LIKE', 'Device.WiFi.AccessPoint.%')
            ->get();

        // Organize SSIDs
        foreach ($ssidParams as $param) {
            if (preg_match('/Device\.WiFi\.SSID\.(\d+)\.(.+)/', $param->name, $matches)) {
                $instance = (int) $matches[1];
                $field = $matches[2];

                if (!isset($instances[$instance])) {
                    // SSIDs 1-4 typically map to Radio 1 (2.4GHz), 5-8 to Radio 2 (5GHz)
                    $radioIndex = $instance <= 4 ? 1 : 2;
                    $instances[$instance] = [
                        'instance' => $instance,
                        'band' => $radioBands[$radioIndex] ?? ($instance <= 4 ? '2.4GHz' : '5GHz'),
                        'data_model' => 'TR-181',
                    ];
                }

                switch ($field) {
                    case 'SSID':
                        $instances[$instance]['ssid'] = $param->value;
                        break;
                    case 'Enable':
                        $instances[$instance]['enabled'] = ($param->value === '1' || strtolower($param->value) === 'true');
                        break;
                    case 'Status':
                        $instances[$instance]['status'] = $param->value;
                        break;
                    case 'BSSID':
                        $instances[$instance]['bssid'] = $param->value;
                        break;
                    case 'LowerLayers':
                        // This tells us which radio the SSID is bound to
                        if (preg_match('/Radio\.(\d+)/', $param->value, $radioMatch)) {
                            $radioIdx = (int)$radioMatch[1];
                            $instances[$instance]['radio'] = $radioIdx;
                            $instances[$instance]['band'] = $radioBands[$radioIdx] ?? $instances[$instance]['band'];
                        }
                        break;
                }
            }
        }

        // Add AccessPoint info (security, etc.)
        foreach ($apParams as $param) {
            if (preg_match('/Device\.WiFi\.AccessPoint\.(\d+)\.(.+)/', $param->name, $matches)) {
                $instance = (int) $matches[1];
                $field = $matches[2];

                if (!isset($instances[$instance])) {
                    continue; // Skip if no matching SSID
                }

                switch ($field) {
                    case 'Enable':
                        $instances[$instance]['ap_enabled'] = ($param->value === '1' || strtolower($param->value) === 'true');
                        break;
                    case 'SSIDAdvertisementEnabled':
                        $instances[$instance]['ssid_broadcast'] = ($param->value === '1' || strtolower($param->value) === 'true');
                        break;
                    case 'Security.ModeEnabled':
                        $instances[$instance]['security_mode'] = $param->value;
                        $instances[$instance]['security_type'] = $this->mapSecurityMode($param->value);
                        break;
                    case 'Security.KeyPassphrase':
                        $instances[$instance]['password'] = $param->value;
                        break;
                    case 'MaxAssociatedDevices':
                        $instances[$instance]['max_clients'] = (int) $param->value;
                        break;
                    case 'AssociatedDeviceNumberOfEntries':
                        $instances[$instance]['connected_clients'] = (int) $param->value;
                        break;
                }
            }
        }

        // Add radio info (channel, auto-channel, etc.)
        foreach ($radioParams as $name => $param) {
            if (preg_match('/Device\.WiFi\.Radio\.(\d+)\.(.+)/', $name, $matches)) {
                $radioIdx = (int) $matches[1];
                $field = $matches[2];

                // Apply radio settings to all SSIDs on that radio
                foreach ($instances as &$inst) {
                    $instRadio = $inst['radio'] ?? ($inst['instance'] <= 4 ? 1 : 2);
                    if ($instRadio !== $radioIdx) continue;

                    switch ($field) {
                        case 'Enable':
                            $inst['radio_enabled'] = ($param->value === '1' || strtolower($param->value) === 'true');
                            break;
                        case 'AutoChannelEnable':
                            $inst['auto_channel'] = ($param->value === '1' || strtolower($param->value) === 'true');
                            break;
                        case 'Channel':
                            $inst['channel'] = (int) $param->value;
                            break;
                        case 'CurrentOperatingChannelBandwidth':
                        case 'OperatingChannelBandwidth':
                            $inst['channel_bandwidth'] = $param->value;
                            break;
                        case 'OperatingStandards':
                            $inst['standard'] = $param->value;
                            break;
                        case 'TransmitPower':
                            $inst['transmit_power'] = $param->value;
                            break;
                    }
                }
                unset($inst);
            }
        }

        // Sort by instance number
        ksort($instances);

        return response()->json([
            'device_id' => $device->id,
            'data_model' => 'TR-181',
            'wlan_configurations' => array_values($instances),
        ]);
    }

    /**
     * Map TR-181 security mode to simplified type
     */
    private function mapSecurityMode(string $mode): string
    {
        return match(strtolower($mode)) {
            'none' => 'none',
            'wep-64', 'wep-128' => 'wep',
            'wpa-personal', 'wpa-psk' => 'wpa',
            'wpa2-personal', 'wpa2-psk' => 'wpa2',
            'wpa3-personal', 'wpa3-sae' => 'wpa3',
            'wpa-wpa2-personal' => 'wpa/wpa2',
            'wpa2-wpa3-personal' => 'wpa2/wpa3',
            default => $mode,
        };
    }

    /**
     * Store WiFi password from advanced section in DeviceWifiCredential
     * This ensures the password is available in the Standard WiFi section
     */
    private function storeWifiPasswordFromAdvanced(Device $device, int $instance, string $password): void
    {
        $isCalix = $device->isCalix();
        $isNokia = $device->isNokia();

        // Determine which network type this instance belongs to
        $isGuestNetwork = false;
        $isMainNetwork = false;

        if ($isCalix) {
            $isGigaSpire = strtolower($device->product_class ?? '') === 'gigaspire';

            if ($isGigaSpire) {
                // GigaSpire: 5GHz uses instances 1-8, 2.4GHz uses instances 9-16
                $guestInstances = [2, 10]; // inst5Guest=2, inst24Guest=10
                $mainInstances = [1, 9];   // inst5Primary=1, inst24Primary=9
            } else {
                // GigaCenter: 2.4GHz uses instances 1-8, 5GHz uses instances 9-16
                $guestInstances = [2, 10]; // inst24Guest=2, inst5Guest=10
                $mainInstances = [1, 9];   // inst24Primary=1, inst5Primary=9
            }

            $isGuestNetwork = in_array($instance, $guestInstances);
            $isMainNetwork = in_array($instance, $mainInstances);
        } elseif ($isNokia) {
            // Nokia TR-098: Instance 4=Guest 2.4GHz, 8=Guest 5GHz, 1=Main 2.4GHz, 5=Main 5GHz
            $isGuestNetwork = in_array($instance, [4, 8]);
            $isMainNetwork = in_array($instance, [1, 5]);
        }

        // Only store if it's a recognized main or guest network
        if (!$isGuestNetwork && !$isMainNetwork) {
            return;
        }

        // Update or create the credential record
        $credential = DeviceWifiCredential::firstOrNew(['device_id' => $device->id]);

        if ($isGuestNetwork) {
            $credential->guest_password = $password;
        } elseif ($isMainNetwork) {
            $credential->main_password = $password;
        }

        $credential->set_by = 'advanced_wifi';
        $credential->save();

        Log::info('Stored WiFi password from advanced section', [
            'device_id' => $device->id,
            'instance' => $instance,
            'network_type' => $isGuestNetwork ? 'guest' : 'main',
        ]);
    }

    /**
     * Get WiFi configuration for TR-098 devices
     */
    private function getWifiConfigTr098(Device $device): JsonResponse
    {
        // Get all WLAN configuration parameters
        $wlanParams = $device->parameters()
            ->where('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%')
            ->whereNotLike('name', '%AssociatedDevice%')
            ->whereNotLike('name', '%Stats%')
            ->whereNotLike('name', '%WPS%')
            ->where(function ($query) {
                // Include PreSharedKey.1.X_000631_KeyPassphrase for password reading
                // Exclude other PreSharedKey.1 params
                $query->where('name', 'NOT LIKE', '%PreSharedKey.1%')
                    ->orWhere('name', 'LIKE', '%PreSharedKey.1.X_000631_KeyPassphrase');
            })
            ->get();

        // Organize by instance
        $instances = [];
        foreach ($wlanParams as $param) {
            if (preg_match('/WLANConfiguration\.(\d+)\.(.+)/', $param->name, $matches)) {
                $instance = (int) $matches[1];
                $field = $matches[2];

                if (!isset($instances[$instance])) {
                    $instances[$instance] = [
                        'instance' => $instance,
                        'band' => $instance >= 9 ? '5GHz' : '2.4GHz',
                        'data_model' => 'TR-098',
                    ];
                }

                // Map relevant fields
                switch ($field) {
                    case 'SSID':
                        $instances[$instance]['ssid'] = $param->value;
                        break;
                    case 'Enable':
                        $instances[$instance]['enabled'] = ($param->value === '1' || $param->value === 'true');
                        break;
                    case 'PreSharedKey.1.X_000631_KeyPassphrase':
                        // Vendor read parameter where device stores the password
                        $instances[$instance]['password'] = $param->value;
                        break;
                    case 'BeaconType':
                        $instances[$instance]['security_type'] = ($param->value === 'Basic') ? 'none' : 'wpa2';
                        break;
                    case 'RadioEnabled':
                        $instances[$instance]['radio_enabled'] = ($param->value === '1' || $param->value === 'true');
                        break;
                    case 'AutoChannelEnable':
                        $instances[$instance]['auto_channel'] = ($param->value === '1' || $param->value === 'true');
                        break;
                    case 'Channel':
                        $instances[$instance]['channel'] = (int) $param->value;
                        break;
                    case 'X_000631_OperatingChannelBandwidth':
                        $instances[$instance]['channel_bandwidth'] = $param->value;
                        $instances[$instance]['auto_channel_bandwidth'] = ($param->value === 'Auto');
                        break;
                    case 'SSIDAdvertisementEnabled':
                        $instances[$instance]['ssid_broadcast'] = ($param->value === '1' || $param->value === 'true');
                        break;
                    case 'Standard':
                        $instances[$instance]['standard'] = $param->value;
                        break;
                    case 'Status':
                        $instances[$instance]['status'] = $param->value;
                        break;
                    case 'BSSID':
                        $instances[$instance]['bssid'] = $param->value;
                        break;
                }
            }
        }

        // Sort by instance number
        ksort($instances);

        return response()->json([
            'device_id' => $device->id,
            'data_model' => 'TR-098',
            'wlan_configurations' => array_values($instances),
        ]);
    }

    /**
     * Enable or disable WiFi radio for a frequency band (2.4GHz or 5GHz)
     * This sets RadioEnabled for all SSID instances on that band
     */
    public function updateWifiRadio(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'band' => 'required|in:2.4GHz,5GHz',
            'enabled' => 'required|boolean',
        ]);

        $band = $validated['band'];
        $enabled = $validated['enabled'];

        // Determine which instances to update based on band
        // 2.4GHz: instances 1-8
        // 5GHz: instances 9-16
        $instances = $band === '2.4GHz' ? range(1, 8) : range(9, 16);

        // Build parameter values array to set RadioEnabled for all instances
        $values = [];
        foreach ($instances as $instance) {
            $prefix = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$instance}";
            $values["{$prefix}.RadioEnabled"] = [
                'value' => $enabled ? 1 : 0,
                'type' => 'xsd:boolean',
            ];
        }

        // Create task to set parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_params',
            'parameters' => [
                'values' => $values,
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => "{$band} radio " . ($enabled ? 'enabled' : 'disabled'),
            'task' => $task,
            'instances_updated' => $instances,
        ], 201);
    }

    /**
     * Enable remote GUI access and return access details
     */
    public function remoteGui(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $dataModel = $device->getDataModel();

        // Check if this is a Nokia device using centralized detection
        $isNokia = $device->isNokia();
        $isCalix = $device->isCalix();

        // Default values that will be returned
        $port = null;
        $protocol = 'https'; // Default to HTTPS, Calix uses HTTP
        $username = null;
        $externalIp = null;

        // Parameters to query and enable parameters vary by device type
        // Priority: Check data model first, then vendor-specific overrides
        // Get support password from .env
        $supportPassword = env('SUPPORT_PASSWORD', 'keepOut-72863!!!');

        if ($isNokia && $dataModel === 'TR-181') {
            // Nokia Beacon G6 with TR-181 data model - use Device. paths with Nokia vendor extensions
            $parametersToQuery = [
                'Device.Users.User.1.Username',
                'Device.Users.User.1.Password',
                'Device.Users.User.2.Username',
                'Device.Users.User.2.Password',
                'Device.UserInterface.RemoteAccess.Port',
                'Device.UserInterface.RemoteAccess.Enable',
                'Device.X_ALU_COM_RemoteGUI.Enable',
                'Device.X_ALU_COM_RemoteGUI.Port',
            ];
            $enableParams = [
                'Device.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                // Set superadmin password to known value for remote access
                'Device.Users.User.2.Password' => [
                    'value' => $supportPassword,
                    'type' => 'xsd:string',
                ],
            ];
            // Nokia defaults
            $port = 443;
            $username = 'superadmin';

            // Get external IP from Device.IP.Interface.2 (WAN interface)
            $externalIpParam = $device->parameters()
                ->where('name', 'LIKE', 'Device.IP.Interface.2.IPv4Address.%.IPAddress')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->where('value', 'NOT LIKE', '192.168.%')
                ->where('value', 'NOT LIKE', '10.%')
                ->where('value', 'NOT LIKE', '172.16.%')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;

        } elseif ($isNokia && $dataModel === 'TR-098') {
            // Nokia Beacon with TR-098 data model - use IGD paths with Nokia vendor extensions
            $parametersToQuery = [
                'InternetGatewayDevice.X_Authentication.WebAccount.UserName',
                'InternetGatewayDevice.X_Authentication.WebAccount.Password',
                'InternetGatewayDevice.DeviceInfo.X_ALU-COM_ServiceManage.WanHttpsPort',
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ALU-COM_WanAccessCfg.HttpsDisabled',
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
            ];
            // Enable HTTPS remote access (HttpsDisabled = false means enabled) and set password
            $enableParams = [
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ALU-COM_WanAccessCfg.HttpsDisabled' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                // Set superadmin password to known value for remote access
                'InternetGatewayDevice.X_Authentication.WebAccount.Password' => [
                    'value' => $supportPassword,
                    'type' => 'xsd:string',
                ],
            ];
            // Nokia defaults
            $port = 443;
            $username = 'superadmin';

            // Get external IP from WANIPConnection
            $externalIpParam = $device->parameters()
                ->where('name', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;

        } elseif ($device->product_class === 'GigaSpire') {
            // Calix GigaSpire devices - TR-098 data model, use HTTP on port 8080
            // User.1 = admin (local), User.2 = support (remote access capable)
            $parametersToQuery = [
                'InternetGatewayDevice.User.1.Username',
                'InternetGatewayDevice.User.1.Password',
                'InternetGatewayDevice.User.2.Username',
                'InternetGatewayDevice.User.2.Password',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Port',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable',
                'InternetGatewayDevice.User.2.RemoteAccessCapable',
            ];

            // Get support password from .env
            $supportPassword = env('GIGASPIRE_SUPPORT_PASSWORD', 'keepOut-72863!!!');
            $supportUsername = env('GIGASPIRE_SUPPORT_USER', 'support');

            $enableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.Password' => [
                    'value' => $supportPassword,
                    'type' => 'xsd:string',
                ],
            ];

            // GigaSpire uses HTTP on port 8080
            $port = 8080;
            $protocol = 'http';
            $username = $supportUsername;

            // TR-098: External IP is in WANIPConnection
            $externalIpParam = $device->parameters()
                ->where('name', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;

        } elseif ($dataModel === 'TR-181') {
            // TR-181 devices (Nokia Beacon G6 with Device. prefix) - HTTPS on port 8443
            // Note: Calix devices are TR-098, not TR-181
            $parametersToQuery = [
                'Device.Users.User.1.Username',
                'Device.Users.User.1.Password',
                'Device.Users.User.2.Username',
                'Device.Users.User.2.Password',
                'Device.UserInterface.RemoteAccess.Port',
                'Device.UserInterface.RemoteAccess.Enable',
            ];
            $enableParams = [
                'Device.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
            ];

            // TR-181: External IP is on Device.IP.Interface.2 (WAN interface)
            $externalIpParam = $device->parameters()
                ->where('name', 'LIKE', 'Device.IP.Interface.2.IPv4Address.%.IPAddress')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->where('value', 'NOT LIKE', '192.168.%')
                ->where('value', 'NOT LIKE', '10.%')
                ->where('value', 'NOT LIKE', '172.16.%')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;

        } elseif ($device->isSmartRG()) {
            // SmartRG/Sagemcom devices - use LAN IP (MER network) for GUI access
            $parametersToQuery = [
                'InternetGatewayDevice.User.1.Username',
                'InternetGatewayDevice.User.1.Password',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Port',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable',
                'InternetGatewayDevice.User.2.RemoteAccessCapable',
                'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress',
            ];
            $enableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
            ];

            // SmartRG: Use LAN IP (192.168.x.x) for backend/MER network access
            $lanIpParam = $device->parameters()
                ->where('name', 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress')
                ->first();
            $externalIp = $lanIpParam ? $lanIpParam->value : '192.168.1.1'; // Default to 192.168.1.1

        } elseif ($isCalix) {
            // Calix GigaCenter devices (ENT/ONT/804Mesh) - TR-098 data model
            // User.1 = admin (local), User.2 = support (remote access capable)
            $supportUsername = env('GIGASPIRE_SUPPORT_USER', 'support');

            $parametersToQuery = [
                'InternetGatewayDevice.User.1.Username',
                'InternetGatewayDevice.User.1.Password',
                'InternetGatewayDevice.User.2.Username',
                'InternetGatewayDevice.User.2.Password',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Port',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable',
                'InternetGatewayDevice.User.2.RemoteAccessCapable',
            ];
            $enableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                // Set support password to known value for remote access
                'InternetGatewayDevice.User.2.Password' => [
                    'value' => $supportPassword,
                    'type' => 'xsd:string',
                ],
            ];

            // GigaCenter uses HTTP on port 8080 (same as GigaSpire)
            $port = 8080;
            $protocol = 'http';
            $username = $supportUsername;

            // TR-098: External IP is in WANIPConnection
            $externalIpParam = $device->parameters()
                ->where('name', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;

        } else {
            // Generic TR-098 devices (fallback)
            $parametersToQuery = [
                'InternetGatewayDevice.User.1.Username',
                'InternetGatewayDevice.User.1.Password',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Port',
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable',
                'InternetGatewayDevice.User.2.RemoteAccessCapable',
            ];
            $enableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                // Set support password to known value for remote access
                'InternetGatewayDevice.User.2.Password' => [
                    'value' => $supportPassword,
                    'type' => 'xsd:string',
                ],
            ];

            // TR-098: External IP is in WANIPConnection
            $externalIpParam = $device->parameters()
                ->where('name', 'LIKE', '%ExternalIPAddress%')
                ->where('name', 'LIKE', '%WANIPConnection%')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : null;
        }

        // Create task to query parameters and enable remote access
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_parameter_values',
            'status' => 'pending',
            'parameters' => $parametersToQuery,
        ]);

        // Also create a task to enable remote access
        $enableTask = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => $enableParams,
        ]);

        // Set the remote support expiry time (1 hour from now)
        $device->remote_support_expires_at = now()->addHour();
        $device->save();

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        $isSmartRG = $device->isSmartRG();

        return response()->json([
            'task' => $task,
            'enable_task' => $enableTask,
            'external_ip' => $externalIp,
            'port' => $port,
            'protocol' => $protocol,
            'username' => $username,
            'is_nokia' => $isNokia,
            'is_calix' => $isCalix,
            'is_smartrg' => $isSmartRG,
            'use_lan_ip' => $isSmartRG, // Indicates this IP is for backend/MER network access
            'message' => $isSmartRG
                ? 'Remote access enabled. Use the LAN IP from the backend/MER network.'
                : 'Remote access is being enabled...',
        ]);
    }

    /**
     * Close remote GUI access and reset password to random value
     */
    public function closeRemoteAccess(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Skip SmartRG devices - they use MER network access, not remote GUI
        if ($device->isSmartRG()) {
            return response()->json([
                'success' => true,
                'message' => 'SmartRG devices use MER network access - no remote access to disable.',
            ]);
        }

        $dataModel = $device->getDataModel();
        $isNokia = $device->isNokia();
        $isCalix = $device->isCalix();

        // Generate random password (16 chars alphanumeric + special)
        $randomPassword = bin2hex(random_bytes(8)) . '!' . rand(10, 99);

        // Build disable parameters based on device type
        $disableParams = [];

        if ($isNokia && $dataModel === 'TR-181') {
            $disableParams = [
                'Device.UserInterface.RemoteAccess.Enable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'Device.Users.User.2.Password' => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        } elseif ($isNokia && $dataModel === 'TR-098') {
            $disableParams = [
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ALU-COM_WanAccessCfg.HttpsDisabled' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.X_Authentication.WebAccount.Password' => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        } elseif ($isCalix || $device->product_class === 'GigaSpire') {
            $disableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.Password' => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        } else {
            // Generic TR-098 fallback
            $disableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.Password' => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        }

        // Create task to disable remote access and reset password
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'description' => 'Close remote access and reset password',
            'status' => 'pending',
            'parameters' => $disableParams,
        ]);

        // Clear the remote support expiry time
        $device->remote_support_expires_at = null;
        $device->save();

        // Trigger connection request to apply changes
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'success' => true,
            'task' => $task,
            'message' => 'Remote access is being disabled and password reset to random value.',
        ]);
    }

    /**
     * Create a configuration backup for a device
     */
    public function createBackup(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'notes' => 'nullable|string',
            'is_starred' => 'nullable|boolean',
            'is_auto' => 'boolean',
        ]);

        // Get all current parameters from the database
        $parameters = $device->parameters()
            ->get()
            ->mapWithKeys(function ($param) {
                return [$param->name => [
                    'value' => $param->value,
                    'type' => $param->type,
                    'writable' => $param->writable,
                ]];
            })
            ->toArray();

        $parameterCount = count($parameters);

        // Generate backup name if not provided
        $backupName = $validated['name'] ??
            ($validated['is_auto'] ?? false ? 'Auto Backup' : 'Manual Backup') .
            ' - ' . now()->format('Y-m-d H:i:s');

        // Create the backup
        $backup = $device->configBackups()->create([
            'name' => $backupName,
            'description' => $validated['description'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'notes' => $validated['notes'] ?? null,
            'is_starred' => $validated['is_starred'] ?? false,
            'backup_data' => $parameters,
            'is_auto' => $validated['is_auto'] ?? false,
            'parameter_count' => $parameterCount,
        ]);

        // Update device backup tracking
        $device->update([
            'initial_backup_created' => true,
            'last_backup_at' => now(),
        ]);

        return response()->json([
            'backup' => $backup,
            'message' => 'Configuration backup created successfully',
        ]);
    }

    /**
     * Create a pre-operation safety backup before critical operations
     */
    private function createPreOperationBackup(Device $device, string $operation): ?ConfigBackup
    {
        // Get all current parameters
        $parameters = $device->parameters()
            ->get()
            ->mapWithKeys(function ($param) {
                return [$param->name => [
                    'value' => $param->value,
                    'type' => $param->type,
                    'writable' => $param->writable,
                ]];
            })
            ->toArray();

        $parameterCount = count($parameters);

        if ($parameterCount === 0) {
            Log::warning('Skipping pre-operation backup - no parameters', [
                'device_id' => $device->id,
                'operation' => $operation,
            ]);
            return null;
        }

        // Create the safety backup
        $backup = $device->configBackups()->create([
            'name' => "Pre-{$operation} Backup - " . now()->format('Y-m-d H:i:s'),
            'description' => "Automatic safety backup created before {$operation} operation",
            'backup_data' => $parameters,
            'is_auto' => true,
            'parameter_count' => $parameterCount,
        ]);

        // Update device backup tracking
        $device->update([
            'last_backup_at' => now(),
        ]);

        Log::info('Pre-operation backup created', [
            'device_id' => $device->id,
            'operation' => $operation,
            'backup_id' => $backup->id,
            'parameter_count' => $parameterCount,
        ]);

        return $backup;
    }

    /**
     * Get all backups for a device
     */
    public function getBackups(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $query = $device->configBackups();

        // Filter by tags (if any tag matches)
        if ($request->has('tags') && is_array($request->tags)) {
            $query->where(function ($q) use ($request) {
                foreach ($request->tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        // Filter by starred
        if ($request->has('starred')) {
            $query->where('is_starred', filter_var($request->starred, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $backups = $query
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'name' => $backup->name,
                    'description' => $backup->description,
                    'tags' => $backup->tags ?? [],
                    'notes' => $backup->notes,
                    'is_starred' => $backup->is_starred,
                    'parameter_count' => $backup->parameter_count,
                    'is_auto' => $backup->is_auto,
                    'size' => $backup->size,
                    'created_at' => $backup->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'backups' => $backups,
        ]);
    }

    /**
     * Restore a configuration backup
     */
    public function restoreBackup(Request $request, string $id, int $backupId): JsonResponse
    {
        $device = Device::findOrFail($id);
        $backup = $device->configBackups()->findOrFail($backupId);

        // Validate selective restore parameters if provided
        $validated = $request->validate([
            'parameters' => 'nullable|array',
            'parameters.*' => 'string',
            'create_backup' => 'nullable|boolean',
        ]);

        $selectedParams = $validated['parameters'] ?? null;
        $createBackup = $validated['create_backup'] ?? true;

        // Create safety backup before configuration restore
        if ($createBackup) {
            $this->createPreOperationBackup($device, 'Configuration Restore');
        }

        // Check if backup has writable info - TR-098 devices using GetParameterValues
        // with partial path don't get writable attribute, only GetParameterNames provides it
        $hasWritableInfo = collect($backup->backup_data)->contains(function ($param) {
            return isset($param['writable']) && $param['writable'] === true;
        });

        // For TR-098 Calix devices without writable info, use heuristic-based filtering
        $isCalix = $device->isCalix();
        $dataModel = $device->getDataModel();

        if (!$hasWritableInfo && $dataModel === 'TR-098' && $isCalix) {
            // Use pattern-based heuristic for commonly writable TR-098 Calix parameters
            $writableParams = collect($backup->backup_data)
                ->filter(function ($param, $name) use ($selectedParams) {
                    // If selective restore, must be in selected list
                    if ($selectedParams !== null && !in_array($name, $selectedParams)) {
                        return false;
                    }

                    // Password/passphrase parameters are ALWAYS included for later injection
                    // Even if empty in backup, we'll inject stored passwords from DeviceWifiCredential
                    // EXCEPTION: ConnectionRequestPassword should NEVER be set to empty (breaks management)
                    $isPasswordParam = preg_match('/(KeyPassphrase|PreSharedKey|Password|Passphrase|PSK|WEPKey)$/i', $name);
                    if ($isPasswordParam) {
                        // Skip ConnectionRequestPassword if empty - setting it empty breaks connection requests
                        if (preg_match('/ConnectionRequestPassword$/i', $name)) {
                            $value = $param['value'] ?? '';
                            if ($value === '' || $value === null) {
                                return false; // Don't include empty connection request password
                            }
                        }
                        return true; // Include other password params for injection
                    }

                    // Use pattern matching to identify likely writable parameters
                    if (!$this->isTr098CalixWritableParameter($name)) {
                        return false;
                    }
                    return true;
                })
                ->mapWithKeys(function ($param, $name) {
                    return [$name => [
                        'value' => $param['value'],
                        'type' => $param['type'] ?? 'xsd:string',
                    ]];
                })
                ->toArray();
        } else {
            // Standard approach: filter by writable attribute
            $writableParams = collect($backup->backup_data)
                ->filter(function ($param, $name) use ($selectedParams) {
                    // If selective restore, must be in selected list
                    if ($selectedParams !== null && !in_array($name, $selectedParams)) {
                        return false;
                    }

                    // Password/passphrase parameters are ALWAYS writable even if device reports otherwise
                    // Devices often incorrectly report these as non-writable because values can't be READ back
                    // But they ARE writable - you CAN set them, you just can't retrieve them (write-only)
                    // EXCEPTION: ConnectionRequestPassword should NEVER be set to empty (breaks management)
                    $isPasswordParam = preg_match('/(KeyPassphrase|PreSharedKey|Password|Passphrase|PSK|WEPKey)$/i', $name);
                    if ($isPasswordParam) {
                        // Skip ConnectionRequestPassword if empty - setting it empty breaks connection requests
                        if (preg_match('/ConnectionRequestPassword$/i', $name)) {
                            $value = $param['value'] ?? '';
                            if ($value === '' || $value === null) {
                                return false; // Don't include empty connection request password
                            }
                        }
                        return true; // Include other password params for injection
                    }

                    // For non-password params, must be writable
                    if (!($param['writable'] ?? false)) {
                        return false;
                    }
                    return true;
                })
                ->mapWithKeys(function ($param, $name) {
                    return [$name => [
                        'value' => $param['value'],
                        'type' => $param['type'] ?? 'xsd:string',
                    ]];
                })
                ->toArray();
        }

        if (empty($writableParams)) {
            $errorMsg = 'No writable parameters found in backup';
            if ($selectedParams) {
                $errorMsg .= ' matching selection';
            } elseif (!$hasWritableInfo && $dataModel === 'TR-098' && $isCalix) {
                $errorMsg .= '. Try running "Get Everything" first to populate writable info, or use selective restore.';
            }
            return response()->json([
                'error' => $errorMsg,
            ], 400);
        }

        // Validate parameters against device's current structure and remap if needed
        $validationResult = $this->validateAndRemapRestoreParams($device, $writableParams);
        $writableParams = $validationResult['params'];
        $skippedParams = $validationResult['skipped'];
        $remappedParams = $validationResult['remapped'];

        if (!empty($skippedParams)) {
            Log::info("Restore backup #{$backupId} for device {$device->serial_number}: Skipped " . count($skippedParams) . " invalid parameters", [
                'skipped_samples' => array_slice($skippedParams, 0, 10)
            ]);
        }

        if (!empty($remappedParams)) {
            Log::info("Restore backup #{$backupId} for device {$device->serial_number}: Remapped " . count($remappedParams) . " parameters", [
                'remapped' => $remappedParams
            ]);
        }

        // Inject stored WiFi passwords for empty password parameters on ALL devices
        // Many devices don't expose WiFi passwords in backups for security reasons
        $injectedPasswords = [];
        $storedCredentials = DeviceWifiCredential::where('device_id', $device->id)->first();

        if ($storedCredentials) {
            foreach ($writableParams as $paramName => &$paramData) {
                $value = $paramData['value'] ?? '';

                // Skip if value is already set (not empty)
                if ($value !== '' && $value !== null) {
                    continue;
                }

                $isGuestNetwork = false;
                $isPasswordParam = false;

                // TR-098 style: WLANConfiguration.{i}.PreSharedKey.{j}.KeyPassphrase
                if (preg_match('/WLANConfiguration\.(\d+)\.PreSharedKey\.\d+\.KeyPassphrase$/i', $paramName, $matches)) {
                    $instance = (int) $matches[1];
                    $isPasswordParam = true;
                    // Calix TR-098: instances 2, 10 are guest networks
                    $isGuestNetwork = in_array($instance, [2, 10]);
                }
                // TR-181 style: WiFi.AccessPoint.{i}.Security.KeyPassphrase
                elseif (preg_match('/WiFi\.AccessPoint\.(\d+)\.Security\.KeyPassphrase$/i', $paramName, $matches)) {
                    $instance = (int) $matches[1];
                    $isPasswordParam = true;
                    // TR-181: instances 3, 4 are typically guest networks (SSID 3/4)
                    $isGuestNetwork = in_array($instance, [3, 4]);
                }
                // Nokia TR-098 style: WLANConfiguration.{i}.KeyPassphrase (direct)
                elseif (preg_match('/WLANConfiguration\.(\d+)\.KeyPassphrase$/i', $paramName, $matches)) {
                    $instance = (int) $matches[1];
                    $isPasswordParam = true;
                    $isGuestNetwork = in_array($instance, [2, 10]);
                }
                // Generic passphrase/password patterns
                elseif (preg_match('/(Passphrase|PreSharedKey)$/i', $paramName) &&
                        preg_match('/(WiFi|WLAN|Wireless)/i', $paramName)) {
                    $isPasswordParam = true;
                    // Check for "guest" in the parameter name
                    $isGuestNetwork = preg_match('/guest/i', $paramName);
                }

                if ($isPasswordParam) {
                    if ($isGuestNetwork && $storedCredentials->guest_password) {
                        $paramData['value'] = $storedCredentials->guest_password;
                        $injectedPasswords[] = "{$paramName} (guest)";
                        Log::info("Restore: Injected stored guest WiFi password for {$paramName}");
                    } elseif (!$isGuestNetwork && $storedCredentials->main_password) {
                        $paramData['value'] = $storedCredentials->main_password;
                        $injectedPasswords[] = "{$paramName} (main)";
                        Log::info("Restore: Injected stored main WiFi password for {$paramName}");
                    }
                }
            }
            unset($paramData); // Break reference after foreach
        }

        // Create task to restore the parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => $writableParams,
        ]);

        // Create follow-up refresh task to run after restore completes
        // Uses wait_for_next_session to ensure it runs in a new session after restore
        $refreshTask = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'status' => 'pending',
            'parameters' => ['names' => array_keys($writableParams)],
            'progress_info' => [
                'wait_for_next_session' => true,
                'post_restore_refresh' => true,
                'restore_task_id' => $task->id,
            ],
        ]);

        Log::info("Created post-restore refresh task #{$refreshTask->id} for device {$device->serial_number} (after restore task #{$task->id})");

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        $restoreType = $selectedParams ? 'Selective restore' : 'Full restore';

        $response = [
            'task' => $task,
            'refresh_task' => $refreshTask,
            'message' => $restoreType . ' initiated (refresh scheduled)',
            'writable_params_count' => count($writableParams),
            'total_params_in_backup' => count($backup->backup_data),
        ];

        // Add info about skipped/remapped parameters
        if (!empty($skippedParams)) {
            $response['skipped_params_count'] = count($skippedParams);
            $response['skipped_params_samples'] = array_slice($skippedParams, 0, 5);
        }

        if (!empty($remappedParams)) {
            $response['remapped_params_count'] = count($remappedParams);
            $response['remapped_params'] = $remappedParams;
        }

        // Add info about injected WiFi passwords if any were used
        if (!empty($injectedPasswords)) {
            $response['wifi_passwords_injected'] = true;
            $response['wifi_passwords_injected_count'] = count($injectedPasswords);
            $response['message'] .= ' (WiFi passwords restored from ACS storage)';
            Log::info("Restore backup #{$backupId} for device {$device->serial_number}: Injected " . count($injectedPasswords) . " stored WiFi passwords");
        }

        return response()->json($response);
    }

    /**
     * Compare two configuration backups
     */
    public function compareBackups(string $id, int $backup1Id, int $backup2Id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $backup1 = $device->configBackups()->findOrFail($backup1Id);
        $backup2 = $device->configBackups()->findOrFail($backup2Id);

        $data1 = $backup1->backup_data ?? [];
        $data2 = $backup2->backup_data ?? [];

        // Find added parameters (in backup2 but not in backup1)
        $added = [];
        foreach ($data2 as $name => $param) {
            if (!isset($data1[$name])) {
                $added[$name] = [
                    'value' => $param['value'] ?? '',
                    'type' => $param['type'] ?? 'xsd:string',
                    'writable' => $param['writable'] ?? false,
                ];
            }
        }

        // Find removed parameters (in backup1 but not in backup2)
        $removed = [];
        foreach ($data1 as $name => $param) {
            if (!isset($data2[$name])) {
                $removed[$name] = [
                    'value' => $param['value'] ?? '',
                    'type' => $param['type'] ?? 'xsd:string',
                    'writable' => $param['writable'] ?? false,
                ];
            }
        }

        // Find modified parameters (different values)
        $modified = [];
        foreach ($data1 as $name => $param1) {
            if (isset($data2[$name])) {
                $param2 = $data2[$name];
                $value1 = $param1['value'] ?? '';
                $value2 = $param2['value'] ?? '';

                if ($value1 !== $value2) {
                    $modified[$name] = [
                        'old_value' => $value1,
                        'new_value' => $value2,
                        'type' => $param1['type'] ?? 'xsd:string',
                        'writable' => $param1['writable'] ?? false,
                    ];
                }
            }
        }

        // Find unchanged parameters
        $unchanged = [];
        foreach ($data1 as $name => $param1) {
            if (isset($data2[$name])) {
                $param2 = $data2[$name];
                $value1 = $param1['value'] ?? '';
                $value2 = $param2['value'] ?? '';

                if ($value1 === $value2) {
                    $unchanged[] = $name;
                }
            }
        }

        return response()->json([
            'backup1' => [
                'id' => $backup1->id,
                'name' => $backup1->name,
                'created_at' => $backup1->created_at->format('Y-m-d H:i:s'),
                'parameter_count' => count($data1),
            ],
            'backup2' => [
                'id' => $backup2->id,
                'name' => $backup2->name,
                'created_at' => $backup2->created_at->format('Y-m-d H:i:s'),
                'parameter_count' => count($data2),
            ],
            'comparison' => [
                'added' => $added,
                'removed' => $removed,
                'modified' => $modified,
                'unchanged_count' => count($unchanged),
            ],
            'summary' => [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'modified_count' => count($modified),
                'unchanged_count' => count($unchanged),
            ],
        ]);
    }

    /**
     * Download a backup as JSON file
     */
    public function downloadBackup(string $id, int $backupId)
    {
        $device = Device::findOrFail($id);
        $backup = $device->configBackups()->findOrFail($backupId);

        $exportData = [
            'hayacs_backup_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'device_id' => $device->id,
            'device_manufacturer' => $device->manufacturer,
            'device_model' => $device->model_name,
            'backup' => [
                'id' => $backup->id,
                'name' => $backup->name,
                'description' => $backup->description,
                'created_at' => $backup->created_at->toIso8601String(),
                'is_auto' => $backup->is_auto,
                'parameter_count' => $backup->parameter_count,
                'backup_data' => $backup->backup_data,
            ],
        ];

        $filename = 'backup-' . $device->id . '-' . $backup->id . '-' . now()->format('Y-m-d-His') . '.json';

        return response()->json($exportData, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Import a backup from JSON file
     */
    public function importBackup(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'backup_file' => 'required|file|mimes:json,txt|max:10240', // Max 10MB
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $fileContent = file_get_contents($validated['backup_file']->getRealPath());
            $importData = json_decode($fileContent, true);

            if (!$importData) {
                return response()->json([
                    'error' => 'Invalid JSON file',
                ], 400);
            }

            // Validate backup format
            if (!isset($importData['hayacs_backup_version']) || !isset($importData['backup']['backup_data'])) {
                return response()->json([
                    'error' => 'Invalid backup file format. This does not appear to be a HayACS backup file.',
                ], 400);
            }

            $backupData = $importData['backup'];
            $parameterData = $backupData['backup_data'];

            if (empty($parameterData)) {
                return response()->json([
                    'error' => 'Backup file contains no parameter data',
                ], 400);
            }

            // Create the backup
            $backup = $device->configBackups()->create([
                'name' => $validated['name'] ?? ($backupData['name'] . ' (Imported)'),
                'description' => 'Imported from backup file on ' . now()->format('Y-m-d H:i:s') .
                    ($backupData['description'] ? "\nOriginal: " . $backupData['description'] : ''),
                'backup_data' => $parameterData,
                'is_auto' => false,
                'parameter_count' => count($parameterData),
            ]);

            // Update device backup tracking
            $device->update([
                'last_backup_at' => now(),
            ]);

            Log::info('Backup imported from file', [
                'device_id' => $device->id,
                'backup_id' => $backup->id,
                'parameter_count' => count($parameterData),
                'original_backup_id' => $backupData['id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Backup imported successfully',
                'backup' => [
                    'id' => $backup->id,
                    'name' => $backup->name,
                    'parameter_count' => $backup->parameter_count,
                    'created_at' => $backup->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Backup import failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to import backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore native device config file via TR-069 Download RPC
     *
     * This uses the device's native binary config file (from Upload RPC)
     * which can restore all settings including WiFi passwords.
     */
    public function restoreNativeConfig(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'task_id' => 'required|integer', // The upload task that has the config file
        ]);

        // Find the source upload task
        $sourceTask = Task::where('id', $validated['task_id'])
            ->where('device_id', $device->id)
            ->where('task_type', 'upload')
            ->where('status', 'completed')
            ->first();

        if (!$sourceTask) {
            return response()->json([
                'error' => 'Source config file not found or task not completed',
            ], 404);
        }

        // Check that the file exists
        $sourceFile = $sourceTask->progress_info['uploaded_file'] ?? null;
        if (!$sourceFile || !Storage::disk('local')->exists($sourceFile)) {
            return response()->json([
                'error' => 'Config file not found on server',
            ], 404);
        }

        // Generate download token
        $downloadToken = bin2hex(random_bytes(16));

        // Create config restore task (uses Download RPC)
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'config_restore',
            'description' => 'Native config restore from uploaded file',
            'parameters' => [
                'source_file' => $sourceFile,
                'source_task_id' => $sourceTask->id,
                'download_token' => $downloadToken,
                'file_type' => '3 Vendor Configuration File',
                // URL will be set after we have the task ID
            ],
            'status' => 'pending',
        ]);

        // Now update with the full URL including task ID
        $downloadUrl = url("/device-config/{$task->id}?token={$downloadToken}");
        $task->update([
            'parameters' => array_merge($task->parameters, [
                'url' => $downloadUrl,
            ]),
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        Log::info('Native config restore initiated', [
            'device_id' => $device->id,
            'task_id' => $task->id,
            'source_task_id' => $sourceTask->id,
            'source_file' => $sourceFile,
        ]);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
            'message' => 'Native config restore initiated - device will download and apply config file',
            'source_file_size' => $sourceTask->progress_info['file_size'] ?? 'unknown',
        ]);
    }

    /**
     * Get list of available native config files for a device
     */
    public function getNativeConfigFiles(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Find all completed upload tasks for this device
        $uploadTasks = Task::where('device_id', $device->id)
            ->where('task_type', 'upload')
            ->where('status', 'completed')
            ->whereNotNull('progress_info->uploaded_file')
            ->orderBy('created_at', 'desc')
            ->get();

        $configFiles = $uploadTasks->map(function ($task) {
            return [
                'task_id' => $task->id,
                'uploaded_at' => $task->progress_info['uploaded_at'] ?? $task->created_at->toIso8601String(),
                'file_size' => $task->progress_info['file_size'] ?? null,
                'file_type' => $task->parameters['file_type'] ?? 'unknown',
                'analysis' => $task->progress_info['analysis'] ?? null,
                'description' => $task->description,
            ];
        });

        return response()->json([
            'config_files' => $configFiles,
            'count' => $configFiles->count(),
        ]);
    }

    /**
     * Update backup metadata (tags, notes, starred)
     */
    public function updateBackupMetadata(Request $request, string $id, int $backupId): JsonResponse
    {
        $device = Device::findOrFail($id);
        $backup = $device->configBackups()->findOrFail($backupId);

        $validated = $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'notes' => 'nullable|string',
            'is_starred' => 'nullable|boolean',
        ]);

        $backup->update($validated);

        return response()->json([
            'message' => 'Backup metadata updated successfully',
            'backup' => [
                'id' => $backup->id,
                'name' => $backup->name,
                'tags' => $backup->tags,
                'notes' => $backup->notes,
                'is_starred' => $backup->is_starred,
            ],
        ]);
    }

    /**
     * Get all port mappings for a device
     */
    public function getPortMappings(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Detect manufacturer for device-specific WAN path handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        $isSmartRG = strtolower($device->manufacturer) === 'smartrg' ||
            strtoupper($device->oui) === 'E82C6D';

        if ($isCalix) {
            // Calix: Find the active WAN connection dynamically
            // Different Calix models use different WANIPConnection indices (e.g., 2, 14)
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common Calix path
                $wanDeviceIdx = 3;
                $wanConnDeviceIdx = 1;
                $wanConnIdx = 2;
            }
        } elseif ($isSmartRG) {
            // SmartRG: Find the active WAN connection dynamically
            // Look for WANIPConnection with ConnectionStatus=Connected and valid ExternalIPAddress
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common SmartRG path
                $wanDeviceIdx = 2;
                $wanConnDeviceIdx = 2;
                $wanConnIdx = 1;
            }
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanDeviceIdx = 1;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        if ($isDevice2) {
            // Device:2 model
            $portMappingPrefix = 'Device.NAT.PortMapping.';
        } else {
            // InternetGatewayDevice model
            $portMappingPrefix = "InternetGatewayDevice.WANDevice.{$wanDeviceIdx}.WANConnectionDevice.{$wanConnDeviceIdx}.WANIPConnection.{$wanConnIdx}.PortMapping.";
        }

        // For SmartRG, search ALL WAN interfaces for port mappings
        // This ensures we find port forwards regardless of which interface they're on
        if ($isSmartRG) {
            $portMappings = $device->parameters()
                ->where('name', 'LIKE', '%WANIPConnection%.PortMapping.%')
                ->where('name', 'NOT LIKE', '%NumberOfEntries')
                ->get()
                ->groupBy(function ($param) {
                    // Group by full path including WAN indices
                    if (preg_match('/(.*\.PortMapping\.\d+)\./', $param->name, $matches)) {
                        return $matches[1];
                    }
                    return null;
                })
                ->filter()
                ->map(function ($params, $prefix) {
                    // Extract WAN path info
                    preg_match('/WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\.PortMapping\.(\d+)/', $prefix, $matches);
                    $mapping = [
                        'instance' => (int) ($matches[4] ?? 0),
                        'wan_path' => "WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}",
                    ];
                    foreach ($params as $param) {
                        $parts = explode('.', $param->name);
                        $field = end($parts);
                        $mapping[$field] = $param->value;
                    }
                    return $mapping;
                })
                ->filter(function ($mapping) {
                    return isset($mapping['PortMappingEnabled']) &&
                        $mapping['PortMappingEnabled'] === '1';
                })
                ->values();
        } else {
            // Standard path-based lookup for other devices
            $portMappings = $device->parameters()
                ->where('name', 'LIKE', $portMappingPrefix . '%')
                ->get()
                ->groupBy(function ($param) use ($portMappingPrefix) {
                    $name = str_replace($portMappingPrefix, '', $param->name);
                    $parts = explode('.', $name);
                    return $parts[0];
                })
                ->map(function ($params, $instance) {
                    $mapping = ['instance' => (int) $instance];
                    foreach ($params as $param) {
                        $parts = explode('.', $param->name);
                        $field = end($parts);
                        $mapping[$field] = $param->value;
                    }
                    return $mapping;
                })
                ->filter(function ($mapping) use ($isDevice2) {
                    // TR-181 uses 'Enable', TR-098 uses 'PortMappingEnabled'
                    if ($isDevice2) {
                        return isset($mapping['Enable']) &&
                            ($mapping['Enable'] === '1' || $mapping['Enable'] === 'true');
                    }
                    return isset($mapping['PortMappingEnabled']) &&
                        $mapping['PortMappingEnabled'] === '1';
                })
                ->values();
        }

        return response()->json([
            'port_mappings' => $portMappings,
            'data_model' => $dataModel,
            'active_wan_path' => $isSmartRG ? "WANDevice.{$wanDeviceIdx}.WANConnectionDevice.{$wanConnDeviceIdx}.WANIPConnection.{$wanConnIdx}" : null,
        ]);
    }

    /**
     * Refresh port mappings from device
     * Creates tasks to:
     * 1. First get PortMappingNumberOfEntries to know how many exist
     * 2. Then use GetParameterNames to discover all PortMapping entries
     * 3. Finally retrieve their values
     */
    public function refreshPortMappings(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Detect data model and manufacturer for device-specific handling
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        $isSmartRG = strtolower($device->manufacturer) === 'smartrg' ||
            strtoupper(substr($device->oui ?? '', 0, 6)) === '3C9066' ||
            strtoupper($device->oui) === 'E82C6D';

        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        // Devices with Device. prefix parameters (Nokia Beacon G6) use Device.NAT.PortMapping
        // Note: Calix devices are TR-098, not TR-181
        if ($isDevice2) {
            // Clear existing TR-181 port mapping parameters
            $deletedCount = $device->parameters()
                ->where('name', 'LIKE', 'Device.NAT.PortMapping.%')
                ->where('name', 'NOT LIKE', '%NumberOfEntries')
                ->delete();

            Log::info('Cleared existing TR-181 port mapping parameters before refresh', [
                'device_id' => $device->id,
                'parameters_deleted' => $deletedCount,
            ]);

            // Task 1: Get the PortMappingNumberOfEntries
            $countTask = Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_params',
                'status' => 'pending',
                'parameters' => [
                    'names' => ['Device.NAT.PortMappingNumberOfEntries'],
                ],
            ]);

            // Task 2: Use GetParameterNames to discover all port mapping entries
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_parameter_names',
                'status' => 'pending',
                'parameters' => [
                    'path' => 'Device.NAT.PortMapping.',
                    'next_level' => false, // Get all sub-parameters recursively
                ],
            ]);

            // Send connection request to trigger immediate processing
            $this->connectionRequestService->sendConnectionRequest($device);

            return response()->json([
                'success' => true,
                'message' => 'Port mapping refresh tasks created for TR-181 device',
                'task' => [
                    'id' => $countTask->id,
                    'status' => $countTask->status,
                ],
                'data_model' => 'TR-181',
                'cleared_parameters' => $deletedCount,
            ]);
        }

        // TR-098 devices use InternetGatewayDevice path
        // Build list of WAN paths to check for SmartRG (they can have multiple WAN interfaces)
        $wanPaths = [];

        if ($isSmartRG) {
            // SmartRG: Check ALL WAN interfaces that have NAT enabled
            // Port forwards could be on any of them
            $natParams = $device->parameters()
                ->where('name', 'LIKE', '%WANIPConnection%.NATEnabled')
                ->where('value', '1')
                ->get();

            foreach ($natParams as $natParam) {
                if (preg_match('/(InternetGatewayDevice\.WANDevice\.\d+\.WANConnectionDevice\.\d+\.WANIPConnection\.\d+)\.NATEnabled/', $natParam->name, $matches)) {
                    $wanPaths[] = $matches[1];
                }
            }

            // If no NAT-enabled interfaces found, check connected ones
            if (empty($wanPaths)) {
                $activeWan = $this->findActiveWanConnection($device);
                if ($activeWan) {
                    $wanPaths[] = "InternetGatewayDevice.WANDevice.{$activeWan['wanDevice']}.WANConnectionDevice.{$activeWan['wanConnDevice']}.WANIPConnection.{$activeWan['wanConn']}";
                }
            }

            // Fallback to common SmartRG paths
            if (empty($wanPaths)) {
                $wanPaths = [
                    'InternetGatewayDevice.WANDevice.2.WANConnectionDevice.2.WANIPConnection.1',
                ];
            }
        } elseif ($isCalix) {
            $wanPaths = ['InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.2'];
        } else {
            $wanPaths = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1'];
        }

        // Clear existing port mapping parameters from database before refreshing
        // This ensures we get a clean slate and don't show stale data from deleted mappings
        if ($isSmartRG) {
            // For SmartRG, delete ALL port mapping parameters across all WAN interfaces
            $deletedCount = $device->parameters()
                ->where('name', 'LIKE', '%WANIPConnection%.PortMapping.%')
                ->where('name', 'NOT LIKE', '%NumberOfEntries')
                ->delete();
        } else {
            // For other devices, delete port mapping parameters for the specific WAN paths
            $deletedCount = 0;
            foreach ($wanPaths as $wanPath) {
                $deletedCount += $device->parameters()
                    ->where('name', 'LIKE', $wanPath . '.PortMapping.%')
                    ->where('name', 'NOT LIKE', '%NumberOfEntries')
                    ->delete();
            }
        }

        Log::info('Cleared existing port mapping parameters before refresh', [
            'device_id' => $device->id,
            'parameters_deleted' => $deletedCount,
        ]);

        // Build parameter names to query
        $paramsToGet = [];
        foreach ($wanPaths as $wanPath) {
            $paramsToGet[] = $wanPath . '.PortMappingNumberOfEntries';
        }

        // Task 1: Get the current PortMappingNumberOfEntries from all WAN interfaces
        $countTask = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'status' => 'pending',
            'parameters' => [
                'names' => $paramsToGet,
            ],
        ]);

        // Task 2: Use GetParameterNames to discover all port mapping entries
        // We'll query each WAN path's PortMapping subtree
        foreach ($wanPaths as $index => $wanPath) {
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_parameter_names',
                'status' => 'pending',
                'parameters' => [
                    'path' => $wanPath . '.PortMapping.',
                    'next_level' => false, // Get all sub-parameters recursively
                ],
            ]);
        }

        // Send connection request to trigger immediate processing
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'success' => true,
            'message' => 'Port mapping refresh tasks created - checking ' . count($wanPaths) . ' WAN interface(s)',
            'task' => [
                'id' => $countTask->id,
                'status' => $countTask->status,
            ],
            'wan_paths' => $wanPaths,
            'cleared_parameters' => $deletedCount,
        ]);
    }

    /**
     * Add a new port mapping
     */
    public function addPortMapping(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'protocol' => 'required|in:TCP,UDP,Both',
            'external_port' => 'required|integer|min:1|max:65535',
            'internal_port' => 'required|integer|min:1|max:65535',
            'internal_client' => 'required|ip',
        ]);

        // Detect manufacturer for device-specific WAN path handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        $isSmartRG = strtolower($device->manufacturer) === 'smartrg' ||
            strtoupper($device->oui) === 'E82C6D';

        if ($isCalix) {
            // Calix: Find the active WAN connection dynamically
            // Different Calix models use different WANIPConnection indices (e.g., 2, 14)
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common Calix path
                $wanDeviceIdx = 3;
                $wanConnDeviceIdx = 1;
                $wanConnIdx = 2;
            }
        } elseif ($isSmartRG) {
            // SmartRG: Find the active WAN connection dynamically
            // Look for WANIPConnection with ConnectionStatus=Connected and valid ExternalIPAddress
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common SmartRG path
                $wanDeviceIdx = 2;
                $wanConnDeviceIdx = 2;
                $wanConnIdx = 1;
            }
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanDeviceIdx = 1;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        if ($isDevice2) {
            // Device:2 model
            $portMappingPrefix = 'Device.NAT.PortMapping';
        } else {
            // InternetGatewayDevice model
            $portMappingPrefix = "InternetGatewayDevice.WANDevice.{$wanDeviceIdx}.WANConnectionDevice.{$wanConnDeviceIdx}.WANIPConnection.{$wanConnIdx}.PortMapping";
        }

        // Find next available instance number
        $existingInstances = $device->parameters()
            ->where('name', 'LIKE', $portMappingPrefix . '.%')
            ->get()
            ->map(function ($param) use ($portMappingPrefix) {
                $name = str_replace($portMappingPrefix . '.', '', $param->name);
                $parts = explode('.', $name);
                return (int) $parts[0];
            })
            ->unique()
            ->values()
            ->toArray();

        // Find first available instance (starting from 1)
        $instance = 1;
        while (in_array($instance, $existingInstances)) {
            $instance++;
        }

        // Detect Nokia/Alcatel-Lucent for specific handling
        // Nokia TR-098 devices need AddObject first (like SmartRG and TR-181)
        $isNokia = str_starts_with(strtoupper($device->oui ?? ''), '0C7C28') ||
            str_starts_with(strtoupper($device->oui ?? ''), '80AB4D') ||
            stripos($device->manufacturer ?? '', 'Nokia') !== false ||
            stripos($device->manufacturer ?? '', 'ALCL') !== false ||
            stripos($device->manufacturer ?? '', 'Alcatel') !== false;

        // Handle "Both" protocol by creating two separate port mappings (TCP and UDP)
        $protocols = $validated['protocol'] === 'Both' ? ['TCP', 'UDP'] : [$validated['protocol']];
        $tasks = [];

        foreach ($protocols as $protocol) {
            // Build parameters for the new port mapping
            // Use {instance} placeholder for devices that use AddObject (TR-181, SmartRG, Nokia)
            // The placeholder will be replaced with the actual instance number after AddObject returns
            $instancePlaceholder = ($isDevice2 || $isSmartRG || $isNokia || $isCalix) ? '{instance}' : $instance;

            // TR-181 uses different parameter names than TR-098
            // TR-181: Enable, Description, Protocol
            // TR-098: PortMappingEnabled, PortMappingDescription, PortMappingProtocol

            if ($isDevice2) {
                // TR-181 (Device:2) parameter names
                $parameters = [
                    "{$portMappingPrefix}.{$instancePlaceholder}.Enable" => [
                        'value' => true,
                        'type' => 'xsd:boolean',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.Description" => [
                        'value' => $validated['description'] . ($validated['protocol'] === 'Both' ? " ({$protocol})" : ''),
                        'type' => 'xsd:string',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.Protocol" => [
                        'value' => $protocol,
                        'type' => 'xsd:string',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.ExternalPort" => [
                        'value' => $validated['external_port'],
                        'type' => 'xsd:unsignedInt',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.InternalPort" => [
                        'value' => $validated['internal_port'],
                        'type' => 'xsd:unsignedInt',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.InternalClient" => [
                        'value' => $validated['internal_client'],
                        'type' => 'xsd:string',
                    ],
                ];

                // Set Interface to WAN interface for TR-181 devices
                // Nokia Beacon G6: Device.IP.Interface.2 was sent successfully at 21:47:10
                // (Status=0 returned, port forward visible in device GUI)
                $parameters["{$portMappingPrefix}.{$instancePlaceholder}.Interface"] = [
                    'value' => 'Device.IP.Interface.2',  // WAN interface
                    'type' => 'xsd:string',
                ];
            } else {
                // TR-098 (InternetGatewayDevice) parameter names
                $parameters = [
                    "{$portMappingPrefix}.{$instancePlaceholder}.PortMappingEnabled" => [
                        'value' => true,
                        'type' => 'xsd:boolean',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.PortMappingDescription" => [
                        'value' => $validated['description'] . ($validated['protocol'] === 'Both' ? " ({$protocol})" : ''),
                        'type' => 'xsd:string',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.PortMappingProtocol" => [
                        'value' => $protocol,
                        'type' => 'xsd:string',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.ExternalPort" => [
                        'value' => $validated['external_port'],
                        'type' => 'xsd:unsignedInt',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.InternalPort" => [
                        'value' => $validated['internal_port'],
                        'type' => 'xsd:unsignedInt',
                    ],
                    "{$portMappingPrefix}.{$instancePlaceholder}.InternalClient" => [
                        'value' => $validated['internal_client'],
                        'type' => 'xsd:string',
                    ],
                ];
            }

            // Add ExternalPortEndRange only for non-SmartRG devices (SmartRG doesn't support it)
            if (!$isSmartRG && !$isDevice2) {
                $parameters["{$portMappingPrefix}.{$instancePlaceholder}.ExternalPortEndRange"] = [
                    'value' => $validated['external_port'],
                    'type' => 'xsd:unsignedInt',
                ];
            }

            // TR-181 devices, SmartRG, Nokia, and Calix require AddObject first to create the instance
            // The device allocates the instance number, then we set parameters
            if ($isDevice2 || $isSmartRG || $isNokia || $isCalix) {
                $task = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'add_object',
                    'description' => "Create port mapping instance ({$protocol})",
                    'status' => 'pending',
                    'parameters' => [
                        'object_name' => "{$portMappingPrefix}.",
                        'follow_up_parameters' => $parameters,
                    ],
                ]);
            } else {
                // TR-098 devices (non-SmartRG, non-Nokia) - directly set parameters
                $task = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'description' => "Add port mapping ({$protocol})",
                    'status' => 'pending',
                    'parameters' => $parameters,
                ]);
            }

            $tasks[] = $task;

            // Increment instance for TR-098 devices that don't use AddObject when creating both TCP and UDP
            if (!$isDevice2 && !$isSmartRG && !$isNokia && !$isCalix) {
                $instance++;
            }
        }

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $tasks[0], // Return first task for backwards compatibility
            'tasks' => $tasks,
            'message' => count($tasks) > 1 ? 'Port mapping creation initiated (TCP and UDP)' : 'Port mapping creation initiated',
            'instance' => ($isDevice2 || $isSmartRG || $isNokia || $isCalix) ? 'pending' : ($instance - count($tasks) + 1),
        ]);
    }

    /**
     * Delete a port mapping
     */
    public function deletePortMapping(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'instance' => 'required|integer|min:1',
        ]);

        // Detect manufacturer for device-specific WAN path handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        $isSmartRG = strtolower($device->manufacturer) === 'smartrg' ||
            strtoupper($device->oui) === 'E82C6D';

        if ($isCalix) {
            // Calix: Find the active WAN connection dynamically
            // Different Calix models use different WANIPConnection indices (e.g., 2, 14)
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common Calix path
                $wanDeviceIdx = 3;
                $wanConnDeviceIdx = 1;
                $wanConnIdx = 2;
            }
        } elseif ($isSmartRG) {
            // SmartRG: Find the active WAN connection dynamically
            // Look for WANIPConnection with ConnectionStatus=Connected and valid ExternalIPAddress
            $activeWan = $this->findActiveWanConnection($device);
            if ($activeWan) {
                $wanDeviceIdx = $activeWan['wanDevice'];
                $wanConnDeviceIdx = $activeWan['wanConnDevice'];
                $wanConnIdx = $activeWan['wanConn'];
            } else {
                // Fallback to common SmartRG path
                $wanDeviceIdx = 2;
                $wanConnDeviceIdx = 2;
                $wanConnIdx = 1;
            }
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanDeviceIdx = 1;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        if ($isDevice2) {
            // Device:2 model
            $objectName = "Device.NAT.PortMapping.{$validated['instance']}.";
        } else {
            // InternetGatewayDevice model
            $objectName = "InternetGatewayDevice.WANDevice.{$wanDeviceIdx}.WANConnectionDevice.{$wanConnDeviceIdx}.WANIPConnection.{$wanConnIdx}.PortMapping.{$validated['instance']}.";
        }

        // Create task to delete the port mapping
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'delete_object',
            'description' => 'Delete port mapping',
            'status' => 'pending',
            'parameters' => [
                'object_name' => $objectName,
            ],
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $task,
            'message' => 'Port mapping deletion initiated',
        ]);
    }

    /**
     * Get list of connected devices on the LAN
     */
    public function getConnectedDevices(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $connectedDevices = [];

        // Try to get LAN hosts from device parameters
        // Different data models use different paths
        $hostPaths = [
            'Device.Hosts.Host.',  // TR-181
            'InternetGatewayDevice.LANDevice.1.Hosts.Host.',  // TR-098
        ];

        foreach ($hostPaths as $basePath) {
            $hosts = $device->parameters()
                ->where('name', 'LIKE', $basePath . '%')
                ->get();

            if ($hosts->isEmpty()) {
                continue;
            }

            // Group parameters by host instance
            $hostInstances = [];
            foreach ($hosts as $param) {
                // Extract host instance number (e.g., "Host.1." from "Device.Hosts.Host.1.IPAddress")
                if (preg_match('/Host\.(\d+)\./', $param->name, $matches)) {
                    $instanceNum = $matches[1];
                    if (!isset($hostInstances[$instanceNum])) {
                        $hostInstances[$instanceNum] = [];
                    }
                    // Extract parameter name after instance (e.g., "IPAddress" from "Device.Hosts.Host.1.IPAddress")
                    $paramName = substr($param->name, strrpos($param->name, '.') + 1);
                    $hostInstances[$instanceNum][$paramName] = $param->value;
                }
            }

            // Build connected devices list
            foreach ($hostInstances as $hostData) {
                $ipAddress = $hostData['IPAddress'] ?? null;
                $macAddress = $hostData['MACAddress'] ?? $hostData['PhysAddress'] ?? null;
                $hostname = $hostData['HostName'] ?? null;
                // Check for active: supports 'true', '1', true, 1
                $activeValue = $hostData['Active'] ?? 'false';
                $active = in_array($activeValue, ['true', '1', 1, true], true) || $activeValue === '1';

                // Only include active devices with valid IP addresses
                if ($active && $ipAddress && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $connectedDevices[] = [
                        'ip' => $ipAddress,
                        'mac' => $macAddress,
                        'hostname' => $hostname ?: 'Unknown Device',
                        'label' => ($hostname ?: 'Unknown Device') . ' (' . $ipAddress . ')',
                    ];
                }
            }

            // If we found hosts, no need to check other paths
            if (!empty($connectedDevices)) {
                break;
            }
        }

        // Sort by IP address
        usort($connectedDevices, function ($a, $b) {
            return ip2long($a['ip']) - ip2long($b['ip']);
        });

        return response()->json([
            'connected_devices' => $connectedDevices,
            'count' => count($connectedDevices),
        ]);
    }

    /**
     * Start WiFi interference scan (neighboring networks scan)
     */
    public function startWiFiScan(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $diagnosticParam = $this->getWiFiDiagnosticParameterPath($device, 'state');

        // Create task to trigger the scan
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'wifi_scan',
            'description' => 'WiFi Interference Scan',
            'status' => 'pending',
            'parameters' => [
                $diagnosticParam => [
                    'value' => 'Requested',
                    'type' => 'xsd:string',
                ],
            ],
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $task,
            'message' => 'WiFi scan initiated',
        ]);
    }

    /**
     * Get WiFi interference scan results
     */
    public function getWiFiScanResults(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $stateParam = $this->getWiFiDiagnosticParameterPath($device, 'state');
        $resultPrefix = $this->getWiFiDiagnosticParameterPath($device, 'result');

        // Get diagnostic state
        $state = $device->parameters()
            ->where('name', $stateParam)
            ->value('value');

        // Get all scan results
        $scanResults = $device->parameters()
            ->where('name', 'LIKE', $resultPrefix . '%')
            ->get()
            ->groupBy(function ($param) use ($resultPrefix) {
                // Extract instance number from parameter name
                $name = str_replace($resultPrefix, '', $param->name);
                $parts = explode('.', $name);
                return $parts[0]; // Instance number
            })
            ->map(function ($params, $instance) {
                $result = ['instance' => (int) $instance];
                foreach ($params as $param) {
                    $parts = explode('.', $param->name);
                    $field = end($parts);
                    $result[$field] = $param->value;
                }
                return $result;
            })
            ->values()
            ->sortByDesc(function ($result) {
                // Sort by signal strength (strongest first)
                return (int) ($result['SignalStrength'] ?? -999);
            })
            ->values();

        return response()->json([
            'state' => $state,
            'results' => $scanResults,
            'count' => $scanResults->count(),
        ]);
    }

    /**
     * Get the WiFi diagnostic parameter path for a device based on data model and manufacturer
     * Supports vendor-specific extensions for Alcatel-Lucent and Calix devices
     */
    private function getWiFiDiagnosticParameterPath(Device $device, string $type): string
    {
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        if ($isDevice2) {
            // Device:2 model - standard TR-181 WiFi diagnostics
            return $type === 'state'
                ? 'Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'Device.WiFi.NeighboringWiFiDiagnostic.Result.';
        }

        // InternetGatewayDevice (TR-098) model - check for vendor-specific extensions

        // Alcatel-Lucent / Nokia devices (e.g., XS-2426X-A)
        if (in_array($device->manufacturer, ['ALCL', 'Nokia', 'Alcatel-Lucent'])) {
            return $type === 'state'
                ? 'InternetGatewayDevice.X_ALU-COM_NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'InternetGatewayDevice.X_ALU-COM_NeighboringWiFiDiagnostic.Result.';
        }

        // Calix devices (GigaSpire, GigaCenter, etc.)
        if ($device->oui === '000631' || stripos($device->manufacturer, 'Calix') !== false) {
            return $type === 'state'
                ? 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.Result.';
        }

        // Default to standard TR-098 WiFi diagnostics (if device supports it)
        // Note: Standard TR-098 doesn't have WiFi diagnostics, so this may not work for all devices
        return $type === 'state'
            ? 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Stats'
            : 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Stats.';
    }

    /**
     * Start SpeedTest - uses TR-143 diagnostics where available, falls back to Download RPC
     */
    public function startSpeedTest(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'test_type' => 'required|in:download,upload,both',
            'download_url' => 'nullable|url',
            'upload_url' => 'nullable|url',
        ]);

        $testType = $validated['test_type'];

        // Check if device supports TR-143 diagnostics
        // Calix devices don't expose DownloadDiagnostics/UploadDiagnostics via TR-069
        $isCalix = $device->isCalix();

        // Use Download RPC method for Calix (no TR-143 support)
        if ($isCalix) {
            return $this->startDownloadRpcSpeedTest($device, $testType);
        }

        // Use Hay's TR-143 speedtest server
        $downloadUrl = $validated['download_url'] ?? 'http://tr143.hay.net/download.zip';
        $uploadUrl = $validated['upload_url'] ?? 'http://tr143.hay.net/handler.php';

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        // Detect SmartRG for combined parameter approach
        $isSmartRG = $device->isSmartRG();

        $tasks = [];

        // Download Test
        if ($testType === 'download' || $testType === 'both') {
            $downloadPrefix = $isDevice2
                ? 'Device.IP.Diagnostics.DownloadDiagnostics'
                : 'InternetGatewayDevice.DownloadDiagnostics';

            if ($isSmartRG) {
                // SmartRG: USS pattern - config params first, then trigger (REQUIRES TimeBasedTestDuration!)
                // Task 1: Set NumberOfConnections
                $configTask1 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$downloadPrefix}.NumberOfConnections" => [
                            'value' => '2',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);
                $tasks[] = $configTask1;

                // Task 2: Set TimeBasedTestDuration (CRITICAL - USS sets this for download too!)
                $configTask2 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$downloadPrefix}.TimeBasedTestDuration" => [
                            'value' => '12',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);
                $tasks[] = $configTask2;

                // Task 3: Set DiagnosticsState + DownloadURL to trigger test
                $downloadTask = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'download_diagnostics',
                    'description' => 'Speed test (download)',
                    'status' => 'pending',
                    'parameters' => [
                        "{$downloadPrefix}.DiagnosticsState" => [
                            'value' => 'Requested',
                            'type' => 'xsd:string',
                        ],
                        "{$downloadPrefix}.DownloadURL" => [
                            'value' => $downloadUrl,
                            'type' => 'xsd:string',
                        ],
                    ],
                ]);
                $tasks[] = $downloadTask;
            } else {
                // Standard devices: Separate config and trigger tasks
                $configTask = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$downloadPrefix}.NumberOfConnections" => [
                            'value' => '2',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);

                $tasks[] = $configTask;

                // Then set DiagnosticsState + URL to trigger the test
                $downloadParams = [
                    "{$downloadPrefix}.DiagnosticsState" => [
                        'value' => 'Requested',
                        'type' => 'xsd:string',
                    ],
                    "{$downloadPrefix}.DownloadURL" => [
                        'value' => $downloadUrl,
                        'type' => 'xsd:string',
                    ],
                ];

                $downloadTask = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'download_diagnostics',
                    'description' => 'Speed test (download)',
                    'status' => 'pending',
                    'parameters' => $downloadParams,
                ]);

                $tasks[] = $downloadTask;
            }
        }

        // For "both" tests, store upload config in download task metadata
        // Upload will be queued automatically when download completes
        if ($testType === 'both' && isset($downloadTask)) {
            $downloadTask->progress_info = [
                'queue_upload_after' => true,
                'upload_url' => $uploadUrl,
                'is_device2' => $isDevice2,
                'is_smartrg' => $isSmartRG,
            ];
            $downloadTask->save();
        }

        // Upload Test (only queue immediately if user selected "upload" only, not "both")
        if ($testType === 'upload') {
            $uploadPrefix = $isDevice2
                ? 'Device.IP.Diagnostics.UploadDiagnostics'
                : 'InternetGatewayDevice.UploadDiagnostics';

            if ($isSmartRG) {
                // SmartRG: USS pattern - config params first (separately), then trigger params together
                // Task 1: Set NumberOfConnections
                $configTask1 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$uploadPrefix}.NumberOfConnections" => [
                            'value' => '2',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);
                $tasks[] = $configTask1;

                // Task 2: Set TimeBasedTestDuration
                $configTask2 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$uploadPrefix}.TimeBasedTestDuration" => [
                            'value' => '12',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);
                $tasks[] = $configTask2;

                // Task 3: Set DiagnosticsState + UploadURL + TestFileLength together to trigger test
                $uploadTask = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'upload_diagnostics',
                    'description' => 'Speed test (upload)',
                    'status' => 'pending',
                    'parameters' => [
                        "{$uploadPrefix}.DiagnosticsState" => [
                            'value' => 'Requested',
                            'type' => 'xsd:string',
                        ],
                        "{$uploadPrefix}.UploadURL" => [
                            'value' => $uploadUrl,
                            'type' => 'xsd:string',
                        ],
                        "{$uploadPrefix}.TestFileLength" => [
                            'value' => '1858291200',  // ~1.7GB (USS default from trace)
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);
                $tasks[] = $uploadTask;
            } else {
                // Standard devices: Separate config and trigger tasks
                // USS uses 2 connections for upload, not 10
                $configTask1 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$uploadPrefix}.NumberOfConnections" => [
                            'value' => '2',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);

                $tasks[] = $configTask1;

                // Then set TimeBasedTestDuration
                $configTask2 = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'parameters' => [
                        "{$uploadPrefix}.TimeBasedTestDuration" => [
                            'value' => '12',
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);

                $tasks[] = $configTask2;

                // Finally set DiagnosticsState + URL + TestFileLength to trigger the test
                $uploadParams = [
                    "{$uploadPrefix}.DiagnosticsState" => [
                        'value' => 'Requested',
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.UploadURL" => [
                        'value' => $uploadUrl,
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.TestFileLength" => [
                        'value' => '1858291200',  // ~1.7GB (USS default from trace)
                        'type' => 'xsd:unsignedInt',
                    ],
                ];

                $uploadTask = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'upload_diagnostics',
                    'description' => 'Speed test (upload)',
                    'status' => 'pending',
                    'parameters' => $uploadParams,
                ]);

                $tasks[] = $uploadTask;
            }
        }

        // Trigger connection request
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'tasks' => $tasks,
            'message' => 'SpeedTest initiated',
            'test_type' => $testType,
        ]);
    }

    /**
     * Start speed test using Download RPC for devices without TR-143 support (e.g., Calix)
     * Download: Downloads a test file to the device and measures transfer time.
     * Note: Calix devices do not support Upload RPC, so only download testing is available.
     */
    private function startDownloadRpcSpeedTest(Device $device, string $testType): JsonResponse
    {
        // Download RPC speed test - uses a 25MB test file for good timing resolution
        $fileSize = 26214400; // 25MB
        $downloadUrl = 'http://hayacs.hay.net/device-config/25MB.bin';

        $tasks = [];

        if ($testType === 'download' || $testType === 'both') {
            $task = Task::create([
                'device_id' => $device->id,
                'task_type' => 'download',
                'description' => 'Speed test - 25MB download',
                'status' => 'pending',
                'parameters' => [
                    'url' => $downloadUrl,
                    'file_type' => '2 Web Content',  // Changed from '3 Vendor Log File' - that type is for uploads FROM device
                    'file_size' => $fileSize,
                    'username' => '',
                    'password' => '',
                    'description' => 'Speed test - 25MB download',
                ],
            ]);
            $tasks[] = $task;
        }

        // Note: Calix devices don't support Upload RPC - they ignore the command
        // Upload speed testing is not available for these devices
        if ($testType === 'upload') {
            return response()->json([
                'error' => 'Upload speed test not supported for Calix devices',
                'message' => 'Calix devices do not support the TR-069 Upload RPC. Only download speed testing is available.',
            ], 400);
        }

        // Trigger connection request
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'tasks' => $tasks,
            'message' => 'Speed test initiated (Download RPC method)',
            'test_type' => 'download',
            'method' => 'download_rpc',
            'note' => 'Calix devices only support download speed testing (no Upload RPC support)',
        ]);
    }

    /**
     * Get SpeedTest status and results
     */
    public function getSpeedTestStatus(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Check if this is a Calix device (uses Download RPC method)
        if ($device->isCalix()) {
            return $this->getDownloadRpcSpeedTestStatus($device);
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        // Get download diagnostic parameters
        $downloadPrefix = $isDevice2
            ? 'Device.IP.Diagnostics.DownloadDiagnostics.'
            : 'InternetGatewayDevice.DownloadDiagnostics.';

        $downloadParams = $device->parameters()
            ->where('name', 'LIKE', $downloadPrefix . '%')
            ->get()
            ->mapWithKeys(function ($param) use ($downloadPrefix) {
                $field = str_replace($downloadPrefix, '', $param->name);
                return [$field => $param->value];
            });

        // Get upload diagnostic parameters
        $uploadPrefix = $isDevice2
            ? 'Device.IP.Diagnostics.UploadDiagnostics.'
            : 'InternetGatewayDevice.UploadDiagnostics.';

        $uploadParams = $device->parameters()
            ->where('name', 'LIKE', $uploadPrefix . '%')
            ->get()
            ->mapWithKeys(function ($param) use ($uploadPrefix) {
                $field = str_replace($uploadPrefix, '', $param->name);
                return [$field => $param->value];
            });

        // Calculate speeds in bps for UI display
        $downloadSpeedBps = null;
        if (isset($downloadParams['TestBytesReceived'], $downloadParams['BOMTime'], $downloadParams['EOMTime'])) {
            $kbps = $this->calculateSpeed($downloadParams['TestBytesReceived'], $downloadParams['BOMTime'], $downloadParams['EOMTime']);
            $downloadSpeedBps = $kbps ? $kbps * 1000 : null; // Convert kbps to bps
        }

        $uploadSpeedBps = null;
        if (isset($uploadParams['TotalBytesSent'], $uploadParams['BOMTime'], $uploadParams['EOMTime'])) {
            $kbps = $this->calculateSpeed($uploadParams['TotalBytesSent'], $uploadParams['BOMTime'], $uploadParams['EOMTime']);
            $uploadSpeedBps = $kbps ? $kbps * 1000 : null; // Convert kbps to bps
        }

        // Determine overall state for UI
        $downloadState = $downloadParams['DiagnosticsState'] ?? null;
        $uploadState = $uploadParams['DiagnosticsState'] ?? null;

        // Check for pending tasks to determine if test is in progress
        $pendingTasks = $device->tasks()
            ->whereIn('task_type', ['download_diagnostics', 'upload_diagnostics'])
            ->whereIn('status', ['pending', 'sent'])
            ->exists();

        $state = null;
        if ($pendingTasks) {
            $state = 'InProgress';
        } elseif ($downloadState === 'Completed' && $uploadState === 'Completed') {
            $state = 'Complete';
        } elseif ($downloadState === 'Completed' && $uploadState !== 'Completed') {
            $state = 'InProgress'; // Download done, upload in progress
        } elseif ($downloadState === 'Error' || $uploadState === 'Error') {
            $state = 'Error';
        } elseif ($downloadState === 'Requested' || $uploadState === 'Requested') {
            $state = 'Requested';
        } elseif ($downloadState === 'Completed' || $uploadState === 'Completed') {
            $state = 'Complete'; // At least one test completed
        }

        // Save results to history when test completes (both download and upload done)
        $testCompletedAt = null;
        if ($state === 'Complete' && ($downloadSpeedBps || $uploadSpeedBps)) {
            // Check if we already saved this result by looking at the EOM time
            $downloadEndTime = isset($downloadParams['EOMTime']) ? \Carbon\Carbon::parse($downloadParams['EOMTime']) : null;
            $uploadEndTime = isset($uploadParams['EOMTime']) ? \Carbon\Carbon::parse($uploadParams['EOMTime']) : null;
            $testCompletedAt = $uploadEndTime ?? $downloadEndTime;

            if ($testCompletedAt) {
                // Only save if we don't already have a result with this exact timestamp
                $existingResult = SpeedTestResult::where('device_id', $device->id)
                    ->where(function ($q) use ($downloadEndTime, $uploadEndTime) {
                        if ($downloadEndTime) {
                            $q->where('download_end_time', $downloadEndTime);
                        }
                        if ($uploadEndTime) {
                            $q->orWhere('upload_end_time', $uploadEndTime);
                        }
                    })
                    ->first();

                if (!$existingResult) {
                    $downloadStartTime = isset($downloadParams['BOMTime']) ? \Carbon\Carbon::parse($downloadParams['BOMTime']) : null;
                    $uploadStartTime = isset($uploadParams['BOMTime']) ? \Carbon\Carbon::parse($uploadParams['BOMTime']) : null;

                    SpeedTestResult::create([
                        'device_id' => $device->id,
                        'download_speed_mbps' => $downloadSpeedBps ? round($downloadSpeedBps / 1000000, 2) : null,
                        'upload_speed_mbps' => $uploadSpeedBps ? round($uploadSpeedBps / 1000000, 2) : null,
                        'download_bytes' => $downloadParams['TestBytesReceived'] ?? null,
                        'upload_bytes' => $uploadParams['TotalBytesSent'] ?? null,
                        'download_duration_ms' => ($downloadStartTime && $downloadEndTime)
                            ? $downloadEndTime->diffInMilliseconds($downloadStartTime) : null,
                        'upload_duration_ms' => ($uploadStartTime && $uploadEndTime)
                            ? $uploadEndTime->diffInMilliseconds($uploadStartTime) : null,
                        'download_state' => $downloadState,
                        'upload_state' => $uploadState,
                        'download_start_time' => $downloadStartTime,
                        'download_end_time' => $downloadEndTime,
                        'upload_start_time' => $uploadStartTime,
                        'upload_end_time' => $uploadEndTime,
                        'test_type' => 'both',
                    ]);
                }
            }
        }

        // Get the completion time for display
        // SmartRG/Sagemcom TR-098 devices report EOMTime with 'Z' suffix but time is actually local (bug)
        // Nokia devices (both TR-098 and TR-181) report actual UTC time with 'Z' suffix (correct)
        $completedAt = null;
        if ($state === 'Complete') {
            $downloadEndTime = isset($downloadParams['EOMTime']) ? $downloadParams['EOMTime'] : null;
            $uploadEndTime = isset($uploadParams['EOMTime']) ? $uploadParams['EOMTime'] : null;
            $endTimeStr = $uploadEndTime ?? $downloadEndTime;
            if ($endTimeStr) {
                // Check if this is a SmartRG/Sagemcom device (they lie about the Z suffix)
                $isSmartRG = $device->isSmartRG();

                if ($isSmartRG && !$isDevice2) {
                    // SmartRG TR-098 devices: Z suffix is a lie, treat as local time
                    $timeWithoutTz = preg_replace('/[Z+-]\d{2}:\d{2}$/', '', $endTimeStr);
                    $timeWithoutTz = rtrim($timeWithoutTz, 'Z');
                    $endTime = \Carbon\Carbon::parse($timeWithoutTz, config('app.timezone'));
                } else {
                    // All other devices (Nokia, Calix, etc.): Z suffix means actual UTC
                    $endTime = \Carbon\Carbon::parse($endTimeStr)->setTimezone(config('app.timezone'));
                }
                $completedAt = $endTime->toIso8601String();
            }
        }

        return response()->json([
            'state' => $state,
            'completed_at' => $completedAt,
            'results' => [
                'download' => $downloadSpeedBps,
                'upload' => $uploadSpeedBps,
            ],
            // Also include detailed info for debugging/display
            'download' => [
                'state' => $downloadState,
                'rom_time' => $downloadParams['ROMTime'] ?? null,
                'bom_time' => $downloadParams['BOMTime'] ?? null,
                'eom_time' => $downloadParams['EOMTime'] ?? null,
                'test_bytes_received' => $downloadParams['TestBytesReceived'] ?? null,
                'total_bytes_received' => $downloadParams['TotalBytesReceived'] ?? null,
                'speed_bps' => $downloadSpeedBps,
            ],
            'upload' => [
                'state' => $uploadState,
                'rom_time' => $uploadParams['ROMTime'] ?? null,
                'bom_time' => $uploadParams['BOMTime'] ?? null,
                'eom_time' => $uploadParams['EOMTime'] ?? null,
                'test_bytes_sent' => $uploadParams['TestBytesSent'] ?? null,
                'total_bytes_sent' => $uploadParams['TotalBytesSent'] ?? null,
                'speed_bps' => $uploadSpeedBps,
            ],
        ]);
    }

    /**
     * Get Download RPC speed test status for Calix devices
     */
    private function getDownloadRpcSpeedTestStatus(Device $device): JsonResponse
    {
        // Look for the most recent download task with "Speed test" in the description
        $downloadTask = $device->tasks()
            ->where('task_type', 'download')
            ->where('description', 'LIKE', '%Speed test%')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$downloadTask) {
            return response()->json([
                'state' => null,
                'completed_at' => null,
                'results' => [
                    'download' => null,
                    'upload' => null,
                ],
                'method' => 'download_rpc',
            ]);
        }

        // Determine download state and speed
        $downloadState = null;
        $downloadSpeedBps = null;
        $downloadSpeedMbps = null;
        $downloadCompletedAt = null;

        $downloadState = match ($downloadTask->status) {
            'pending', 'sent' => 'InProgress',
            'completed' => 'Completed',
            'failed' => 'Error',
            default => null,
        };

        if ($downloadTask->status === 'completed' && isset($downloadTask->result['speed_mbps'])) {
            $downloadSpeedMbps = $downloadTask->result['speed_mbps'];
            $downloadSpeedBps = $downloadSpeedMbps * 1000000;
            $downloadCompletedAt = $downloadTask->completed_at;
        }

        // Determine overall state (download only for Calix - no upload support)
        $state = match ($downloadState) {
            'InProgress' => 'InProgress',
            'Completed' => 'Complete',
            'Error' => 'Error',
            default => null,
        };

        $completedAt = $downloadCompletedAt;

        // Save to SpeedTestResult history if test is complete and not already saved
        if ($state === 'Complete' && $downloadSpeedMbps) {
            $existingResult = SpeedTestResult::where('device_id', $device->id)
                ->where('created_at', '>=', $downloadTask->created_at)
                ->where('test_type', 'download_rpc')
                ->first();

            if (!$existingResult) {
                SpeedTestResult::create([
                    'device_id' => $device->id,
                    'download_speed_mbps' => $downloadSpeedMbps,
                    'upload_speed_mbps' => null, // Calix doesn't support Upload RPC
                    'download_bytes' => $downloadTask->parameters['file_size'] ?? null,
                    'upload_bytes' => null,
                    'download_duration_ms' => isset($downloadTask->result['transfer_duration_seconds'])
                        ? $downloadTask->result['transfer_duration_seconds'] * 1000 : null,
                    'upload_duration_ms' => null,
                    'download_state' => $downloadState,
                    'upload_state' => null,
                    'download_start_time' => isset($downloadTask->result['start_time'])
                        ? \Carbon\Carbon::parse($downloadTask->result['start_time']) : null,
                    'download_end_time' => isset($downloadTask->result['complete_time'])
                        ? \Carbon\Carbon::parse($downloadTask->result['complete_time']) : null,
                    'upload_start_time' => null,
                    'upload_end_time' => null,
                    'test_type' => 'download_rpc',
                ]);
            }
        }

        return response()->json([
            'state' => $state,
            'completed_at' => $completedAt?->toIso8601String(),
            'results' => [
                'download' => $downloadSpeedBps,
                'upload' => null, // Calix doesn't support Upload RPC
            ],
            'method' => 'download_rpc',
            'download_task_id' => $downloadTask->id,
            'note' => 'Calix devices only support download speed testing (no Upload RPC support)',
            // Include detailed info from task results
            'download' => [
                'state' => $downloadState,
                'start_time' => $downloadTask->result['start_time'] ?? null,
                'end_time' => $downloadTask->result['complete_time'] ?? null,
                'file_size' => $downloadTask->parameters['file_size'] ?? null,
                'transfer_duration_seconds' => $downloadTask->result['transfer_duration_seconds'] ?? null,
                'speed_mbps' => $downloadSpeedMbps,
                'speed_bps' => $downloadSpeedBps,
            ],
            'upload' => [
                'state' => null,
                'note' => 'Not supported on Calix devices',
            ],
        ]);
    }

    /**
     * Get historical SpeedTest results for a device
     */
    public function getSpeedTestHistory(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $results = $device->speedTestResults()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($result) {
                // Get the end time for display
                $endTime = $result->upload_end_time ?? $result->download_end_time;
                $completedAt = null;

                if ($endTime) {
                    // All device times were stored as UTC when parsed from the device's Z-suffixed timestamps
                    // Convert from UTC to local timezone for display
                    $completedAt = \Carbon\Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $endTime->format('Y-m-d H:i:s'),
                        'UTC'
                    )->setTimezone(config('app.timezone'))->toIso8601String();
                }

                return [
                    'id' => $result->id,
                    'download_mbps' => (float) $result->download_speed_mbps,
                    'upload_mbps' => (float) $result->upload_speed_mbps,
                    'download_bytes' => $result->download_bytes,
                    'upload_bytes' => $result->upload_bytes,
                    'download_duration_ms' => $result->download_duration_ms,
                    'upload_duration_ms' => $result->upload_duration_ms,
                    'test_type' => $result->test_type,
                    'completed_at' => $completedAt,
                    'created_at' => $result->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'device_id' => $device->id,
            'results' => $results,
            'count' => $results->count(),
        ]);
    }

    /**
     * Calculate speed in kbps from bytes and time
     */
    private function calculateSpeed(string $bytes, string $startTime, string $endTime): ?int
    {
        try {
            // Check for invalid/empty timing fields
            if (empty($bytes) || empty($startTime) || empty($endTime)) {
                return null;
            }

            // Check for placeholder dates (device hasn't run test yet)
            if (str_starts_with($startTime, '0001-01-01') || str_starts_with($endTime, '0001-01-01')) {
                return null;
            }

            $start = \Carbon\Carbon::parse($startTime);
            $end = \Carbon\Carbon::parse($endTime);
            $durationSeconds = abs($end->diffInSeconds($start, false)); // false = signed difference, then abs()

            if ($durationSeconds <= 0 || (int) $bytes <= 0) {
                return null;
            }

            $bytesPerSecond = (int) $bytes / $durationSeconds;
            $kbps = ($bytesPerSecond * 8) / 1000; // Convert bytes/s to kbps

            return (int) round($kbps);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ==================== BACKUP TEMPLATES ====================

    /**
     * Get all backup templates (optionally filtered)
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $query = BackupTemplate::with('sourceDevice:id,serial_number,model');

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by tags
        if ($request->has('tags') && is_array($request->tags)) {
            $query->where(function ($q) use ($request) {
                foreach ($request->tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        // Filter by device model
        if ($request->has('device_model')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('device_model_filter')
                  ->orWhere('device_model_filter', $request->device_model);
            });
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'templates' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'category' => $template->category,
                    'tags' => $template->tags,
                    'parameter_count' => $template->parameter_count,
                    'size' => $template->size,
                    'device_model_filter' => $template->device_model_filter,
                    'source_device' => $template->sourceDevice ? [
                        'id' => $template->sourceDevice->id,
                        'serial' => $template->sourceDevice->serial_number,
                        'model' => $template->sourceDevice->model,
                    ] : null,
                    'created_at' => $template->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Get a specific template
     */
    public function getTemplate(int $templateId): JsonResponse
    {
        $template = BackupTemplate::with('sourceDevice')->findOrFail($templateId);

        return response()->json([
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'tags' => $template->tags,
                'template_data' => $template->template_data,
                'parameter_patterns' => $template->parameter_patterns,
                'device_model_filter' => $template->device_model_filter,
                'parameter_count' => $template->parameter_count,
                'size' => $template->size,
                'source_device' => $template->sourceDevice,
                'created_at' => $template->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new template from a backup or device config
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|in:wifi,port_forwarding,general,security,diagnostics',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'source_type' => 'required|in:backup,device',
            'source_id' => 'required',
            'parameter_patterns' => 'nullable|array',
            'parameter_patterns.*' => 'string',
            'device_model_filter' => 'nullable|string|max:255',
            'strip_device_specific' => 'boolean',
        ]);

        $templateData = [];
        $sourceDeviceId = null;

        if ($validated['source_type'] === 'backup') {
            // Create template from existing backup
            $backup = ConfigBackup::findOrFail($validated['source_id']);
            $templateData = $backup->backup_data;
            $sourceDeviceId = $backup->device_id;
        } elseif ($validated['source_type'] === 'device') {
            // Create template from current device parameters
            $device = Device::findOrFail($validated['source_id']);
            $parameters = $device->parameters()->get()->mapWithKeys(function ($param) {
                return [$param->name => $param->value];
            })->toArray();
            $templateData = $parameters;
            $sourceDeviceId = $device->id;
        }

        // Filter parameters based on patterns if provided
        if (!empty($validated['parameter_patterns'])) {
            $filteredData = [];
            foreach ($validated['parameter_patterns'] as $pattern) {
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\\.'], $pattern) . '$/';
                foreach ($templateData as $key => $value) {
                    if (preg_match($regex, $key)) {
                        $filteredData[$key] = $value;
                    }
                }
            }
            $templateData = $filteredData;
        }

        // Strip device-specific values if requested
        if ($validated['strip_device_specific'] ?? true) {
            $templateData = $this->stripDeviceSpecificValues($templateData);
        }

        $template = BackupTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'tags' => $validated['tags'] ?? [],
            'template_data' => $templateData,
            'parameter_patterns' => $validated['parameter_patterns'] ?? null,
            'device_model_filter' => $validated['device_model_filter'] ?? null,
            'created_by_device_id' => $sourceDeviceId,
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'parameter_count' => $template->parameter_count,
            ],
        ], 201);
    }

    /**
     * Update a template
     */
    public function updateTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = BackupTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:wifi,port_forwarding,general,security,diagnostics',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'template_data' => 'nullable|array',
            'parameter_patterns' => 'nullable|array',
            'device_model_filter' => 'nullable|string|max:255',
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
            ],
        ]);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(int $templateId): JsonResponse
    {
        $template = BackupTemplate::findOrFail($templateId);
        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Apply a template to one or more devices
     */
    public function applyTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = BackupTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'string|exists:devices,id',
            'merge_strategy' => 'nullable|in:replace,merge',
            'create_backup' => 'boolean',
        ]);

        $mergeStrategy = $validated['merge_strategy'] ?? 'merge';
        $createBackup = $validated['create_backup'] ?? true;
        $results = [];

        foreach ($validated['device_ids'] as $deviceId) {
            try {
                $device = Device::findOrFail($deviceId);

                // Check device model compatibility
                if ($template->device_model_filter && $device->model !== $template->device_model_filter) {
                    $results[] = [
                        'device_id' => $deviceId,
                        'success' => false,
                        'error' => 'Device model mismatch',
                    ];
                    continue;
                }

                // Create pre-operation backup if requested
                if ($createBackup) {
                    $this->createPreOperationBackup($device, 'Template Application');
                }

                // Apply template parameters
                $parametersToSet = $template->template_data;

                // If merge strategy, only update parameters that exist in template
                // If replace strategy, we'd need to handle differently (not implemented for safety)

                $tasks = [];
                foreach ($parametersToSet as $paramName => $paramValue) {
                    // Create set_parameter_values task
                    $task = Task::create([
                        'device_id' => $device->id,
                        'task_type' => 'set_parameter_values',
                        'status' => 'pending',
                        'parameters' => [$paramName => $paramValue],
                    ]);
                    $tasks[] = $task->id;
                }

                $results[] = [
                    'device_id' => $deviceId,
                    'success' => true,
                    'tasks_created' => count($tasks),
                    'task_ids' => $tasks,
                ];

                Log::info('Template applied to device', [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'device_id' => $device->id,
                    'parameters_set' => count($parametersToSet),
                ]);
            } catch (\Exception $e) {
                $results[] = [
                    'device_id' => $deviceId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        return response()->json([
            'message' => "Template applied to {$successCount} device(s). {$failCount} failed.",
            'results' => $results,
        ]);
    }

    /**
     * Strip device-specific values from template data
     */
    private function stripDeviceSpecificValues(array $data): array
    {
        // List of parameters that should be stripped (device-specific identifiers)
        $stripPatterns = [
            '/SerialNumber$/i',
            '/MACAddress$/i',
            '/IPAddress$/i',
            '/ExternalIPAddress$/i',
            '/ConnectionRequestURL$/i',
            '/UUID$/i',
            '/HardwareVersion$/i',
            '/SoftwareVersion$/i',
            '/ProvisioningCode$/i',
        ];

        $filtered = [];
        foreach ($data as $key => $value) {
            $shouldStrip = false;
            foreach ($stripPatterns as $pattern) {
                if (preg_match($pattern, $key)) {
                    $shouldStrip = true;
                    break;
                }
            }
            if (!$shouldStrip) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Delete a device
     */
    public function destroy(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $device->delete();

        return response()->json([
            'message' => 'Device deleted successfully',
        ]);
    }

    // =========================================================================
    // TR-181 MIGRATION METHODS
    // =========================================================================

    /**
     * Check if a device is eligible for TR-181 migration
     */
    public function checkMigrationEligibility(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $migrationService = new Tr181MigrationService();

        $result = $migrationService->checkEligibility($device);

        // Add SSH WiFi config availability info
        $result['ssh_wifi_configs'] = $migrationService->checkSshWifiConfigsAvailable($device);

        // Add recommendation if SSH configs aren't available
        if (!$result['ssh_wifi_configs']['has_passwords'] && $result['eligible']) {
            $result['recommendations'] = $result['recommendations'] ?? [];
            $result['recommendations'][] = 'Extract WiFi config via SSH before migration to preserve WiFi passwords. TR-069 backups have masked passwords.';
        }

        return response()->json($result);
    }

    /**
     * Get migration statistics for all Beacon G6 devices
     */
    public function getMigrationStats(): JsonResponse
    {
        $migrationService = new Tr181MigrationService();
        $stats = $migrationService->getEligibleDeviceCount();

        return response()->json($stats);
    }

    /**
     * Start TR-181 migration for a device
     */
    public function startMigration(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $migrationService = new Tr181MigrationService();

        $skipBackup = $request->boolean('skip_backup', false);
        $result = $migrationService->createMigrationTasks($device, $skipBackup);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        // Send connection request to start migration immediately
        if ($device->online) {
            try {
                $this->connectionRequestService->sendConnectionRequest($device);
            } catch (\Exception $e) {
                Log::warning("Failed to send connection request for migration: " . $e->getMessage());
            }
        }

        return response()->json($result);
    }

    /**
     * Verify migration status for a device
     */
    public function verifyMigration(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $migrationService = new Tr181MigrationService();

        $result = $migrationService->verifyMigration($device);

        return response()->json($result);
    }

    /**
     * Create WiFi fallback tasks if migration lost WiFi settings
     * Prefers SSH-extracted configs (has plaintext passwords) over TR-069 backup (masked passwords)
     */
    public function createWifiFallback(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $migrationService = new Tr181MigrationService();

        // Check if we have SSH-extracted WiFi configs with passwords (preferred)
        $sshCheck = $migrationService->checkSshWifiConfigsAvailable($device);

        if ($sshCheck['has_passwords']) {
            // Use SSH-extracted configs (these have actual plaintext passwords)
            $result = $migrationService->createWifiFallbackFromSshConfigs($device);

            if ($result['success']) {
                // Send connection request to apply WiFi settings immediately
                if ($device->online) {
                    try {
                        $this->connectionRequestService->sendConnectionRequest($device);
                    } catch (\Exception $e) {
                        Log::warning("Failed to send connection request for WiFi fallback: " . $e->getMessage());
                    }
                }

                return response()->json(array_merge($result, [
                    'source' => 'ssh_extracted',
                    'note' => 'Used SSH-extracted WiFi configs with actual passwords',
                ]));
            }

            // If SSH method failed, fall through to backup method
            Log::info("SSH WiFi fallback failed, trying backup method: " . $result['message']);
        }

        // Fallback to TR-069 backup method (passwords may be masked)
        $backup = $device->configBackups()
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$backup) {
            return response()->json([
                'success' => false,
                'message' => 'No backup found and no SSH-extracted WiFi configs available',
            ], 400);
        }

        $result = $migrationService->createWifiFallbackTasks($device, $backup);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        // Send connection request to apply WiFi settings immediately
        if ($device->online) {
            try {
                $this->connectionRequestService->sendConnectionRequest($device);
            } catch (\Exception $e) {
                Log::warning("Failed to send connection request for WiFi fallback: " . $e->getMessage());
            }
        }

        return response()->json(array_merge($result, [
            'source' => 'tr069_backup',
            'warning' => 'Used TR-069 backup - WiFi passwords may be masked. Consider using SSH extraction for this device.',
        ]));
    }

    /**
     * Get list of devices eligible for migration
     */
    public function getEligibleDevices(): JsonResponse
    {
        $migrationService = new Tr181MigrationService();

        // Get all Beacon G6 devices
        $devices = Device::where(function ($query) {
            $query->whereIn('oui', Tr181MigrationService::NOKIA_OUIS)
                  ->orWhere('product_class', 'like', '%Beacon G6%')
                  ->orWhere('product_class', 'like', '%G-240W-F%');
        })->get();

        $results = [];
        foreach ($devices as $device) {
            $eligibility = $migrationService->checkEligibility($device);
            $results[] = [
                'id' => $device->id,
                'serial_number' => $device->serial_number,
                'product_class' => $device->product_class,
                'firmware' => $device->software_version,
                'data_model' => $device->getDataModel(),
                'online' => $device->online,
                'last_inform' => $device->last_inform?->toDateTimeString(),
                'eligible' => $eligibility['eligible'],
                'reasons' => $eligibility['reasons'],
                'warnings' => $eligibility['warnings'],
            ];
        }

        return response()->json([
            'devices' => $results,
            'total' => count($results),
            'eligible_count' => count(array_filter($results, fn($d) => $d['eligible'])),
        ]);
    }

    /**
     * Get the current standard WiFi configuration for a device
     * Returns the current SSID, password, and guest network status
     */
    public function getStandardWifiConfig(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $dataModel = $device->getDataModel();

        // Use centralized manufacturer detection from Device model
        $isNokia = $device->isNokia();
        $isCalix = $device->isCalix();

        // Get stored WiFi credentials (passwords not readable from device)
        $storedCredentials = DeviceWifiCredential::where('device_id', $device->id)->first();

        // Handle TR-098 Calix devices
        if ($dataModel === 'TR-098' && $isCalix) {
            // Calix TR-098 uses InternetGatewayDevice.LANDevice.1.WLANConfiguration.{i}
            // GigaSpire: 1-8 = 5GHz, 9-16 = 2.4GHz (INVERTED from GigaCenter)
            // GigaCenter: 1-8 = 2.4GHz, 9-16 = 5GHz
            $isGigaSpire = strtolower($device->product_class ?? '') === 'gigaspire';
            $prefix = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';

            if ($isGigaSpire) {
                // GigaSpire: 5GHz uses instances 1-8, 2.4GHz uses instances 9-16
                $inst24Primary = 9;   // Primary 2.4GHz (band-steered)
                $inst24Guest = 10;    // Guest 2.4GHz
                $inst24Dedicated = 12; // Dedicated 2.4GHz only
                $inst5Primary = 1;    // Primary 5GHz (band-steered)
                $inst5Guest = 2;      // Guest 5GHz
                $inst5Dedicated = 3;  // Dedicated 5GHz only
            } else {
                // GigaCenter: 2.4GHz uses instances 1-8, 5GHz uses instances 9-16
                $inst24Primary = 1;
                $inst24Guest = 2;
                $inst24Dedicated = 3;
                $inst5Primary = 9;
                $inst5Guest = 10;
                $inst5Dedicated = 12;
            }

            $mainSsid24 = $device->parameters()->where('name', "{$prefix}.{$inst24Primary}.SSID")->first();
            $mainSsid5 = $device->parameters()->where('name', "{$prefix}.{$inst5Primary}.SSID")->first();

            // Check if guest networks are enabled
            $guestEnabled24 = $device->parameters()->where('name', "{$prefix}.{$inst24Guest}.Enable")->first();
            $guestEnabled5 = $device->parameters()->where('name', "{$prefix}.{$inst5Guest}.Enable")->first();
            $guestEnabled = ($guestEnabled24 && $guestEnabled24->value === '1') ||
                            ($guestEnabled5 && $guestEnabled5->value === '1');

            // Get guest SSID (use 2.4GHz instance as primary guest SSID source)
            $guestSsid = $device->parameters()->where('name', "{$prefix}.{$inst24Guest}.SSID")->first();

            // Check if dedicated networks are enabled
            $dedicated24Enabled = $device->parameters()->where('name', "{$prefix}.{$inst24Dedicated}.Enable")->first();
            $dedicated5Enabled = $device->parameters()->where('name', "{$prefix}.{$inst5Dedicated}.Enable")->first();

            // Get dedicated SSIDs
            $dedicated24Ssid = $device->parameters()->where('name', "{$prefix}.{$inst24Dedicated}.SSID")->first();
            $dedicated5Ssid = $device->parameters()->where('name', "{$prefix}.{$inst5Dedicated}.SSID")->first();

            // Calix exposes passwords in clear text via X_000631_KeyPassphrase
            $mainPassword24 = $device->parameters()->where('name', "{$prefix}.{$inst24Primary}.PreSharedKey.1.X_000631_KeyPassphrase")->first();
            $guestPassword = $device->parameters()->where('name', "{$prefix}.{$inst24Guest}.PreSharedKey.1.X_000631_KeyPassphrase")->first();

            return response()->json([
                'ssid' => $mainSsid24?->value ?? '',
                'ssid_5ghz' => $mainSsid5?->value ?? '',
                // Calix provides password in clear text, fall back to stored credentials
                'password' => $mainPassword24?->value ?? $storedCredentials?->main_password ?? '',
                'guest_enabled' => $guestEnabled,
                'guest_ssid' => $guestSsid?->value ?? '',
                'guest_password' => $guestPassword?->value ?? $storedCredentials?->guest_password ?? '',
                // Dedicated networks use instance mapping based on device type
                'dedicated_24ghz_enabled' => $dedicated24Enabled && $dedicated24Enabled->value === '1',
                'dedicated_24ghz_ssid' => $dedicated24Ssid?->value ?? '',
                'dedicated_5ghz_enabled' => $dedicated5Enabled && $dedicated5Enabled->value === '1',
                'dedicated_5ghz_ssid' => $dedicated5Ssid?->value ?? '',
                'data_model' => $dataModel,
                'device_type' => $isGigaSpire ? 'GigaSpire' : 'GigaCenter',
                'credentials_stored' => $storedCredentials !== null,
                'credentials_set_by' => $storedCredentials?->set_by,
                'credentials_updated_at' => $storedCredentials?->updated_at?->toIso8601String(),
            ]);
        }

        // Handle TR-098 Nokia Beacon G6 devices
        if ($dataModel === 'TR-098' && $isNokia) {
            // TR-098 Nokia uses InternetGatewayDevice.LANDevice.1.WLANConfiguration.{i}
            // Instance 1 = Main 2.4GHz, Instance 5 = Main 5GHz (same SSID for band steering)
            // Instance 4 = Guest 2.4GHz, Instance 8 = Guest 5GHz
            $mainSsid = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID')->first();

            // Check if guest network is enabled (instance 4 for 2.4GHz, 8 for 5GHz)
            $guestEnabled24 = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.4.Enable')->first();
            $guestEnabled5 = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.8.Enable')->first();
            $guestEnabled = ($guestEnabled24 && strtolower($guestEnabled24->value) === 'true') ||
                            ($guestEnabled5 && strtolower($guestEnabled5->value) === 'true');

            // Get guest SSID
            $guestSsid = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.4.SSID')->first();

            // Check dedicated network status (instance 2 for dedicated 2.4GHz, 6 for dedicated 5GHz)
            $dedicated24Enabled = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.Enable')->first();
            $dedicated5Enabled = $device->parameters()->where('name', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.Enable')->first();

            return response()->json([
                'ssid' => $mainSsid?->value ?? '',
                'password' => $storedCredentials?->main_password ?? '',
                'guest_enabled' => $guestEnabled,
                'guest_ssid' => $guestSsid?->value ?? '',
                'guest_password' => $storedCredentials?->guest_password ?? '',
                'dedicated_24ghz_enabled' => $dedicated24Enabled && strtolower($dedicated24Enabled->value) === 'true',
                'dedicated_5ghz_enabled' => $dedicated5Enabled && strtolower($dedicated5Enabled->value) === 'true',
                'data_model' => $dataModel,
                'credentials_stored' => $storedCredentials !== null,
                'credentials_set_by' => $storedCredentials?->set_by,
                'credentials_updated_at' => $storedCredentials?->updated_at?->toIso8601String(),
            ]);
        }

        // For non-Nokia TR-098 devices, WiFi config not supported yet
        if ($dataModel !== 'TR-181') {
            return response()->json([
                'error' => 'Standard WiFi configuration is only supported for TR-181 devices and TR-098 Nokia Beacon G6',
                'data_model' => $dataModel,
            ], 400);
        }

        // TR-181 handling (existing code)
        // Get the main SSID (SSID.1 for 2.4GHz, SSID.5 for 5GHz - they should match)
        $mainSsid = $device->parameters()->where('name', 'Device.WiFi.SSID.1.SSID')->first();

        // Nokia uses AP.4/AP.8 for guest, others use AP.3/AP.7
        $guestAp24 = $isNokia ? 4 : 3;
        $guestAp5 = $isNokia ? 8 : 7;

        // Check if guest network is enabled from device params
        $guestEnabled24 = $device->parameters()->where('name', "Device.WiFi.AccessPoint.{$guestAp24}.Enable")->first();
        $guestEnabled5 = $device->parameters()->where('name', "Device.WiFi.AccessPoint.{$guestAp5}.Enable")->first();
        $guestEnabled = ($guestEnabled24 && $guestEnabled24->value === '1') ||
                        ($guestEnabled5 && $guestEnabled5->value === '1');

        // Get guest SSID from device params
        $guestSsid = $device->parameters()->where('name', "Device.WiFi.SSID.{$guestAp24}.SSID")->first();

        // Check dedicated network status
        $dedicated24Enabled = $device->parameters()->where('name', 'Device.WiFi.AccessPoint.2.Enable')->first();
        $dedicated5Enabled = $device->parameters()->where('name', 'Device.WiFi.AccessPoint.6.Enable')->first();

        return response()->json([
            'ssid' => $mainSsid?->value ?? '',
            // Passwords from stored credentials (device returns empty for security)
            'password' => $storedCredentials?->main_password ?? '',
            'guest_enabled' => $guestEnabled,
            'guest_ssid' => $guestSsid?->value ?? '',
            'guest_password' => $storedCredentials?->guest_password ?? '',
            'dedicated_24ghz_enabled' => $dedicated24Enabled?->value === '1',
            'dedicated_5ghz_enabled' => $dedicated5Enabled?->value === '1',
            'data_model' => $dataModel,
            // Credential metadata
            'credentials_stored' => $storedCredentials !== null,
            'credentials_set_by' => $storedCredentials?->set_by,
            'credentials_updated_at' => $storedCredentials?->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Apply standard WiFi configuration to a device
     * Sets up: Main network (band-steered), dedicated 2.4/5GHz networks, and optional guest network
     */
    public function applyStandardWifiConfig(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $dataModel = $device->getDataModel();

        // Detect Nokia and Calix devices using centralized detection
        $isNokia = $device->isNokia();
        $isCalix = $device->isCalix();

        // Check for supported device types
        if ($dataModel !== 'TR-181' && !($dataModel === 'TR-098' && ($isNokia || $isCalix))) {
            return response()->json([
                'error' => 'Standard WiFi configuration is only supported for TR-181 devices, TR-098 Nokia Beacon G6, and TR-098 Calix devices',
                'data_model' => $dataModel,
            ], 400);
        }

        $validated = $request->validate([
            'ssid' => 'required|string|min:1|max:32',
            'password' => 'required|string|min:8|max:63',
            'enable_guest' => 'nullable|boolean',
            'guest_password' => 'nullable|string|min:8|max:63',
        ]);

        $ssid = $validated['ssid'];
        $password = $validated['password'];
        $enableGuest = $validated['enable_guest'] ?? false;

        // Generate a memorable guest password - two words + three digit number
        // Format: "BlueSky472" - easy to say over the phone and remember
        $adjectives = ['Happy', 'Sunny', 'Blue', 'Green', 'Quick', 'Bright', 'Cool', 'Swift', 'Lucky', 'Warm',
                       'Golden', 'Silver', 'Brave', 'Calm', 'Fresh', 'Grand', 'Kind', 'Merry', 'Noble', 'Pure',
                       'Rapid', 'Smart', 'Sweet', 'True', 'Wild', 'Gentle', 'Jolly', 'Lively', 'Proud', 'Quiet'];
        $nouns = ['Sky', 'Star', 'Moon', 'Sun', 'Lake', 'River', 'Tree', 'Bird', 'Cloud', 'Wind',
                  'Snow', 'Rain', 'Fire', 'Wave', 'Stone', 'Leaf', 'Rose', 'Pine', 'Oak', 'Bear',
                  'Wolf', 'Hawk', 'Deer', 'Fox', 'Fish', 'Frog', 'Lion', 'Tiger', 'Eagle', 'Dove'];
        $adjective = $adjectives[random_int(0, count($adjectives) - 1)];
        $noun = $nouns[random_int(0, count($nouns) - 1)];
        $number = random_int(100, 999);
        $generatedGuestPassword = $adjective . $noun . $number;
        $guestPassword = $validated['guest_password'] ?? $generatedGuestPassword;

        // Handle TR-098 Nokia Beacon G6 devices
        if ($dataModel === 'TR-098' && $isNokia) {
            return $this->applyTr098NokiaWifiConfig($device, $ssid, $password, $enableGuest, $guestPassword, $validated);
        }

        // Handle TR-098 Calix devices
        if ($dataModel === 'TR-098' && $isCalix) {
            return $this->applyTr098CalixWifiConfig($device, $ssid, $password, $enableGuest, $guestPassword, $validated);
        }

        // Use WPA3-Personal-Transition for better security while maintaining backwards compatibility
        // WPA3-Personal-Transition allows both WPA3 and WPA2 clients to connect
        $securityMode = 'WPA3-Personal-Transition';

        // Build network configurations
        // Nokia devices use SSID.4/AP.4 and SSID.8/AP.8 for guest networks (compatible with Nokia GUI/App)
        // Other devices use SSID.3/AP.3 and SSID.7/AP.7 for guest networks
        if ($isNokia) {
            // Nokia "batch by radio" optimization:
            // Group all 2.4GHz APs (1,2,3,4) in one task and all 5GHz APs (5,6,7,8) in another.
            // This minimizes radio restarts - device should only restart each radio once.
            // AP mapping: 1=Main 2.4GHz, 2=Dedicated 2.4GHz, 3=Unused, 4=Guest 2.4GHz
            //             5=Main 5GHz,   6=Dedicated 5GHz,   7=Unused, 8=Guest 5GHz
            $networks = [
                'radio_24ghz' => [
                    'description' => "WiFi: Configure all 2.4GHz networks",
                    'params' => [
                        // AP.1 - Main 2.4GHz (band-steered SSID)
                        'Device.WiFi.SSID.1.SSID' => $ssid,
                        'Device.WiFi.AccessPoint.1.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.1.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.1.Security.KeyPassphrase' => $password,
                        // AP.2 - Dedicated 2.4GHz
                        'Device.WiFi.SSID.2.SSID' => $ssid . '-2.4GHz',
                        'Device.WiFi.AccessPoint.2.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.2.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.2.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.2.Security.KeyPassphrase' => $password,
                        // AP.3 - Unused (disabled)
                        'Device.WiFi.AccessPoint.3.Enable' => ['value' => false, 'type' => 'xsd:boolean'],
                        // AP.4 - Guest 2.4GHz (Nokia GUI compatible)
                        'Device.WiFi.SSID.4.SSID' => $ssid . '-Guest',
                        'Device.WiFi.AccessPoint.4.Enable' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.4.SSIDAdvertisementEnabled' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.4.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.4.Security.KeyPassphrase' => $guestPassword,
                        'Device.WiFi.AccessPoint.4.IsolationEnable' => ['value' => true, 'type' => 'xsd:boolean'],
                    ],
                ],
                'radio_5ghz' => [
                    'description' => "WiFi: Configure all 5GHz networks",
                    'params' => [
                        // AP.5 - Main 5GHz (band-steered SSID)
                        'Device.WiFi.SSID.5.SSID' => $ssid,
                        'Device.WiFi.AccessPoint.5.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.5.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.5.Security.KeyPassphrase' => $password,
                        // AP.6 - Dedicated 5GHz
                        'Device.WiFi.SSID.6.SSID' => $ssid . '-5GHz',
                        'Device.WiFi.AccessPoint.6.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.6.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.6.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.6.Security.KeyPassphrase' => $password,
                        // AP.7 - Unused (disabled)
                        'Device.WiFi.AccessPoint.7.Enable' => ['value' => false, 'type' => 'xsd:boolean'],
                        // AP.8 - Guest 5GHz (Nokia GUI compatible)
                        'Device.WiFi.SSID.8.SSID' => $ssid . '-Guest',
                        'Device.WiFi.AccessPoint.8.Enable' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.8.SSIDAdvertisementEnabled' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.8.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.8.Security.KeyPassphrase' => $guestPassword,
                        'Device.WiFi.AccessPoint.8.IsolationEnable' => ['value' => true, 'type' => 'xsd:boolean'],
                    ],
                ],
            ];
        } else {
            // Standard network mapping for non-Nokia devices:
            // AP 1/5: Main (band-steered), AP 2: Dedicated 2.4GHz, AP 6: Dedicated 5GHz
            // AP 3/7: Guest, AP 4/8: Disabled (unused)
            $networks = [
                'main' => [
                    'description' => "WiFi: Configure main network \"{$ssid}\"",
                    'params' => [
                        'Device.WiFi.SSID.1.SSID' => $ssid,
                        'Device.WiFi.AccessPoint.1.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.1.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.1.Security.KeyPassphrase' => $password,
                        'Device.WiFi.SSID.5.SSID' => $ssid,
                        'Device.WiFi.AccessPoint.5.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.5.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.5.Security.KeyPassphrase' => $password,
                    ],
                ],
                'dedicated_24ghz' => [
                    'description' => "WiFi: Configure 2.4GHz network \"{$ssid}-2.4GHz\"",
                    'params' => [
                        'Device.WiFi.SSID.2.SSID' => $ssid . '-2.4GHz',
                        'Device.WiFi.AccessPoint.2.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.2.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.2.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.2.Security.KeyPassphrase' => $password,
                    ],
                ],
                'dedicated_5ghz' => [
                    'description' => "WiFi: Configure 5GHz network \"{$ssid}-5GHz\"",
                    'params' => [
                        'Device.WiFi.SSID.6.SSID' => $ssid . '-5GHz',
                        'Device.WiFi.AccessPoint.6.Enable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.6.SSIDAdvertisementEnabled' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.6.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.6.Security.KeyPassphrase' => $password,
                    ],
                ],
                'guest' => [
                    'description' => "WiFi: Configure guest network \"{$ssid}-Guest\" (" . ($enableGuest ? 'enabled' : 'disabled') . ")",
                    'params' => [
                        'Device.WiFi.SSID.3.SSID' => $ssid . '-Guest',
                        'Device.WiFi.AccessPoint.3.Enable' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.3.SSIDAdvertisementEnabled' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.3.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.3.Security.KeyPassphrase' => $guestPassword,
                        'Device.WiFi.AccessPoint.3.IsolationEnable' => ['value' => true, 'type' => 'xsd:boolean'],
                        'Device.WiFi.SSID.7.SSID' => $ssid . '-Guest',
                        'Device.WiFi.AccessPoint.7.Enable' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.7.SSIDAdvertisementEnabled' => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.7.Security.ModeEnabled' => $securityMode,
                        'Device.WiFi.AccessPoint.7.Security.KeyPassphrase' => $guestPassword,
                        'Device.WiFi.AccessPoint.7.IsolationEnable' => ['value' => true, 'type' => 'xsd:boolean'],
                    ],
                ],
                'disable_unused' => [
                    'description' => 'WiFi: Disable unused networks',
                    'params' => [
                        'Device.WiFi.AccessPoint.4.Enable' => ['value' => false, 'type' => 'xsd:boolean'],
                        'Device.WiFi.AccessPoint.8.Enable' => ['value' => false, 'type' => 'xsd:boolean'],
                    ],
                ],
            ];
        }

        $tasks = [];
        $isOneTaskPerSession = $this->isOneTaskPerSessionDevice($device);

        if ($isNokia) {
            // Nokia devices: Create separate tasks for each network to avoid firmware stall
            // Tasks will be processed sequentially as the device checks in
            $isFirstTask = true;
            foreach ($networks as $networkKey => $network) {
                $taskData = [
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'description' => $network['description'],
                    'parameters' => $network['params'],
                    'status' => 'pending',
                ];

                // For one-task-per-session devices, mark subsequent tasks to wait
                if ($isOneTaskPerSession && !$isFirstTask) {
                    $taskData['progress_info'] = ['wait_for_next_session' => true];
                }

                $task = Task::create($taskData);
                $tasks[] = $task;
                $isFirstTask = false;
            }
        } else {
            // Other devices: Single task with all parameters
            $allParams = [];
            foreach ($networks as $network) {
                $allParams = array_merge($allParams, $network['params']);
            }

            $task = Task::create([
                'device_id' => $device->id,
                'task_type' => 'set_parameter_values',
                'description' => "WiFi: Configure all networks (SSID: {$ssid})",
                'parameters' => $allParams,
                'status' => 'pending',
            ]);
            $tasks[] = $task;
        }

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        // Store WiFi credentials locally for support team visibility
        // Passwords are not readable from the device (security feature), so we save them when set
        DeviceWifiCredential::updateOrCreate(
            ['device_id' => $device->id],
            [
                'ssid' => $ssid,
                'main_password' => $password,
                'guest_ssid' => $ssid . '-Guest',
                'guest_password' => $guestPassword,
                'guest_enabled' => $enableGuest,
                'set_by' => auth()->user()?->name ?? 'System',
            ]
        );

        $response = [
            'message' => $isNokia
                ? 'Standard WiFi configuration queued (2 tasks for Nokia device)'
                : 'Standard WiFi configuration applied',
            'tasks' => array_map(fn($t) => ['id' => $t->id, 'description' => $t->description], $tasks),
            'task_count' => count($tasks),
            'networks_configured' => [
                'main' => $ssid,
                'dedicated_24ghz' => $ssid . '-2.4GHz',
                'dedicated_5ghz' => $ssid . '-5GHz',
                'guest' => $enableGuest ? ($ssid . '-Guest') : 'disabled',
            ],
        ];

        // Include the generated guest password so user can save it
        // Only include if guest network is enabled and we generated the password (not user-provided)
        if ($enableGuest && !isset($validated['guest_password'])) {
            $response['guest_password'] = $guestPassword;
        }

        return response()->json($response, 201);
    }

    /**
     * Apply WiFi configuration for TR-098 Nokia Beacon G6 devices
     * TR-098 uses InternetGatewayDevice.LANDevice.1.WLANConfiguration.{i} structure
     * Instance mapping: 1-4 = 2.4GHz networks, 5-8 = 5GHz networks
     *   1/5 = Main (band-steered), 2/6 = Dedicated, 3/7 = Unused, 4/8 = Guest
     */
    private function applyTr098NokiaWifiConfig(
        Device $device,
        string $ssid,
        string $password,
        bool $enableGuest,
        string $guestPassword,
        array $validated
    ): JsonResponse {
        // TR-098 parameter path prefix
        $prefix = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';

        // TR-098 uses BeaconType for security mode
        // 11iandWPA3 = WPA2 + WPA3 transitional (best security with backward compatibility)
        $beaconType = '11iandWPA3';
        $authMode = 'SAEandPSKAuthentication';  // WPA3-SAE + WPA2-PSK
        $encryptionMode = 'AESEncryption';

        // Build network configurations grouped by radio (like TR-181 Nokia)
        // This minimizes radio restarts
        $networks = [
            'radio_24ghz' => [
                'description' => "WiFi: Configure all 2.4GHz networks",
                'params' => [
                    // Instance 1 - Main 2.4GHz (band-steered SSID)
                    "{$prefix}.1.SSID" => $ssid,
                    "{$prefix}.1.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.1.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.1.BeaconType" => $beaconType,
                    "{$prefix}.1.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.1.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.1.PreSharedKey.1.KeyPassphrase" => $password,
                    // Instance 2 - Dedicated 2.4GHz
                    "{$prefix}.2.SSID" => $ssid . '-2.4GHz',
                    "{$prefix}.2.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.2.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.2.BeaconType" => $beaconType,
                    "{$prefix}.2.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.2.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.2.PreSharedKey.1.KeyPassphrase" => $password,
                    // Instance 3 - Unused (disabled)
                    "{$prefix}.3.Enable" => ['value' => false, 'type' => 'xsd:boolean'],
                    // Instance 4 - Guest 2.4GHz
                    "{$prefix}.4.SSID" => $ssid . '-Guest',
                    "{$prefix}.4.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                    "{$prefix}.4.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                    "{$prefix}.4.BeaconType" => $beaconType,
                    "{$prefix}.4.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.4.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.4.PreSharedKey.1.KeyPassphrase" => $guestPassword,
                ],
            ],
            'radio_5ghz' => [
                'description' => "WiFi: Configure all 5GHz networks",
                'params' => [
                    // Instance 5 - Main 5GHz (band-steered SSID)
                    "{$prefix}.5.SSID" => $ssid,
                    "{$prefix}.5.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.5.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.5.BeaconType" => $beaconType,
                    "{$prefix}.5.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.5.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.5.PreSharedKey.1.KeyPassphrase" => $password,
                    // Instance 6 - Dedicated 5GHz
                    "{$prefix}.6.SSID" => $ssid . '-5GHz',
                    "{$prefix}.6.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.6.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
                    "{$prefix}.6.BeaconType" => $beaconType,
                    "{$prefix}.6.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.6.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.6.PreSharedKey.1.KeyPassphrase" => $password,
                    // Instance 7 - Unused (disabled)
                    "{$prefix}.7.Enable" => ['value' => false, 'type' => 'xsd:boolean'],
                    // Instance 8 - Guest 5GHz
                    "{$prefix}.8.SSID" => $ssid . '-Guest',
                    "{$prefix}.8.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                    "{$prefix}.8.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
                    "{$prefix}.8.BeaconType" => $beaconType,
                    "{$prefix}.8.IEEE11iAuthenticationMode" => $authMode,
                    "{$prefix}.8.IEEE11iEncryptionModes" => $encryptionMode,
                    "{$prefix}.8.PreSharedKey.1.KeyPassphrase" => $guestPassword,
                ],
            ],
        ];

        $tasks = [];
        $isOneTaskPerSession = $this->isOneTaskPerSessionDevice($device);

        // Create separate tasks for each radio to avoid firmware stall
        // For one-task-per-session devices, mark subsequent tasks to wait for next session
        $isFirstTask = true;
        foreach ($networks as $networkKey => $network) {
            $taskData = [
                'device_id' => $device->id,
                'task_type' => 'set_parameter_values',
                'description' => $network['description'],
                'parameters' => $network['params'],
                'status' => 'pending',
            ];

            // For one-task-per-session devices, mark subsequent tasks to wait
            if ($isOneTaskPerSession && !$isFirstTask) {
                $taskData['progress_info'] = ['wait_for_next_session' => true];
            }

            $task = Task::create($taskData);
            $tasks[] = $task;
            $isFirstTask = false;
        }

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        // Store WiFi credentials locally for support team visibility
        DeviceWifiCredential::updateOrCreate(
            ['device_id' => $device->id],
            [
                'ssid' => $ssid,
                'main_password' => $password,
                'guest_ssid' => $ssid . '-Guest',
                'guest_password' => $guestPassword,
                'guest_enabled' => $enableGuest,
                'set_by' => auth()->user()?->name ?? 'System',
            ]
        );

        $response = [
            'message' => 'Standard WiFi configuration queued (2 tasks for TR-098 Nokia device)',
            'tasks' => array_map(fn($t) => ['id' => $t->id, 'description' => $t->description], $tasks),
            'task_count' => count($tasks),
            'networks_configured' => [
                'main' => $ssid,
                'dedicated_24ghz' => $ssid . '-2.4GHz',
                'dedicated_5ghz' => $ssid . '-5GHz',
                'guest' => $enableGuest ? ($ssid . '-Guest') : 'disabled',
            ],
            'data_model' => 'TR-098',
        ];

        // Include the generated guest password so user can save it
        if ($enableGuest && !isset($validated['guest_password'])) {
            $response['guest_password'] = $guestPassword;
        }

        return response()->json($response, 201);
    }

    /**
     * Apply WiFi configuration for TR-098 Calix devices
     * TR-098 Calix uses InternetGatewayDevice.LANDevice.1.WLANConfiguration.{i} structure
     *
     * Instance mapping varies by device type:
     *
     * GigaCenter (ENT, ONT, etc.): 16 instances, dual-band
     *   1-8 = 2.4GHz radio (1=Primary, 2=Guest, 3=Dedicated)
     *   9-16 = 5GHz radio (9=Primary, 10=Guest, 12=Dedicated)
     *
     * GigaSpire: 24 instances, dual-band (NO 6GHz support despite 24 instances)
     *   1-8 = 5GHz radio (1=Primary, 2=Guest, 3=Dedicated)
     *   9-16 = 2.4GHz radio (9=Primary, 10=Guest, 12=Dedicated)
     *   17-24 = Reserved/unused (Channel 0)
     */
    private function applyTr098CalixWifiConfig(
        Device $device,
        string $ssid,
        string $password,
        bool $enableGuest,
        string $guestPassword,
        array $validated
    ): JsonResponse {
        // TR-098 parameter path prefix
        $prefix = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';

        // Calix uses the STANDARD KeyPassphrase for SETTING passwords
        // Note: X_000631_KeyPassphrase is READ-ONLY (returns the actual password in clear text)
        // For WRITING, we must use the standard PreSharedKey.1.KeyPassphrase parameter
        $passwordParam = 'PreSharedKey.1.KeyPassphrase';

        // Detect GigaSpire devices - they have DIFFERENT instance mapping and do NOT support 6GHz
        // GigaSpire uses product_class = "GigaSpire" while GigaCenter uses "ENT", "ONT", etc.
        $isGigaSpire = strtolower($device->product_class ?? '') === 'gigaspire';

        // Security settings differ between GigaCenter and GigaSpire:
        // GigaCenter: Does NOT support WPA3, use WPA/WPA2 mixed mode
        // GigaSpire: Supports WPA3, use WPA2/WPA3 transitional
        if ($isGigaSpire) {
            $beaconType = '11iandWPA3';
            $authMode = 'SAEandPSKAuthentication';  // WPA3-SAE + WPA2-PSK
            $encryptionMode = 'AESEncryption';
        } else {
            // GigaCenter - WPA/WPA2 mixed (best available)
            $beaconType = 'WPAand11i';
            $authMode = null;
            $encryptionMode = null;
        }

        // Instance mapping differs between GigaCenter and GigaSpire:
        // GigaCenter: 1-8 = 2.4GHz, 9-16 = 5GHz
        // GigaSpire:  1-8 = 5GHz, 9-16 = 2.4GHz (swapped!)
        if ($isGigaSpire) {
            // GigaSpire: 5GHz uses instances 1-8, 2.4GHz uses instances 9-16
            $inst24Primary = 9;   // Primary 2.4GHz (band-steered)
            $inst24Guest = 10;    // Guest 2.4GHz
            $inst24Dedicated = 12; // Dedicated 2.4GHz only
            $inst5Primary = 1;    // Primary 5GHz (band-steered)
            $inst5Guest = 2;      // Guest 5GHz
            $inst5Dedicated = 3;  // Dedicated 5GHz only
        } else {
            // GigaCenter: 2.4GHz uses instances 1-8, 5GHz uses instances 9-16
            $inst24Primary = 1;   // Primary 2.4GHz (band-steered)
            $inst24Guest = 2;     // Guest 2.4GHz
            $inst24Dedicated = 3; // Dedicated 2.4GHz only
            $inst5Primary = 9;    // Primary 5GHz (band-steered)
            $inst5Guest = 10;     // Guest 5GHz
            $inst5Dedicated = 12; // Dedicated 5GHz only
        }

        // Base parameters for 2.4GHz radio (supported by all Calix TR-098 devices)
        // Uses instance variables set above based on device type
        $params24ghz = [
            // Primary 2.4GHz (band-steered)
            "{$prefix}.{$inst24Primary}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Primary}.SSID" => $ssid,
            "{$prefix}.{$inst24Primary}.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Primary}.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Primary}.BeaconType" => $beaconType,
            "{$prefix}.{$inst24Primary}.{$passwordParam}" => $password,
            // Guest 2.4GHz
            "{$prefix}.{$inst24Guest}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Guest}.SSID" => $ssid . '-Guest',
            "{$prefix}.{$inst24Guest}.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Guest}.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Guest}.BeaconType" => $beaconType,
            "{$prefix}.{$inst24Guest}.{$passwordParam}" => $guestPassword,
            // Dedicated 2.4GHz only
            "{$prefix}.{$inst24Dedicated}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Dedicated}.SSID" => $ssid . '-2.4GHz',
            "{$prefix}.{$inst24Dedicated}.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Dedicated}.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst24Dedicated}.BeaconType" => $beaconType,
            "{$prefix}.{$inst24Dedicated}.{$passwordParam}" => $password,
        ];

        // Add GigaCenter-specific parameters (NOT supported on GigaSpire)
        if (!$isGigaSpire) {
            // Disable legacy features that hurt performance on modern networks
            $params24ghz["{$prefix}.{$inst24Primary}.X_000631_AirtimeFairness"] = ['value' => false, 'type' => 'xsd:boolean'];
            $params24ghz["{$prefix}.{$inst24Primary}.X_000631_MulticastForwardEnable"] = ['value' => false, 'type' => 'xsd:boolean'];
            // Enable client isolation on guest network
            $params24ghz["{$prefix}.{$inst24Guest}.X_000631_IntraSsidIsolation"] = ['value' => true, 'type' => 'xsd:boolean'];
        }

        // Add WPA2/WPA3 transitional security parameters (GigaSpire only)
        if ($authMode && $encryptionMode) {
            // Primary 2.4GHz security
            $params24ghz["{$prefix}.{$inst24Primary}.IEEE11iAuthenticationMode"] = $authMode;
            $params24ghz["{$prefix}.{$inst24Primary}.IEEE11iEncryptionModes"] = $encryptionMode;
            // Guest 2.4GHz security
            $params24ghz["{$prefix}.{$inst24Guest}.IEEE11iAuthenticationMode"] = $authMode;
            $params24ghz["{$prefix}.{$inst24Guest}.IEEE11iEncryptionModes"] = $encryptionMode;
            // Dedicated 2.4GHz security
            $params24ghz["{$prefix}.{$inst24Dedicated}.IEEE11iAuthenticationMode"] = $authMode;
            $params24ghz["{$prefix}.{$inst24Dedicated}.IEEE11iEncryptionModes"] = $encryptionMode;
        }

        // Base parameters for 5GHz radio (supported by all Calix TR-098 devices)
        // Uses instance variables set above based on device type
        $params5ghz = [
            // Primary 5GHz (band-steered)
            "{$prefix}.{$inst5Primary}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Primary}.SSID" => $ssid,
            "{$prefix}.{$inst5Primary}.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Primary}.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Primary}.BeaconType" => $beaconType,
            "{$prefix}.{$inst5Primary}.{$passwordParam}" => $password,
            // Guest 5GHz
            "{$prefix}.{$inst5Guest}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Guest}.SSID" => $ssid . '-Guest',
            "{$prefix}.{$inst5Guest}.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Guest}.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Guest}.BeaconType" => $beaconType,
            "{$prefix}.{$inst5Guest}.{$passwordParam}" => $guestPassword,
            // Dedicated 5GHz only
            "{$prefix}.{$inst5Dedicated}.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Dedicated}.SSID" => $ssid . '-5GHz',
            "{$prefix}.{$inst5Dedicated}.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Dedicated}.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
            "{$prefix}.{$inst5Dedicated}.BeaconType" => $beaconType,
            "{$prefix}.{$inst5Dedicated}.{$passwordParam}" => $password,
        ];

        // Add GigaCenter-specific parameters for 5GHz (NOT supported on GigaSpire)
        if (!$isGigaSpire) {
            // Enable client isolation on guest network
            $params5ghz["{$prefix}.{$inst5Guest}.X_000631_IntraSsidIsolation"] = ['value' => true, 'type' => 'xsd:boolean'];
        }

        // Add GigaSpire-specific parameters for 5GHz (MU-MIMO)
        if ($isGigaSpire) {
            $params5ghz["{$prefix}.{$inst5Primary}.X_000631_EnableMUMIMO"] = ['value' => true, 'type' => 'xsd:boolean'];
            $params5ghz["{$prefix}.{$inst5Guest}.X_000631_EnableMUMIMO"] = ['value' => true, 'type' => 'xsd:boolean'];
            $params5ghz["{$prefix}.{$inst5Dedicated}.X_000631_EnableMUMIMO"] = ['value' => true, 'type' => 'xsd:boolean'];
        }

        // Add WPA2/WPA3 transitional security parameters for 5GHz (GigaSpire only)
        if ($authMode && $encryptionMode) {
            // Primary 5GHz security
            $params5ghz["{$prefix}.{$inst5Primary}.IEEE11iAuthenticationMode"] = $authMode;
            $params5ghz["{$prefix}.{$inst5Primary}.IEEE11iEncryptionModes"] = $encryptionMode;
            // Guest 5GHz security
            $params5ghz["{$prefix}.{$inst5Guest}.IEEE11iAuthenticationMode"] = $authMode;
            $params5ghz["{$prefix}.{$inst5Guest}.IEEE11iEncryptionModes"] = $encryptionMode;
            // Dedicated 5GHz security
            $params5ghz["{$prefix}.{$inst5Dedicated}.IEEE11iAuthenticationMode"] = $authMode;
            $params5ghz["{$prefix}.{$inst5Dedicated}.IEEE11iEncryptionModes"] = $encryptionMode;
        }

        // Initialize networks with 2.4GHz and 5GHz
        $networks = [
            'radio_24ghz' => [
                'description' => "WiFi: Configure 2.4GHz networks (Primary + Guest + Dedicated)",
                'params' => $params24ghz,
            ],
            'radio_5ghz' => [
                'description' => "WiFi: Configure 5GHz networks (Primary + Guest + Dedicated)",
                'params' => $params5ghz,
            ],
        ];

        // Note: GigaSpire has 24 WLAN instances but instances 17-24 are reserved/unused (Channel 0)
        // GigaSpire does NOT support 6GHz - only dual-band (2.4GHz + 5GHz)

        $tasks = [];
        $isOneTaskPerSession = $this->isOneTaskPerSessionDevice($device);

        // Create separate tasks for each radio to minimize disruption
        $isFirstTask = true;
        foreach ($networks as $networkKey => $network) {
            $taskData = [
                'device_id' => $device->id,
                'task_type' => 'set_parameter_values',
                'description' => $network['description'],
                'parameters' => $network['params'],
                'status' => 'pending',
            ];

            // For one-task-per-session devices, mark subsequent tasks to wait
            if ($isOneTaskPerSession && !$isFirstTask) {
                $taskData['progress_info'] = ['wait_for_next_session' => true];
            }

            $task = Task::create($taskData);
            $tasks[] = $task;
            $isFirstTask = false;
        }

        // Queue a refresh task to update the UI after WiFi config is applied
        // This reads back the WiFi parameters so the GUI shows the new settings
        // Note: X_000631_KeyPassphrase is the READ-ONLY parameter that exposes passwords in clear text (GigaCenter ONLY)
        // Uses instance variables set above based on device type (GigaSpire vs GigaCenter)
        $refreshParams = [
            // Primary 2.4GHz
            "{$prefix}.{$inst24Primary}.RadioEnabled", "{$prefix}.{$inst24Primary}.Status", "{$prefix}.{$inst24Primary}.SSID", "{$prefix}.{$inst24Primary}.Enable",
            "{$prefix}.{$inst24Primary}.BeaconType",
            // Guest 2.4GHz
            "{$prefix}.{$inst24Guest}.RadioEnabled", "{$prefix}.{$inst24Guest}.Status", "{$prefix}.{$inst24Guest}.SSID", "{$prefix}.{$inst24Guest}.Enable",
            "{$prefix}.{$inst24Guest}.BeaconType",
            // Dedicated 2.4GHz
            "{$prefix}.{$inst24Dedicated}.RadioEnabled", "{$prefix}.{$inst24Dedicated}.Status", "{$prefix}.{$inst24Dedicated}.SSID", "{$prefix}.{$inst24Dedicated}.Enable",
            "{$prefix}.{$inst24Dedicated}.BeaconType",
            // Primary 5GHz
            "{$prefix}.{$inst5Primary}.RadioEnabled", "{$prefix}.{$inst5Primary}.Status", "{$prefix}.{$inst5Primary}.SSID", "{$prefix}.{$inst5Primary}.Enable",
            "{$prefix}.{$inst5Primary}.BeaconType",
            // Guest 5GHz
            "{$prefix}.{$inst5Guest}.RadioEnabled", "{$prefix}.{$inst5Guest}.Status", "{$prefix}.{$inst5Guest}.SSID", "{$prefix}.{$inst5Guest}.Enable",
            "{$prefix}.{$inst5Guest}.BeaconType",
            // Dedicated 5GHz
            "{$prefix}.{$inst5Dedicated}.RadioEnabled", "{$prefix}.{$inst5Dedicated}.Status", "{$prefix}.{$inst5Dedicated}.SSID", "{$prefix}.{$inst5Dedicated}.Enable",
            "{$prefix}.{$inst5Dedicated}.BeaconType",
        ];

        // GigaCenter ONLY: Add X_000631_KeyPassphrase to read passwords (GigaSpire does NOT support this)
        if (!$isGigaSpire) {
            $refreshParams[] = "{$prefix}.{$inst24Primary}.PreSharedKey.1.X_000631_KeyPassphrase";
            $refreshParams[] = "{$prefix}.{$inst24Guest}.PreSharedKey.1.X_000631_KeyPassphrase";
            $refreshParams[] = "{$prefix}.{$inst24Dedicated}.PreSharedKey.1.X_000631_KeyPassphrase";
            $refreshParams[] = "{$prefix}.{$inst5Primary}.PreSharedKey.1.X_000631_KeyPassphrase";
            $refreshParams[] = "{$prefix}.{$inst5Guest}.PreSharedKey.1.X_000631_KeyPassphrase";
            $refreshParams[] = "{$prefix}.{$inst5Dedicated}.PreSharedKey.1.X_000631_KeyPassphrase";
        }

        // Add GigaCenter-specific refresh params (NOT supported on GigaSpire)
        if (!$isGigaSpire) {
            $refreshParams[] = "{$prefix}.{$inst24Primary}.X_000631_AirtimeFairness";
            $refreshParams[] = "{$prefix}.{$inst24Primary}.X_000631_MulticastForwardEnable";
        }

        // Add MU-MIMO refresh params for GigaSpire devices
        if ($isGigaSpire) {
            $refreshParams[] = "{$prefix}.{$inst5Primary}.X_000631_EnableMUMIMO";
            $refreshParams[] = "{$prefix}.{$inst5Guest}.X_000631_EnableMUMIMO";
            $refreshParams[] = "{$prefix}.{$inst5Dedicated}.X_000631_EnableMUMIMO";
        }
        $refreshTask = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_parameter_values',
            'description' => 'WiFi: Refresh parameters after configuration',
            'parameters' => ['names' => $refreshParams],
            'status' => 'pending',
        ]);
        $tasks[] = $refreshTask;

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        // Store WiFi credentials locally for support team visibility
        // Calix exposes passwords, but we store them for consistency
        DeviceWifiCredential::updateOrCreate(
            ['device_id' => $device->id],
            [
                'ssid' => $ssid,
                'main_password' => $password,
                'guest_ssid' => $ssid . '-Guest',
                'guest_password' => $guestPassword,
                'guest_enabled' => $enableGuest,
                'set_by' => auth()->user()?->name ?? 'System',
            ]
        );

        $deviceSubType = $isGigaSpire ? 'GigaSpire' : 'GigaCenter';
        $noteText = $isGigaSpire
            ? 'GigaSpire creates: main dual-band SSID (2.4/5GHz), dedicated 2.4GHz/5GHz SSIDs, and optional guest network. MU-MIMO enabled on 5GHz. UI will refresh automatically.'
            : 'GigaCenter creates: main band-steered SSID, dedicated 2.4GHz/5GHz SSIDs, and optional guest with client isolation. UI will refresh automatically.';

        $taskMessage = 'Standard WiFi configuration queued (3 tasks: 2.4GHz config, 5GHz config, refresh)';

        $networksConfigured = [
            'primary' => $ssid . ($isGigaSpire ? ' (dual-band: 2.4/5 GHz)' : ' (band-steered)'),
            'dedicated_24ghz' => $ssid . '-2.4GHz',
            'dedicated_5ghz' => $ssid . '-5GHz',
            'guest' => $enableGuest ? ($ssid . '-Guest') : 'disabled',
        ];

        $response = [
            'message' => $taskMessage,
            'tasks' => array_map(fn($t) => ['id' => $t->id, 'description' => $t->description], $tasks),
            'task_count' => count($tasks),
            'networks_configured' => $networksConfigured,
            'data_model' => 'TR-098',
            'device_type' => 'Calix ' . $deviceSubType,
            'note' => $noteText,
        ];

        // Include the generated guest password so user can save it
        if ($enableGuest && !isset($validated['guest_password'])) {
            $response['guest_password'] = $guestPassword;
        }

        return response()->json($response, 201);
    }

    /**
     * Toggle guest network on/off
     */
    public function toggleGuestNetwork(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $dataModel = $device->getDataModel();

        // Detect Nokia devices using centralized detection
        $isNokia = $device->isNokia();

        // Check for supported device types
        if ($dataModel !== 'TR-181' && !($dataModel === 'TR-098' && $isNokia)) {
            return response()->json([
                'error' => 'Guest network toggle is only supported for TR-181 devices and TR-098 Nokia Beacon G6',
            ], 400);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $validated['enabled'];

        // Build parameter values based on data model
        if ($dataModel === 'TR-098' && $isNokia) {
            // TR-098 Nokia uses WLANConfiguration instances 4 (2.4GHz) and 8 (5GHz) for guest
            $prefix = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';
            $values = [
                "{$prefix}.4.Enable" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "{$prefix}.4.SSIDAdvertisementEnabled" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "{$prefix}.8.Enable" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "{$prefix}.8.SSIDAdvertisementEnabled" => ['value' => $enabled, 'type' => 'xsd:boolean'],
            ];
        } else {
            // TR-181: Nokia uses AP.4/AP.8 for guest, others use AP.3/AP.7
            $guestAp24 = $isNokia ? 4 : 3;
            $guestAp5 = $isNokia ? 8 : 7;
            $values = [
                "Device.WiFi.AccessPoint.{$guestAp24}.Enable" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "Device.WiFi.AccessPoint.{$guestAp24}.SSIDAdvertisementEnabled" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "Device.WiFi.AccessPoint.{$guestAp5}.Enable" => ['value' => $enabled, 'type' => 'xsd:boolean'],
                "Device.WiFi.AccessPoint.{$guestAp5}.SSIDAdvertisementEnabled" => ['value' => $enabled, 'type' => 'xsd:boolean'],
            ];
        }

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'description' => 'WiFi: ' . ($enabled ? 'Enable' : 'Disable') . ' guest network',
            'parameters' => $values,
            'status' => 'pending',
        ]);

        $this->connectionRequestService->sendConnectionRequest($device);

        // Update stored credentials
        $storedCredentials = DeviceWifiCredential::where('device_id', $device->id)->first();
        if ($storedCredentials) {
            $storedCredentials->update(['guest_enabled' => $enabled]);
        }

        return response()->json([
            'message' => 'Guest network ' . ($enabled ? 'enabled' : 'disabled'),
            'task' => $task,
        ], 201);
    }

    // =========================================================================
    // Remote Support Password Management (Nokia Beacon G6)
    // =========================================================================

    /**
     * Get remote support status for a device
     */
    public function getRemoteSupportStatus(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (!$device->isNokiaBeacon()) {
            return response()->json([
                'supported' => false,
                'message' => 'Remote support password management is only available for Nokia Beacon G6 devices',
            ]);
        }

        return response()->json([
            'supported' => true,
            'is_active' => $device->isRemoteSupportActive(),
            'expires_at' => $device->remote_support_expires_at?->toIso8601String(),
            'time_remaining' => $device->getRemoteSupportTimeRemaining(),
            'enabled_by' => $device->remoteSupportEnabledBy?->name,
            'password_suffix' => $device->password_suffix ? '****' . substr($device->password_suffix, -4) : null,
        ]);
    }

    /**
     * Enable remote support - sets password to known support password for 1 hour
     */
    public function enableRemoteSupport(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (!$device->isNokiaBeacon()) {
            return response()->json([
                'error' => 'Remote support password management is only available for Nokia Beacon G6 devices',
            ], 400);
        }

        $validated = $request->validate([
            'duration_minutes' => 'nullable|integer|min:15|max:480', // 15 min to 8 hours
        ]);

        $durationMinutes = $validated['duration_minutes'] ?? 60;
        $userId = auth()->id();

        $task = $device->enableRemoteSupport($userId, $durationMinutes);

        if (!$task) {
            return response()->json([
                'error' => 'Failed to create password change task',
            ], 500);
        }

        // Send connection request to apply immediately
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'message' => 'Remote support enabled',
            'task_id' => $task->id,
            'expires_at' => $device->fresh()->remote_support_expires_at->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'password' => Device::getSupportPassword(),
        ], 201);
    }

    /**
     * Disable remote support - resets password to device-specific password
     */
    public function disableRemoteSupport(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (!$device->isNokiaBeacon()) {
            return response()->json([
                'error' => 'Remote support password management is only available for Nokia Beacon G6 devices',
            ], 400);
        }

        $task = $device->disableRemoteSupport();

        if (!$task) {
            return response()->json([
                'error' => 'Failed to create password reset task',
            ], 500);
        }

        // Send connection request to apply immediately
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'message' => 'Remote support disabled - password will be reset to device-specific value',
            'task_id' => $task->id,
        ]);
    }

    /**
     * Set the initial device-specific password (for manual provisioning)
     */
    public function setInitialPassword(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (!$device->isNokiaBeacon()) {
            return response()->json([
                'error' => 'Password management is only available for Nokia Beacon G6 devices',
            ], 400);
        }

        $task = $device->setInitialPassword();

        if (!$task) {
            return response()->json([
                'error' => 'Failed to create password set task',
            ], 500);
        }

        // Send connection request to apply immediately
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'message' => 'Initial password task created',
            'task_id' => $task->id,
            'password_format' => '{SerialNumber}_{RandomSuffix}_stay$away',
        ], 201);
    }

    // =========================================================================
    // WiFi Configuration & Password Management (SSH-extracted)
    // =========================================================================

    /**
     * Get WiFi configurations for a device (without passwords)
     */
    public function wifiConfigs(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $configs = $device->wifiConfigs()
            ->orderByRaw("FIELD(band, '2.4GHz', '5GHz', '6GHz')")
            ->orderBy('interface_name')
            ->get()
            ->map(function ($config) {
                return [
                    'id' => $config->id,
                    'interface_name' => $config->interface_name,
                    'radio' => $config->radio,
                    'band' => $config->band,
                    'ssid' => $config->ssid,
                    'has_password' => !empty($config->password_encrypted),
                    'encryption' => $config->encryption,
                    'hidden' => $config->hidden,
                    'enabled' => $config->enabled,
                    'network_type' => $config->network_type,
                    'is_mesh_backhaul' => $config->is_mesh_backhaul,
                    'max_clients' => $config->max_clients,
                    'client_isolation' => $config->client_isolation,
                    'wps_enabled' => $config->wps_enabled,
                    'mac_address' => $config->mac_address,
                    'extracted_at' => $config->extracted_at?->toIso8601String(),
                    'extraction_method' => $config->extraction_method,
                ];
            });

        return response()->json([
            'data' => $configs,
            'total' => $configs->count(),
            'has_ssh_credentials' => $device->hasSshCredentials(),
            'credentials_verified' => $device->sshCredentials?->verified ?? false,
        ]);
    }

    /**
     * Get WiFi passwords for support display
     * Only returns customer-facing networks (not backhaul)
     */
    public function wifiPasswords(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Check if device has WiFi configs
        if (!$device->hasWifiConfigs()) {
            return response()->json([
                'error' => 'No WiFi configuration available for this device',
                'hint' => 'Run SSH extraction first to retrieve WiFi passwords',
            ], 404);
        }

        $passwords = $device->wifiConfigs()
            ->customerFacing()
            ->enabled()
            ->orderByRaw("FIELD(band, '2.4GHz', '5GHz', '6GHz')")
            ->get()
            ->map(function ($config) {
                return [
                    'ssid' => $config->ssid,
                    'password' => $config->getPassword(),
                    'band' => $config->band,
                    'network_type' => $config->network_type,
                    'interface' => $config->interface_name,
                ];
            });

        Log::info('WiFi passwords retrieved for support', [
            'device_id' => $device->id,
            'networks' => $passwords->count(),
            'user' => auth()->user()?->email ?? 'system',
        ]);

        return response()->json([
            'data' => $passwords,
            'extracted_at' => $device->wifiConfigs()->first()?->extracted_at?->toIso8601String(),
        ]);
    }

    /**
     * Trigger SSH extraction of WiFi configuration
     */
    public function extractWifiConfig(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Check if device has SSH credentials
        if (!$device->hasSshCredentials()) {
            return response()->json([
                'error' => 'No SSH credentials configured for this device',
                'hint' => 'Import SSH credentials from Nokia spreadsheet first',
            ], 400);
        }

        $credentials = $device->sshCredentials;
        if (!$credentials->isComplete()) {
            return response()->json([
                'error' => 'SSH credentials incomplete (missing shell password)',
            ], 400);
        }

        try {
            $service = app(\App\Services\NokiaSshService::class);
            $wifiConfigs = $service->extractWifiConfig($device);

            return response()->json([
                'message' => 'WiFi configuration extracted successfully',
                'networks_found' => count($wifiConfigs),
                'data' => collect($wifiConfigs)->map(function ($config) {
                    return [
                        'ssid' => $config->ssid,
                        'band' => $config->band,
                        'network_type' => $config->network_type,
                        'enabled' => $config->enabled,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('WiFi extraction failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'WiFi extraction failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test SSH connection to device
     */
    public function testSshConnection(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (!$device->hasSshCredentials()) {
            return response()->json([
                'success' => false,
                'error' => 'No SSH credentials configured for this device',
            ]);
        }

        $service = app(\App\Services\NokiaSshService::class);
        $result = $service->testConnection($device);

        return response()->json($result);
    }

    /**
     * Get SSH credential status for a device
     */
    public function sshCredentialStatus(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $credentials = $device->sshCredentials;

        if (!$credentials) {
            return response()->json([
                'has_credentials' => false,
                'message' => 'No SSH credentials configured',
            ]);
        }

        return response()->json([
            'has_credentials' => true,
            'username' => $credentials->ssh_username,
            'port' => $credentials->ssh_port,
            'has_ssh_password' => $credentials->hasSshAccess(),
            'has_shell_password' => $credentials->hasShellAccess(),
            'is_complete' => $credentials->isComplete(),
            'verified' => $credentials->verified,
            'last_ssh_success' => $credentials->last_ssh_success?->toIso8601String(),
            'last_ssh_failure' => $credentials->last_ssh_failure?->toIso8601String(),
            'last_error' => $credentials->last_error,
            'credential_source' => $credentials->credential_source,
            'imported_at' => $credentials->imported_at?->toIso8601String(),
        ]);
    }

    // =========================================================================
    // Helper Methods for Restore Parameter Validation
    // =========================================================================

    /**
     * Validate and remap restore parameters against device's current structure.
     *
     * This method:
     * 1. Detects which object instances exist on the device (from stored parameters)
     * 2. Skips backup parameters that reference non-existent instances
     * 3. Remaps pertinent parameters where possible (e.g., LANDevice.2.DHCP → LANDevice.1.DHCP)
     *
     * @param Device $device The device being restored to
     * @param array $params The parameters from backup to validate
     * @return array ['params' => validated params, 'skipped' => skipped params, 'remapped' => remapping info]
     */
    private function validateAndRemapRestoreParams(Device $device, array $params): array
    {
        $validParams = [];
        $skippedParams = [];
        $remappedParams = [];

        // Determine device-specific invalid instances
        // These are instances that we KNOW don't exist on certain device types
        $invalidInstances = [];

        // Calix GigaSpire devices don't have LANDevice.2 (guest network bridge)
        // Only GigaCenters have this - GigaSpires manage guest networks differently
        $productClass = $device->product_class ?? '';
        $isGigaSpire = stripos($productClass, 'GigaSpire') !== false;
        $isCalix = stripos($device->manufacturer ?? '', 'Calix') !== false;

        if ($isCalix && $isGigaSpire) {
            // GigaSpire only has LANDevice.1
            $invalidInstances['LANDevice.2'] = true;
            $invalidInstances['LANDevice.3'] = true;
            $invalidInstances['LANDevice.4'] = true;
            Log::debug("Restore validation: GigaSpire device - marking LANDevice.2+ as invalid");
        }

        // For TR-181 devices, TR-098 style paths are invalid and vice versa
        $isTr181 = $device->parameters()->where('name', 'LIKE', 'Device.%')->exists();
        $isTr098 = $device->parameters()->where('name', 'LIKE', 'InternetGatewayDevice.%')->exists();

        // Define pertinent parameters that should be remapped when their instance doesn't exist
        // These are settings that should be preserved even if the object instance changed
        $remappablePatterns = [
            // DHCP settings - critical for network operation
            'LANHostConfigManagement' => [
                'DHCPServerConfigurable',
                'DHCPServerEnable',
                'MinAddress',
                'MaxAddress',
                'ReservedAddresses',
                'DHCPLeaseTime',
                'SubnetMask',
                'IPRouters',
                'DNSServers',
            ],
            // IP Interface settings
            'IPInterface' => [
                'IPInterfaceIPAddress',
                'IPInterfaceSubnetMask',
                'IPInterfaceAddressingType',
            ],
        ];

        foreach ($params as $paramName => $paramData) {
            // Extract all object instances from this parameter name
            preg_match_all('/([A-Za-z]+)\.(\d+)/', $paramName, $matches, PREG_SET_ORDER);

            $hasInvalidInstance = false;
            $remapTarget = null;

            foreach ($matches as $match) {
                $objectType = $match[1];
                $instanceNum = (int) $match[2];
                $objectPath = $objectType . '.' . $instanceNum;

                // Check if this instance is explicitly invalid for this device type
                if (isset($invalidInstances[$objectPath])) {
                    $hasInvalidInstance = true;

                    // Try to remap to instance 1 if it's a remappable parameter
                    $alternateInstance = $objectType . '.1';
                    if (!isset($invalidInstances[$alternateInstance])) {
                        // Check if this parameter is remappable
                        $isRemappable = false;
                        foreach ($remappablePatterns as $remapObject => $remapParams) {
                            if ($objectType === 'LANDevice' || strpos($paramName, $remapObject) !== false) {
                                foreach ($remapParams as $remapParam) {
                                    if (strpos($paramName, $remapParam) !== false) {
                                        $isRemappable = true;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if ($isRemappable) {
                            // Create remapped parameter name
                            $remappedName = str_replace($objectPath, $alternateInstance, $paramName);

                            // Only remap if the target parameter doesn't already exist in our params
                            if (!isset($params[$remappedName]) && !isset($validParams[$remappedName])) {
                                $remapTarget = [
                                    'original' => $paramName,
                                    'remapped' => $remappedName,
                                    'from_instance' => $objectPath,
                                    'to_instance' => $alternateInstance,
                                ];
                            }
                        }
                    }
                    break; // One invalid instance is enough to flag this param
                }
            }

            if ($hasInvalidInstance) {
                if ($remapTarget !== null) {
                    // Remap the parameter
                    $validParams[$remapTarget['remapped']] = $paramData;
                    $remappedParams[] = $remapTarget;
                } else {
                    // Skip parameters with non-existent instances that can't be remapped
                    $skippedParams[] = $paramName;
                }
            } else {
                // Parameter is valid, keep it
                $validParams[$paramName] = $paramData;
            }
        }

        return [
            'params' => $validParams,
            'skipped' => $skippedParams,
            'remapped' => $remappedParams,
        ];
    }

    // =========================================================================
    // Helper Methods for TR-098 Calix Parameter Handling
    // =========================================================================

    /**
     * Determine if a TR-098 Calix parameter is likely writable based on patterns.
     *
     * This is used when backups don't have writable attribute info (TR-098 devices
     * using GetParameterValues don't return writable info - only GetParameterNames does).
     *
     * @param string $paramName The parameter name to check
     * @return bool True if the parameter is likely writable
     */
    private function isTr098CalixWritableParameter(string $paramName): bool
    {
        // SPECIFIC patterns for parameters that ARE writable on TR-098 Calix devices
        // Be very conservative - only include parameters we KNOW are writable
        $writablePatterns = [
            // ===== WiFi Configuration - Core Writable Settings =====
            '/WLANConfiguration\.\d+\.SSID$/i',
            '/WLANConfiguration\.\d+\.Enable$/i',
            '/WLANConfiguration\.\d+\.RadioEnabled$/i',
            '/WLANConfiguration\.\d+\.SSIDAdvertisementEnabled$/i',
            '/WLANConfiguration\.\d+\.BeaconType$/i',
            '/WLANConfiguration\.\d+\.BasicAuthenticationMode$/i',
            '/WLANConfiguration\.\d+\.BasicEncryptionModes$/i',
            '/WLANConfiguration\.\d+\.WPAAuthenticationMode$/i',
            '/WLANConfiguration\.\d+\.WPAEncryptionModes$/i',
            '/WLANConfiguration\.\d+\.IEEE11iAuthenticationMode$/i',
            '/WLANConfiguration\.\d+\.IEEE11iEncryptionModes$/i',
            '/WLANConfiguration\.\d+\.Channel$/i',
            '/WLANConfiguration\.\d+\.AutoChannelEnable$/i',
            '/WLANConfiguration\.\d+\.OperatingChannelBandwidth$/i',
            '/WLANConfiguration\.\d+\.WMMEnable$/i',
            '/WLANConfiguration\.\d+\.UAPSDEnable$/i',
            '/WLANConfiguration\.\d+\.TransmitPower$/i',
            '/WLANConfiguration\.\d+\.AutoRateFallBackEnabled$/i',
            // Calix vendor-specific WiFi params
            '/WLANConfiguration\.\d+\.X_000631_Bandwidth$/i',
            '/WLANConfiguration\.\d+\.X_000631_MaxClients$/i',
            '/WLANConfiguration\.\d+\.X_000631_ClientIsolation$/i',
            '/WLANConfiguration\.\d+\.X_000631_Hidden$/i',
            '/WLANConfiguration\.\d+\.X_000631_OperatingMode$/i',
            '/WLANConfiguration\.\d+\.X_000631_WPSPushButton$/i',
            '/WLANConfiguration\.\d+\.X_000631_WPSEnabled$/i',
            '/WLANConfiguration\.\d+\.X_000631_PMFMode$/i',

            // ===== WiFi PreSharedKey (passwords) =====
            '/PreSharedKey\.\d+\.PreSharedKey$/i',
            '/PreSharedKey\.\d+\.KeyPassphrase$/i',
            // Note: X_000631_KeyPassphrase is READ-ONLY on GigaSpire (only GigaCenter supports it for writing)

            // ===== WEP Keys (legacy) =====
            '/WEPKey\.\d+\.WEPKey$/i',

            // ===== Port Forwarding / NAT =====
            '/PortMapping\.\d+\.PortMappingEnabled$/i',
            '/PortMapping\.\d+\.PortMappingDescription$/i',
            '/PortMapping\.\d+\.ExternalPort$/i',
            '/PortMapping\.\d+\.ExternalPortEndRange$/i',
            '/PortMapping\.\d+\.InternalPort$/i',
            '/PortMapping\.\d+\.InternalClient$/i',
            '/PortMapping\.\d+\.PortMappingProtocol$/i',
            '/PortMapping\.\d+\.RemoteHost$/i',
            '/PortMapping\.\d+\.PortMappingLeaseDuration$/i',

            // ===== Time/NTP - Only specific writable fields =====
            '/\.Time\.LocalTimeZone$/i',
            '/\.Time\.LocalTimeZoneName$/i',
            '/\.Time\.NTPServer1$/i',
            '/\.Time\.NTPServer2$/i',
            '/\.Time\.NTPServer3$/i',

            // ===== Management Server - Only specific writable fields =====
            '/ManagementServer\.PeriodicInformInterval$/i',
            '/ManagementServer\.PeriodicInformEnable$/i',
            '/ManagementServer\.ConnectionRequestUsername$/i',
            '/ManagementServer\.ConnectionRequestPassword$/i',

            // ===== DHCP Server settings =====
            '/LANHostConfigManagement\.DHCPServerConfigurable$/i',
            '/LANHostConfigManagement\.DHCPServerEnable$/i',
            '/LANHostConfigManagement\.MinAddress$/i',
            '/LANHostConfigManagement\.MaxAddress$/i',
            '/LANHostConfigManagement\.ReservedAddresses$/i',
            '/LANHostConfigManagement\.DHCPLeaseTime$/i',

            // ===== LAN IP Configuration =====
            '/LANHostConfigManagement\.IPInterface\.\d+\.IPInterfaceIPAddress$/i',
            '/LANHostConfigManagement\.IPInterface\.\d+\.IPInterfaceSubnetMask$/i',

            // ===== Device passwords (Calix specific) =====
            '/X_000631_WebPassword$/i',
            '/UserInterface\.X_000631_WebPassword$/i',
        ];

        // Check if parameter matches any writable pattern
        foreach ($writablePatterns as $pattern) {
            if (preg_match($pattern, $paramName)) {
                return true;
            }
        }

        // Default: assume NOT writable for safety
        // This is very conservative - only restore parameters we KNOW are writable
        return false;
    }
}
