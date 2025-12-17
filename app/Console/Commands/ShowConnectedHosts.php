<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class ShowConnectedHosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:hosts
                            {device : Device ID or serial number}
                            {--active-only : Show only active hosts (default)}
                            {--all : Show all hosts including inactive}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show connected hosts/devices for a gateway, grouped by access point';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deviceId = $this->argument('device');
        $showAll = $this->option('all');
        $jsonOutput = $this->option('json');

        // Find the device
        $device = Device::find($deviceId);
        if (!$device) {
            $device = Device::where('serial_number', $deviceId)->first();
        }
        if (!$device) {
            $device = Device::where('id', 'LIKE', "%{$deviceId}%")->first();
        }

        if (!$device) {
            $this->error("Device not found: {$deviceId}");
            return 1;
        }

        $this->info("Device: {$device->id} ({$device->product_class})");
        $this->info("Serial: {$device->serial_number}");
        $this->newLine();

        // Build mesh AP mapping (Calix uses MAC, Nokia uses BeaconInfo index)
        $meshApMap = [];
        $meshApMacs = [];
        $nokiaBeaconMap = [];

        if ($device->isCalix()) {
            $meshDevices = Device::where('product_class', 'LIKE', '%Mesh%')
                ->whereHas('parameters', function ($q) use ($device) {
                    $q->where('name', 'LIKE', '%GatewayInfo.SerialNumber')
                        ->where('value', $device->serial_number);
                })
                ->get();

            foreach ($meshDevices as $meshDev) {
                $meshMacParam = $meshDev->parameters()->where('name', 'LIKE', '%WapHostInfo.MACAddress')->first();
                if (!$meshMacParam) {
                    $meshMacParam = $meshDev->parameters()->where('name', 'LIKE', '%WANEthernetInterfaceConfig.MACAddress')->first();
                }
                if ($meshMacParam && $meshMacParam->value) {
                    $mac = strtolower($meshMacParam->value);
                    $meshApMap[$mac] = [
                        'id' => $meshDev->id,
                        'serial' => $meshDev->serial_number,
                        'type' => $meshDev->product_class,
                    ];
                    $meshApMacs[] = strtolower(str_replace([':', '-'], '', $mac));
                }
            }

            if (count($meshApMap) > 0) {
                $this->info("Mesh APs connected to this gateway:");
                foreach ($meshApMap as $mac => $info) {
                    $this->line("  {$mac} => {$info['serial']} ({$info['type']})");
                }
                $this->newLine();
            }
        } elseif ($device->isNokia()) {
            // Nokia: Build beacon index map from X_ALU-COM_BeaconInfo.Beacon.N
            $beaconParams = $device->parameters()
                ->where('name', 'LIKE', '%X_ALU-COM_BeaconInfo.Beacon.%')
                ->get();

            $beaconData = [];
            foreach ($beaconParams as $param) {
                if (preg_match('/BeaconInfo\.Beacon\.(\d+)\.(.+)/', $param->name, $m)) {
                    $idx = $m[1];
                    $field = $m[2];
                    if (!isset($beaconData[$idx])) {
                        $beaconData[$idx] = [];
                    }
                    $beaconData[$idx][$field] = $param->value;
                }
            }

            foreach ($beaconData as $idx => $info) {
                $serial = $info['SerialNumber'] ?? null;
                $mac = strtolower($info['MACAddress'] ?? '');
                if ($serial) {
                    $beaconDevice = Device::where('serial_number', $serial)->first();
                    $nokiaBeaconMap[$idx] = [
                        'id' => $beaconDevice?->id,
                        'serial' => $serial,
                        'mac' => $mac,
                        'type' => $beaconDevice?->product_class ?? 'Beacon',
                        'status' => $info['Status'] ?? 'Unknown',
                    ];
                    if ($mac) {
                        $meshApMacs[] = strtolower(str_replace([':', '-'], '', $mac));
                    }
                }
            }

            if (count($nokiaBeaconMap) > 0) {
                $this->info("Beacons connected to this gateway:");
                foreach ($nokiaBeaconMap as $idx => $info) {
                    $this->line("  Beacon {$idx}: {$info['serial']} ({$info['type']}) - {$info['status']}");
                }
                $this->newLine();
            }
        }

        // Get gateway's LAN MAC
        $gatewayLanMac = $device->parameters()
            ->where('name', 'LIKE', '%LANEthernetInterfaceConfig.%.MACAddress%')
            ->first();
        $gatewayMac = $gatewayLanMac ? strtolower($gatewayLanMac->value) : '';

        // Get all host entries
        $hostParams = $device->parameters()
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%Hosts.Host.%')
                    ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
            })
            ->get();

        // Organize by host number
        $hosts = [];
        foreach ($hostParams as $param) {
            if (preg_match('/Host\.(\d+)\.(.+)/', $param->name, $matches)) {
                $hostNum = $matches[1];
                $field = $matches[2];
                if (!isset($hosts[$hostNum])) {
                    $hosts[$hostNum] = [];
                }
                $hosts[$hostNum][$field] = $param->value;
            }
        }

        // Filter hosts
        $filteredHosts = [];
        foreach ($hosts as $hostNum => $host) {
            // Filter inactive unless --all
            if (!$showAll) {
                $active = $host['Active'] ?? '0';
                if ($active !== 'true' && $active !== '1') {
                    continue;
                }
            }

            // Filter out mesh APs themselves
            $hostMac = $host['MACAddress'] ?? '';
            $normalizedMac = strtolower(str_replace([':', '-'], '', $hostMac));
            if (in_array($normalizedMac, $meshApMacs)) {
                continue;
            }

            // Determine connected AP
            // Calix uses X_000631_AccessPoint (MAC), Nokia uses X_ALU-COM_IsBeacon (index)
            $apMac = strtolower($host['X_000631_AccessPoint'] ?? '');
            $isBeacon = $host['X_ALU-COM_IsBeacon'] ?? null;

            if (!empty($nokiaBeaconMap) && $isBeacon !== null) {
                // Nokia: Use beacon index
                if (isset($nokiaBeaconMap[$isBeacon])) {
                    $beaconInfo = $nokiaBeaconMap[$isBeacon];
                    $host['_connected_to'] = "{$beaconInfo['type']} ({$beaconInfo['serial']})";
                    $host['_connected_type'] = 'mesh';
                    $host['_mesh_device_id'] = $beaconInfo['id'];
                } else {
                    $host['_connected_to'] = 'Gateway';
                    $host['_connected_type'] = 'gateway';
                }
            } elseif (empty($apMac) || $apMac === $gatewayMac) {
                $host['_connected_to'] = 'Gateway';
                $host['_connected_type'] = 'gateway';
            } elseif (isset($meshApMap[$apMac])) {
                // Calix: Use MAC
                $meshInfo = $meshApMap[$apMac];
                $host['_connected_to'] = "{$meshInfo['type']} ({$meshInfo['serial']})";
                $host['_connected_type'] = 'mesh';
                $host['_mesh_device_id'] = $meshInfo['id'];
            } else {
                $host['_connected_to'] = "Unknown AP ({$apMac})";
                $host['_connected_type'] = 'unknown';
            }

            $filteredHosts[] = $host;
        }

        if ($jsonOutput) {
            $this->line(json_encode([
                'device' => [
                    'id' => $device->id,
                    'serial' => $device->serial_number,
                    'product_class' => $device->product_class,
                ],
                'mesh_aps' => $meshApMap,
                'nokia_beacons' => $nokiaBeaconMap,
                'hosts' => $filteredHosts,
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        // Group hosts by connected AP
        $hostsByAp = [];
        foreach ($filteredHosts as $host) {
            $ap = $host['_connected_to'];
            if (!isset($hostsByAp[$ap])) {
                $hostsByAp[$ap] = [];
            }
            $hostsByAp[$ap][] = $host;
        }

        // Display
        $totalHosts = count($filteredHosts);
        $this->info("Connected Hosts ({$totalHosts}):");
        $this->line(str_repeat('-', 100));

        // Show gateway hosts first
        if (isset($hostsByAp['Gateway'])) {
            $gatewayHosts = $hostsByAp['Gateway'];
            $this->info("Gateway (direct connection) - " . count($gatewayHosts) . " devices:");
            $this->displayHostTable($gatewayHosts);
            unset($hostsByAp['Gateway']);
        }

        // Show mesh AP hosts
        foreach ($hostsByAp as $ap => $apHosts) {
            $this->newLine();
            $this->info("{$ap} - " . count($apHosts) . " devices:");
            $this->displayHostTable($apHosts);
        }

        return 0;
    }

    /**
     * Display hosts in a table format
     */
    private function displayHostTable(array $hosts): void
    {
        $headers = ['Name', 'IP Address', 'MAC Address', 'Interface', 'Active'];
        $rows = [];

        foreach ($hosts as $host) {
            $rows[] = [
                $host['HostName'] ?? 'Unknown',
                $host['IPAddress'] ?? '-',
                $host['MACAddress'] ?? '-',
                $host['InterfaceType'] ?? '-',
                ($host['Active'] === 'true' || $host['Active'] === '1') ? 'Yes' : 'No',
            ];
        }

        $this->table($headers, $rows);
    }
}
