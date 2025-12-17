<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Parameter;
use App\Services\ConnectionRequestService;
use Illuminate\Console\Command;

class SetupMeshPortForwards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mesh:setup-port-forwards
                            {--device= : Specific mesh device ID to setup}
                            {--scan : Scan for existing port forwards and update mesh devices}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup port forwards on gateways for mesh APs to enable connection requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $scanOnly = $this->option('scan');
        $specificDevice = $this->option('device');

        if ($scanOnly) {
            return $this->scanExistingPortForwards($dryRun);
        }

        if ($specificDevice) {
            return $this->setupSingleDevice($specificDevice, $dryRun);
        }

        return $this->setupAllMeshDevices($dryRun);
    }

    /**
     * Scan existing port forwards and update mesh devices with their forwarded URLs
     */
    protected function scanExistingPortForwards(bool $dryRun): int
    {
        $this->info('Scanning for existing mesh AP port forwards...');
        $updated = 0;

        // Method 1: Find port mappings with mesh device IDs in the description
        $this->info('Pass 1: Checking port mapping descriptions...');
        $portMappings = Parameter::where('name', 'LIKE', '%PortMapping.%.PortMappingDescription')
            ->where(function ($q) {
                $q->where('value', 'LIKE', '%-804Mesh-%')
                    ->orWhere('value', 'LIKE', '%-GigaMesh-%');
            })
            ->get();

        $this->info("  Found {$portMappings->count()} port mappings with mesh device IDs");

        foreach ($portMappings as $descParam) {
            $meshDeviceId = $descParam->value;
            $meshDevice = Device::find($meshDeviceId);

            if (!$meshDevice) {
                $this->warn("  Mesh device not found: {$meshDeviceId}");
                continue;
            }

            // Skip if already has forwarded URL
            if ($meshDevice->mesh_forwarded_url) {
                continue;
            }

            // Extract port mapping path to get other params
            if (!preg_match('/(.+\.PortMapping\.\d+)\.PortMappingDescription/', $descParam->name, $matches)) {
                continue;
            }
            $portMappingPath = $matches[1];
            $gateway = Device::find($descParam->device_id);

            if (!$gateway) {
                continue;
            }

            $result = $this->updateMeshFromPortMapping($meshDevice, $gateway, $portMappingPath, $dryRun);
            if ($result) {
                $updated++;
            }
        }

        // Method 2: Match mesh devices to port forwards by internal IP
        $this->info('Pass 2: Matching by internal IP address...');
        $meshDevices = Device::where(function ($q) {
            $q->whereRaw('LOWER(product_class) LIKE ?', ['%804mesh%'])
                ->orWhereRaw('LOWER(product_class) LIKE ?', ['%gigamesh%']);
        })
            ->whereNull('mesh_forwarded_url')
            ->get();

        $this->info("  Found {$meshDevices->count()} mesh devices without forwarded URLs");

        foreach ($meshDevices as $meshDevice) {
            // Extract internal IP from connection request URL
            $meshIp = $meshDevice->getMeshInternalIp();
            if (!$meshIp) {
                continue;
            }

            // Find the gateway for this mesh device
            $gateway = $meshDevice->getMeshGateway();
            if (!$gateway) {
                continue;
            }

            // Look for port mappings on the gateway that forward to this IP
            $internalClientParams = $gateway->parameters()
                ->where('name', 'LIKE', '%PortMapping.%.InternalClient')
                ->where('value', $meshIp)
                ->get();

            foreach ($internalClientParams as $clientParam) {
                // Extract port mapping path
                if (!preg_match('/(.+\.PortMapping\.\d+)\.InternalClient/', $clientParam->name, $matches)) {
                    continue;
                }
                $portMappingPath = $matches[1];

                // Check if internal port matches mesh device's CR port (60002 or 30005)
                $internalPortParam = $gateway->parameters()
                    ->where('name', "{$portMappingPath}.InternalPort")
                    ->first();

                if (!$internalPortParam) {
                    continue;
                }

                $internalPort = (int) $internalPortParam->value;
                // Mesh APs use port 60002 (GigaMesh) or 30005 (804Mesh) for connection requests
                if (!in_array($internalPort, [60002, 30005])) {
                    continue;
                }

                $result = $this->updateMeshFromPortMapping($meshDevice, $gateway, $portMappingPath, $dryRun);
                if ($result) {
                    $updated++;
                    break; // Found a match, move to next mesh device
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry run - no changes made");
        } else {
            $this->info("Updated {$updated} mesh devices with forwarded URLs");
        }

        return 0;
    }

    /**
     * Update mesh device with forwarded URL from a port mapping
     */
    protected function updateMeshFromPortMapping(Device $meshDevice, Device $gateway, string $portMappingPath, bool $dryRun): bool
    {
        // Get the external port
        $externalPortParam = $gateway->parameters()
            ->where('name', "{$portMappingPath}.ExternalPort")
            ->first();

        if (!$externalPortParam) {
            return false;
        }

        $externalPort = (int) $externalPortParam->value;
        $gatewayIp = $gateway->ip_address;

        // Try to get WAN IP if gateway IP is private
        if (empty($gatewayIp) || $this->isPrivateIp($gatewayIp)) {
            $wanIp = $gateway->parameters()
                ->where('name', 'LIKE', '%WANIPConnection%.ExternalIPAddress')
                ->where('value', '!=', '')
                ->where('value', 'NOT LIKE', '192.168.%')
                ->where('value', 'NOT LIKE', '10.%')
                ->first();
            if ($wanIp) {
                $gatewayIp = $wanIp->value;
            }
        }

        if (empty($gatewayIp) || $this->isPrivateIp($gatewayIp)) {
            $this->warn("  Gateway {$gateway->serial_number} has no public IP");
            return false;
        }

        // Build forwarded URL - use path from mesh device's connection request URL
        $path = '/CWMP/ConnectionRequest';
        if (preg_match('/https?:\/\/[^\/]+(.*)/', $meshDevice->connection_request_url, $m)) {
            $path = $m[1] ?: '/';
        }
        $forwardedUrl = "http://{$gatewayIp}:{$externalPort}{$path}";

        $this->info("  {$meshDevice->serial_number}: {$forwardedUrl}");

        if (!$dryRun) {
            $meshDevice->update([
                'mesh_forwarded_url' => $forwardedUrl,
                'mesh_forward_port' => $externalPort,
            ]);
        }

        return true;
    }

    /**
     * Setup port forward for a single mesh device
     */
    protected function setupSingleDevice(string $deviceId, bool $dryRun): int
    {
        $device = Device::find($deviceId);

        if (!$device) {
            $this->error("Device not found: {$deviceId}");
            return 1;
        }

        if (!$device->isMeshDevice()) {
            $this->error("Device is not a mesh AP: {$deviceId}");
            return 1;
        }

        $gateway = $device->getMeshGateway();
        if (!$gateway) {
            $this->error("Gateway not found for mesh AP");
            return 1;
        }

        $this->info("Mesh AP: {$device->serial_number}");
        $this->info("Gateway: {$gateway->serial_number} ({$gateway->display_name})");
        $this->info("Internal IP: {$device->getMeshInternalIp()}");
        $this->info("Calculated port: {$device->calculateMeshForwardPort()}");

        if ($dryRun) {
            $this->info("Dry run - no tasks created");
            return 0;
        }

        $tasks = $device->setupMeshPortForward();
        if ($tasks) {
            $this->info("Created task #{$tasks['add_object_task']->id} to add port mapping on gateway");
            $this->info("Send connection request to gateway to execute the task");
        } else {
            $this->error("Failed to create port forward tasks");
            return 1;
        }

        return 0;
    }

    /**
     * Setup port forwards for all mesh devices that need them
     */
    protected function setupAllMeshDevices(bool $dryRun): int
    {
        $this->info('Finding mesh APs that need port forwards...');

        $meshDevices = Device::whereRaw('LOWER(product_class) LIKE ?', ['%804mesh%'])
            ->orWhereRaw('LOWER(product_class) LIKE ?', ['%gigamesh%'])
            ->get();

        $this->info("Found {$meshDevices->count()} mesh devices");

        $needsSetup = $meshDevices->filter(fn($d) => $d->needsMeshPortForward());
        $this->info("{$needsSetup->count()} need port forward setup");

        $tasksCreated = 0;
        foreach ($needsSetup as $device) {
            $gateway = $device->getMeshGateway();
            if (!$gateway) {
                $this->warn("  {$device->serial_number}: No gateway found");
                continue;
            }

            $wanPath = $gateway->getWanConnectionPath();
            if (!$wanPath) {
                $this->warn("  {$device->serial_number}: Gateway {$gateway->serial_number} has no WAN path discovered");
                continue;
            }

            $this->info("  {$device->serial_number} -> {$gateway->serial_number}:{$device->calculateMeshForwardPort()}");

            if (!$dryRun) {
                $tasks = $device->setupMeshPortForward();
                if ($tasks) {
                    $tasksCreated++;
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry run - no tasks created");
        } else {
            $this->info("Created {$tasksCreated} port forward tasks");
        }

        return 0;
    }

    /**
     * Check if an IP address is private (RFC 1918)
     */
    private function isPrivateIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        if (($long & 0xFF000000) === 0x0A000000) return true;
        if (($long & 0xFFF00000) === 0xAC100000) return true;
        if (($long & 0xFFFF0000) === 0xC0A80000) return true;

        return false;
    }
}
