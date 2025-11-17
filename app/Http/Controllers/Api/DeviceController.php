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

        return response()->json($device);
    }

    /**
     * Get all parameters for a device
     */
    public function parameters(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $parameters = $device->parameters()
            ->orderBy('name')
            ->get();

        return response()->json($parameters);
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
     */
    private function buildDiscoveryParameters(string $dataModel): array
    {
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            return [
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
            }

            // Hosts - Query up to 20 hosts if any exist
            $hostCount = (int) ($discoveryResults['Device.Hosts.HostNumberOfEntries']['value'] ?? 0);
            for ($i = 1; $i <= min($hostCount, 20); $i++) {
                $parameters[] = "Device.Hosts.Host.{$i}.HostName";
                $parameters[] = "Device.Hosts.Host.{$i}.IPAddress";
                $parameters[] = "Device.Hosts.Host.{$i}.PhysAddress";
                $parameters[] = "Device.Hosts.Host.{$i}.Active";
            }
        } else {
            // InternetGatewayDevice data model

            // WAN - Query manufacturer-specific instances
            // NumberOfEntries doesn't tell us which instance numbers exist, just the count
            $wanDeviceCount = (int) ($discoveryResults['InternetGatewayDevice.WANDeviceNumberOfEntries']['value'] ?? 0);

            if ($wanDeviceCount > 0) {
                if ($isCalix) {
                    // Calix uses WANDevice.3.WANConnectionDevice.1.WANIPConnection.14
                    $wanIdx = 3;
                    $connIdx = 14;
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ExternalIPAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.SubnetMask";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DefaultGateway";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.DNSServers";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.MACAddress";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.ConnectionStatus";
                    $parameters[] = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.1.WANIPConnection.{$connIdx}.Uptime";
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
                }
            }

            // WiFi - Use discovered count, query enabled instances
            $wlanCount = (int) ($discoveryResults['InternetGatewayDevice.LANDevice.1.LANWLANConfigurationNumberOfEntries']['value'] ?? 0);

            // For Calix, query instances 1-8 (2.4GHz) and 9-16 (5GHz)
            // Adjust range based on discovered count
            $wlanInstances = [];
            if ($wlanCount > 0) {
                // Query instances 1-8 (2.4GHz SSIDs)
                for ($i = 1; $i <= min(8, $wlanCount); $i++) {
                    $wlanInstances[] = $i;
                }
                // Query instances 9-16 (5GHz SSIDs) if count suggests they exist
                if ($wlanCount >= 9) {
                    for ($i = 9; $i <= min(16, $wlanCount); $i++) {
                        $wlanInstances[] = $i;
                    }
                }
            }

            foreach ($wlanInstances as $i) {
                // Basic WiFi parameters
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Enable";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.SSID";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Status";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Standard";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.BSSID";

                // Channel settings
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.Channel";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.AutoChannelEnable";

                // Security settings
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.BeaconType";
                // Query the vendor read parameter where device stores the password
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.PreSharedKey.1.X_000631_KeyPassphrase";

                // Radio and visibility
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.RadioEnabled";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.SSIDAdvertisementEnabled";

                // Bandwidth (Calix-specific)
                $parameters[] = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}.X_000631_OperatingChannelBandwidth";
            }

            // Hosts - Query up to 20 hosts if any exist
            $hostCount = (int) ($discoveryResults['InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries']['value'] ?? 0);
            for ($i = 1; $i <= min($hostCount, 20); $i++) {
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.HostName";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.IPAddress";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.MACAddress";
                $parameters[] = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}.Active";
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
