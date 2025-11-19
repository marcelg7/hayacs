<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        // Create initial auto-backup if not already created
        if (!$device->initial_backup_created && $device->parameters()->count() > 0) {
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

            $device->configBackups()->create([
                'name' => 'Initial Auto Backup - ' . now()->format('Y-m-d H:i:s'),
                'description' => 'Automatically created on first access to preserve device configuration',
                'backup_data' => $parameters,
                'is_auto' => true,
                'parameter_count' => count($parameters),
            ]);

            $device->update([
                'initial_backup_created' => true,
                'last_backup_at' => now(),
            ]);
        }

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
        $tasks = $device->tasks()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
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

                // Note: Vendor-specific parameters (X_000631_*) intentionally excluded
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
     * Get all backups for a device
     */
    public function getBackups(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $backups = $device->configBackups()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'name' => $backup->name,
                    'description' => $backup->description,
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
    public function restoreBackup(string $id, int $backupId): JsonResponse
    {
        $device = Device::findOrFail($id);
        $backup = $device->configBackups()->findOrFail($backupId);

        // Filter parameters to only include writable ones
        $writableParams = collect($backup->backup_data)
            ->filter(function ($param) {
                return $param['writable'] ?? false;
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
                'error' => 'No writable parameters found in backup',
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

        return response()->json([
            'task' => $task,
            'message' => 'Configuration restore initiated',
            'writable_params_count' => count($writableParams),
        ]);
    }

    /**
     * Get all port mappings for a device
     */
    public function getPortMappings(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        // Detect manufacturer for device-specific instance handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        if ($isCalix) {
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanIdx = 3;
            $connIdx = 2;
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanIdx = 1;
            $connIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model - not yet implemented for Calix
            $portMappingPrefix = 'Device.NAT.PortMapping.';
        } else {
            // InternetGatewayDevice model
            $portMappingPrefix = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.PortMapping.";
        }

        // Get all port mapping parameters
        $portMappings = $device->parameters()
            ->where('name', 'LIKE', $portMappingPrefix . '%')
            ->get()
            ->groupBy(function ($param) use ($portMappingPrefix) {
                // Extract instance number from parameter name
                $name = str_replace($portMappingPrefix, '', $param->name);
                $parts = explode('.', $name);
                return $parts[0]; // Instance number
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
                // Only include enabled mappings with required fields
                return isset($mapping['PortMappingEnabled']) &&
                    $mapping['PortMappingEnabled'] === '1';
            })
            ->values();

        return response()->json([
            'port_mappings' => $portMappings,
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

        // Detect manufacturer for device-specific instance handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        if ($isCalix) {
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanIdx = 3;
            $connIdx = 2;
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanIdx = 1;
            $connIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model
            $portMappingPrefix = 'Device.NAT.PortMapping';
        } else {
            // InternetGatewayDevice model
            $portMappingPrefix = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.PortMapping";
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

        // Build parameters for the new port mapping
        $parameters = [
            "{$portMappingPrefix}.{$instance}.PortMappingEnabled" => [
                'value' => true,
                'type' => 'xsd:boolean',
            ],
            "{$portMappingPrefix}.{$instance}.PortMappingDescription" => [
                'value' => $validated['description'],
                'type' => 'xsd:string',
            ],
            "{$portMappingPrefix}.{$instance}.PortMappingProtocol" => [
                'value' => $validated['protocol'],
                'type' => 'xsd:string',
            ],
            "{$portMappingPrefix}.{$instance}.ExternalPort" => [
                'value' => $validated['external_port'],
                'type' => 'xsd:unsignedInt',
            ],
            "{$portMappingPrefix}.{$instance}.ExternalPortEndRange" => [
                'value' => $validated['external_port'],
                'type' => 'xsd:unsignedInt',
            ],
            "{$portMappingPrefix}.{$instance}.InternalPort" => [
                'value' => $validated['internal_port'],
                'type' => 'xsd:unsignedInt',
            ],
            "{$portMappingPrefix}.{$instance}.InternalClient" => [
                'value' => $validated['internal_client'],
                'type' => 'xsd:string',
            ],
        ];

        // Create task to add the port mapping
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => $parameters,
        ]);

        // Trigger connection request
        $this->connectionRequestService->sendConnectionRequest($device);

        return response()->json([
            'task' => $task,
            'message' => 'Port mapping creation initiated',
            'instance' => $instance,
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

        // Detect manufacturer for device-specific instance handling
        $isCalix = strtolower($device->manufacturer) === 'calix' ||
            strtoupper($device->oui) === 'D0768F';

        if ($isCalix) {
            // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.2
            $wanIdx = 3;
            $connIdx = 2;
        } else {
            // Standard devices use WANDevice.1.WANConnectionDevice.1.WANIPConnection.1
            $wanIdx = 1;
            $connIdx = 1;
        }

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model
            $objectName = "Device.NAT.PortMapping.{$validated['instance']}.";
        } else {
            // InternetGatewayDevice model
            $objectName = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.PortMapping.{$validated['instance']}.";
        }

        // Create task to delete the port mapping
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'delete_object',
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
     * Start WiFi interference scan (neighboring networks scan)
     */
    public function startWiFiScan(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model - standard WiFi diagnostics
            $diagnosticParam = 'Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState';
        } else {
            // InternetGatewayDevice model - Calix vendor extension
            $diagnosticParam = 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState';
        }

        // Create task to trigger the scan
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
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

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model
            $resultPrefix = 'Device.WiFi.NeighboringWiFiDiagnostic.Result.';
            $stateParam = 'Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState';
        } else {
            // InternetGatewayDevice model - Calix vendor extension
            $resultPrefix = 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.Result.';
            $stateParam = 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState';
        }

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
     * Start a TR-143 SpeedTest (Download/Upload diagnostics)
     */
    public function startSpeedTest(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'test_type' => 'required|in:download,upload,both',
            'download_url' => 'nullable|url',
            'upload_url' => 'nullable|url',
            'number_of_connections' => 'nullable|integer|min:1|max:10',
            'test_duration' => 'nullable|integer|min:1|max:60',
        ]);

        $testType = $validated['test_type'];
        // Use smaller test file (10MB) to avoid long downloads when TimeBasedTestDuration isn't respected
        $downloadUrl = $validated['download_url'] ?? 'http://ipv4.download.thinkbroadband.com/10MB.zip';
        $uploadUrl = $validated['upload_url'] ?? 'http://tr143.hay.net/upload';

        // USS uses 2 connections for download, 10 for upload
        $downloadConnections = $validated['number_of_connections'] ?? 2;
        $uploadConnections = $validated['number_of_connections'] ?? 10;
        $testDuration = $validated['test_duration'] ?? 12; // USS uses 12 seconds
        $uploadFileSize = 1858291200; // ~1.7GB (USS default)

        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        $tasks = [];

        // Download Test
        if ($testType === 'download' || $testType === 'both') {
            $downloadPrefix = $isDevice2
                ? 'Device.IP.Diagnostics.DownloadDiagnostics'
                : 'InternetGatewayDevice.DownloadDiagnostics';

            // Based on USS trace analysis: USS sends DiagnosticsState + DownloadURL in the final call
            // Sending additional parameters in the same call causes device rejection
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
                'status' => 'pending',
                'parameters' => $downloadParams,
            ]);

            $tasks[] = $downloadTask;
        }

        // Upload Test
        if ($testType === 'upload' || $testType === 'both') {
            $uploadPrefix = $isDevice2
                ? 'Device.IP.Diagnostics.UploadDiagnostics'
                : 'InternetGatewayDevice.UploadDiagnostics';

            // Based on USS trace analysis: USS sends DiagnosticsState + UploadURL + TestFileLength in final call
            // Sending additional parameters causes device rejection on some models
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
                    'value' => (string) $uploadFileSize,
                    'type' => 'xsd:unsignedInt',
                ],
            ];

            $uploadTask = Task::create([
                'device_id' => $device->id,
                'task_type' => 'upload_diagnostics',
                'status' => 'pending',
                'parameters' => $uploadParams,
            ]);

            $tasks[] = $uploadTask;
        }

        // Trigger connection request to start the test
        $this->triggerConnectionRequestForTask($device);

        return response()->json([
            'tasks' => $tasks,
            'message' => 'SpeedTest initiated',
            'test_type' => $testType,
        ]);
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
