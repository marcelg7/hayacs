<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupTemplate;
use App\Models\ConfigBackup;
use App\Models\Device;
use App\Models\SpeedTestResult;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    protected ConnectionRequestService $connectionRequestService;

    public function __construct(ConnectionRequestService $connectionRequestService)
    {
        $this->connectionRequestService = $connectionRequestService;
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
        $prefix = $dataModel === 'Device:2' ? 'Device.' : 'InternetGatewayDevice.';

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
        $discoveryParams = $this->buildDiscoveryParameters($dataModel);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'discover_troubleshooting',
            'parameters' => [
                'names' => $discoveryParams,
                'data_model' => $dataModel,
            ],
            'status' => 'pending',
        ]);

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
     */
    private function buildDiscoveryParameters(string $dataModel): array
    {
        $isDevice2 = $dataModel === 'Device:2';

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
        } else {
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
        $isDevice2 = $dataModel === 'Device:2';
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
    public function factoryReset(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

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
     * Uses GetParameterNames to discover all available parameters
     */
    public function getAllParameters(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Determine data model root
        $dataModel = $device->getDataModel();
        $root = $dataModel === 'Device:2' ? 'Device.' : 'InternetGatewayDevice.';

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_parameter_names',
            'parameters' => [
                'path' => $root,
                'next_level' => false, // Get ALL parameters recursively
            ],
            'status' => 'pending',
        ]);

        // Trigger immediate connection
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'message' => 'Get all parameters task created - discovering all device parameters',
            'task' => $task,
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
            'url' => 'required|url',
            'file_type' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'upload',
            'parameters' => [
                'url' => $validated['url'],
                'file_type' => $validated['file_type'] ?? '3 Vendor Log File',
                'username' => $validated['username'] ?? '',
                'password' => $validated['password'] ?? '',
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Upload task created successfully',
            'task' => $task,
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

        // Parameters to query for remote GUI access
        $parametersToQuery = [
            'InternetGatewayDevice.User.1.Username',
            'InternetGatewayDevice.User.1.Password',
            'InternetGatewayDevice.UserInterface.RemoteAccess.Port',
            'InternetGatewayDevice.UserInterface.RemoteAccess.Enable',
            'InternetGatewayDevice.User.2.RemoteAccessCapable',
        ];

        // Also need external IP address
        $externalIp = $device->parameters()
            ->where('name', 'LIKE', '%ExternalIPAddress%')
            ->where('name', 'LIKE', '%WANIPConnection%')
            ->first();

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
            'parameters' => [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
            ],
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $task,
            'enable_task' => $enableTask,
            'external_ip' => $externalIp ? $externalIp->value : null,
            'message' => 'Remote access is being enabled...',
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

        // Filter parameters to only include writable ones
        $writableParams = collect($backup->backup_data)
            ->filter(function ($param, $name) use ($selectedParams) {
                // Must be writable
                if (!($param['writable'] ?? false)) {
                    return false;
                }
                // If selective restore, must be in selected list
                if ($selectedParams !== null && !in_array($name, $selectedParams)) {
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

        if (empty($writableParams)) {
            return response()->json([
                'error' => 'No writable parameters found in backup' . ($selectedParams ? ' matching selection' : ''),
            ], 400);
        }

        // Create task to restore the parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => $writableParams,
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        $restoreType = $selectedParams ? 'Selective restore' : 'Full restore';

        return response()->json([
            'task' => $task,
            'message' => $restoreType . ' initiated',
            'writable_params_count' => count($writableParams),
            'total_params_in_backup' => count($backup->backup_data),
        ]);
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
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanDeviceIdx = 3;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 2;
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
        $isDevice2 = $dataModel === 'Device:2';

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
                ->filter(function ($mapping) {
                    return isset($mapping['PortMappingEnabled']) &&
                        $mapping['PortMappingEnabled'] === '1';
                })
                ->values();
        }

        return response()->json([
            'port_mappings' => $portMappings,
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

        // Detect manufacturer for device-specific handling
        $isSmartRG = strtolower($device->manufacturer) === 'smartrg' ||
            strtoupper(substr($device->oui ?? '', 0, 6)) === '3C9066' ||
            strtoupper($device->oui) === 'E82C6D';

        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

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
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanDeviceIdx = 3;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 2;
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
        $isDevice2 = $dataModel === 'Device:2';

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

        // Handle "Both" protocol by creating two separate port mappings (TCP and UDP)
        $protocols = $validated['protocol'] === 'Both' ? ['TCP', 'UDP'] : [$validated['protocol']];
        $tasks = [];

        foreach ($protocols as $protocol) {
            // Build parameters for the new port mapping
            // Use {instance} placeholder for SmartRG (will be replaced after AddObject returns the instance number)
            $instancePlaceholder = $isSmartRG ? '{instance}' : $instance;

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

            // Add ExternalPortEndRange only for non-SmartRG devices (SmartRG doesn't support it)
            if (!$isSmartRG) {
                $parameters["{$portMappingPrefix}.{$instancePlaceholder}.ExternalPortEndRange"] = [
                    'value' => $validated['external_port'],
                    'type' => 'xsd:unsignedInt',
                ];
            }

            // SmartRG requires AddObject first to create the instance, then SetParameterValues
            if ($isSmartRG) {
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
                // Other devices - directly set parameters
                $task = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'description' => "Add port mapping ({$protocol})",
                    'status' => 'pending',
                    'parameters' => $parameters,
                ]);
            }

            $tasks[] = $task;

            // Increment instance for non-SmartRG devices when creating both TCP and UDP
            if (!$isSmartRG) {
                $instance++;
            }
        }

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $tasks[0], // Return first task for backwards compatibility
            'tasks' => $tasks,
            'message' => count($tasks) > 1 ? 'Port mapping creation initiated (TCP and UDP)' : 'Port mapping creation initiated',
            'instance' => $isSmartRG ? 'pending' : ($instance - count($tasks) + 1),
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
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanDeviceIdx = 3;
            $wanConnDeviceIdx = 1;
            $wanConnIdx = 2;
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
        $isDevice2 = $dataModel === 'Device:2';

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
        $isDevice2 = $dataModel === 'Device:2';

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
     * Start TR-143 SpeedTest (Download and Upload diagnostics)
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
        // Use Hay's TR-143 speedtest server
        $downloadUrl = $validated['download_url'] ?? 'http://tr143.hay.net/download.zip';
        $uploadUrl = $validated['upload_url'] ?? 'http://tr143.hay.net/handler.php';

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        // Detect SmartRG for combined parameter approach
        $isSmartRG = strtolower($device->manufacturer ?? '') === 'smartrg' ||
            strtoupper($device->oui ?? '') === 'E82C6D';

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
     * Get SpeedTest status and results
     */
    public function getSpeedTestStatus(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

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
        // Note: SmartRG devices report EOMTime with 'Z' suffix but the time is actually local time, not UTC
        // We treat it as local time by stripping the timezone and using app timezone
        $completedAt = null;
        if ($state === 'Complete') {
            $downloadEndTime = isset($downloadParams['EOMTime']) ? $downloadParams['EOMTime'] : null;
            $uploadEndTime = isset($uploadParams['EOMTime']) ? $uploadParams['EOMTime'] : null;
            $endTimeStr = $uploadEndTime ?? $downloadEndTime;
            if ($endTimeStr) {
                // Strip timezone indicator and parse as local time
                $timeWithoutTz = preg_replace('/[Z+-]\d{2}:\d{2}$/', '', $endTimeStr);
                $timeWithoutTz = rtrim($timeWithoutTz, 'Z');
                $endTime = \Carbon\Carbon::parse($timeWithoutTz, config('app.timezone'));
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
                return [
                    'id' => $result->id,
                    'download_mbps' => (float) $result->download_speed_mbps,
                    'upload_mbps' => (float) $result->upload_speed_mbps,
                    'download_bytes' => $result->download_bytes,
                    'upload_bytes' => $result->upload_bytes,
                    'download_duration_ms' => $result->download_duration_ms,
                    'upload_duration_ms' => $result->upload_duration_ms,
                    'test_type' => $result->test_type,
                    'completed_at' => $result->upload_end_time
                        ? $result->upload_end_time->toIso8601String()
                        : ($result->download_end_time ? $result->download_end_time->toIso8601String() : null),
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
}
