<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOfflineDevices extends Command
{
    protected $signature = 'devices:mark-offline
                            {--threshold=20 : Minutes since last inform to consider device offline}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Mark devices as offline if they haven\'t checked in recently';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun = $this->option('dry-run');

        $cutoff = now()->subMinutes($threshold);

        // Find devices that are marked online but haven't been seen recently
        $staleDevices = Device::where('online', true)
            ->where('last_inform', '<', $cutoff)
            ->get();

        $count = $staleDevices->count();

        if ($count === 0) {
            $this->info('No stale devices found.');
            return 0;
        }

        if ($dryRun) {
            $this->info("Would mark {$count} devices as offline (dry run)");
            return 0;
        }

        // Batch update for efficiency
        $updated = Device::where('online', true)
            ->where('last_inform', '<', $cutoff)
            ->update(['online' => false]);

        $this->info("Marked {$updated} devices as offline (threshold: {$threshold} minutes)");

        Log::info('Marked stale devices as offline', [
            'count' => $updated,
            'threshold_minutes' => $threshold,
        ]);

        return 0;
    }
}
