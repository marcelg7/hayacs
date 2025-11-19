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
            // Create device health snapshot
            $snapshot = DeviceHealthSnapshot::create([
                'device_id' => $device->id,
                'is_online' => $device->online,
                'last_inform_at' => $device->last_inform,
                'connection_uptime_seconds' => $device->online && $device->last_inform
                    ? now()->diffInSeconds($device->last_inform)
                    : null,
                'inform_interval' => $this->getInformInterval($device),
                'snapshot_at' => now(),
            ]);

            if ($snapshot) {
                $snapshotsCreated++;
            }

            // Record key parameters for trending
            $keyParameters = $this->getKeyParameters($device);
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
     * Get inform interval from device parameters
     */
    private function getInformInterval(Device $device): ?int
    {
        // Try to get PeriodicInformInterval parameter
        $interval = $device->parameters()
            ->where('name', 'LIKE', '%PeriodicInformInterval%')
            ->first();

        return $interval ? (int) $interval->value : null;
    }

    /**
     * Get key parameters to track for trending
     */
    private function getKeyParameters(Device $device): array
    {
        $params = [];

        // WAN IP Address
        $wanIp = $device->parameters()
            ->where('name', 'LIKE', '%ExternalIPAddress%')
            ->where('name', 'LIKE', '%WANIPConnection%')
            ->first();
        if ($wanIp) {
            $params['WAN.ExternalIPAddress'] = $wanIp->value;
        }

        // DSL Sync Rates (if applicable)
        $downstreamRate = $device->parameters()
            ->where('name', 'LIKE', '%DownstreamCurrRate%')
            ->first();
        if ($downstreamRate) {
            $params['DSL.DownstreamCurrRate'] = $downstreamRate->value;
        }

        $upstreamRate = $device->parameters()
            ->where('name', 'LIKE', '%UpstreamCurrRate%')
            ->first();
        if ($upstreamRate) {
            $params['DSL.UpstreamCurrRate'] = $upstreamRate->value;
        }

        // WiFi Signal Strength (first WLAN)
        $signalStrength = $device->parameters()
            ->where('name', 'LIKE', '%WLANConfiguration.1%SignalStrength%')
            ->orWhere('name', 'LIKE', '%WiFi%SignalStrength%')
            ->first();
        if ($signalStrength) {
            $params['WiFi.SignalStrength'] = $signalStrength->value;
        }

        // Device Temperature (if available)
        $temperature = $device->parameters()
            ->where('name', 'LIKE', '%Temperature%')
            ->first();
        if ($temperature) {
            $params['Device.Temperature'] = $temperature->value;
        }

        // CPU Usage (if available)
        $cpuUsage = $device->parameters()
            ->where('name', 'LIKE', '%CPUUsage%')
            ->first();
        if ($cpuUsage) {
            $params['Device.CPUUsage'] = $cpuUsage->value;
        }

        // Memory Usage (if available)
        $memoryUsage = $device->parameters()
            ->where('name', 'LIKE', '%MemoryStatus%')
            ->orWhere('name', 'LIKE', '%MemoryUsage%')
            ->first();
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
