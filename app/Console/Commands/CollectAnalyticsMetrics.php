<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceHealthSnapshot;
use App\Models\ParameterHistory;
use Illuminate\Console\Command;

class CollectAnalyticsMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect device health snapshots and parameter history for analytics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Collecting analytics metrics...');

        $devicesProcessed = 0;
        $snapshotsCreated = 0;
        $parametersRecorded = 0;

        // Get all devices
        $devices = Device::all();

        foreach ($devices as $device) {
            // OPTIMIZED: Fetch ALL parameters for this device ONCE (uses device_id index)
            // Then filter in PHP instead of running 8+ LIKE queries per device
            $allParams = $device->parameters()->get(['name', 'value']);

            // Create device health snapshot
            $snapshot = DeviceHealthSnapshot::create([
                'device_id' => $device->id,
                'is_online' => $device->online,
                'last_inform_at' => $device->last_inform,
                'connection_uptime_seconds' => $device->online && $device->last_inform
                    ? now()->diffInSeconds($device->last_inform)
                    : null,
                'inform_interval' => $this->getInformInterval($allParams),
                'snapshot_at' => now(),
            ]);

            if ($snapshot) {
                $snapshotsCreated++;
            }

            // Record key parameters for trending (using pre-fetched params)
            $keyParameters = $this->getKeyParameters($allParams);
            foreach ($keyParameters as $paramName => $paramValue) {
                if ($paramValue !== null) {
                    ParameterHistory::create([
                        'device_id' => $device->id,
                        'parameter_name' => $paramName,
                        'parameter_value' => $paramValue,
                        'parameter_type' => 'xsd:string',
                        'recorded_at' => now(),
                    ]);
                    $parametersRecorded++;
                }
            }

            $devicesProcessed++;
        }

        $this->info("✓ Processed {$devicesProcessed} devices");
        $this->info("✓ Created {$snapshotsCreated} health snapshots");
        $this->info("✓ Recorded {$parametersRecorded} parameter history entries");

        // Clean up old data (keep 1 year)
        $this->cleanupOldData();

        $this->info('Analytics metrics collection completed successfully!');

        return 0;
    }

    /**
     * Get inform interval from pre-fetched device parameters
     * OPTIMIZED: Uses PHP str_contains instead of SQL LIKE with leading wildcards
     */
    private function getInformInterval($allParams): ?int
    {
        $interval = $allParams->first(function ($param) {
            return str_contains($param->name, 'PeriodicInformInterval');
        });

        return $interval ? (int) $interval->value : null;
    }

    /**
     * Get key parameters to track for trending
     * OPTIMIZED: Uses pre-fetched params with PHP filtering instead of SQL LIKE queries
     */
    private function getKeyParameters($allParams): array
    {
        $params = [];

        // WAN IP Address (must contain both ExternalIPAddress AND WANIPConnection)
        $wanIp = $allParams->first(function ($param) {
            return str_contains($param->name, 'ExternalIPAddress')
                && str_contains($param->name, 'WANIPConnection');
        });
        if ($wanIp) {
            $params['WAN.ExternalIPAddress'] = $wanIp->value;
        }

        // DSL Sync Rates (if applicable)
        $downstreamRate = $allParams->first(function ($param) {
            return str_contains($param->name, 'DownstreamCurrRate');
        });
        if ($downstreamRate) {
            $params['DSL.DownstreamCurrRate'] = $downstreamRate->value;
        }

        $upstreamRate = $allParams->first(function ($param) {
            return str_contains($param->name, 'UpstreamCurrRate');
        });
        if ($upstreamRate) {
            $params['DSL.UpstreamCurrRate'] = $upstreamRate->value;
        }

        // WiFi Signal Strength (first WLAN)
        $signalStrength = $allParams->first(function ($param) {
            return (str_contains($param->name, 'WLANConfiguration.1') && str_contains($param->name, 'SignalStrength'))
                || (str_contains($param->name, 'WiFi') && str_contains($param->name, 'SignalStrength'));
        });
        if ($signalStrength) {
            $params['WiFi.SignalStrength'] = $signalStrength->value;
        }

        // Device Temperature (if available)
        $temperature = $allParams->first(function ($param) {
            return str_contains($param->name, 'Temperature');
        });
        if ($temperature) {
            $params['Device.Temperature'] = $temperature->value;
        }

        // CPU Usage (if available)
        $cpuUsage = $allParams->first(function ($param) {
            return str_contains($param->name, 'CPUUsage');
        });
        if ($cpuUsage) {
            $params['Device.CPUUsage'] = $cpuUsage->value;
        }

        // Memory Usage (if available)
        $memoryUsage = $allParams->first(function ($param) {
            return str_contains($param->name, 'MemoryStatus')
                || str_contains($param->name, 'MemoryUsage');
        });
        if ($memoryUsage) {
            $params['Device.MemoryUsage'] = $memoryUsage->value;
        }

        return $params;
    }

    /**
     * Clean up old analytics data
     */
    private function cleanupOldData(): void
    {
        $oneYearAgo = now()->subYear();

        // Delete health snapshots older than 1 year
        $deletedSnapshots = DeviceHealthSnapshot::where('snapshot_at', '<', $oneYearAgo)->delete();

        // Delete parameter history older than 1 year
        $deletedHistory = ParameterHistory::where('recorded_at', '<', $oneYearAgo)->delete();

        if ($deletedSnapshots > 0 || $deletedHistory > 0) {
            $this->info("✓ Cleaned up {$deletedSnapshots} old snapshots and {$deletedHistory} old parameter history entries");
        }
    }
}
