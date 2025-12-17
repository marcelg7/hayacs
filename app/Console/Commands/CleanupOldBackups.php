<?php

namespace App\Console\Commands;

use App\Models\ConfigBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupOldBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backups:cleanup
                            {--daily-keep=2 : Number of recent daily backups to keep per device}
                            {--weekly-age=7 : Age in days for a backup to be considered weekly}
                            {--weekly-keep=1 : Number of weekly backups to keep per device}
                            {--user-days=90 : Retention days for user-created backups}
                            {--dry-run : Show what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old backups with smart retention (Initial: Never, User: 90d, Auto: 2 daily + 1 weekly)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dailyKeep = (int) $this->option('daily-keep');
        $weeklyAge = (int) $this->option('weekly-age');
        $weeklyKeep = (int) $this->option('weekly-keep');
        $userDays = (int) $this->option('user-days');
        $dryRun = $this->option('dry-run');

        $this->info("Backup Cleanup - Smart Retention Policy");
        $this->info("========================================");
        $this->info("  Initial backups: Never deleted");
        $this->info("  User backups: {$userDays} days");
        $this->info("  Auto backups: {$dailyKeep} daily + {$weeklyKeep} weekly ({$weeklyAge}+ days old)");
        if ($dryRun) {
            $this->warn("  DRY RUN MODE - No data will be deleted");
        }
        $this->newLine();

        $totalDeleted = 0;

        // 1. Clean up user-created backups older than X days
        $userCutoff = now()->subDays($userDays);
        $userCount = DB::table('config_backups')
            ->where('created_at', '<', $userCutoff)
            ->where('is_auto', false)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', 'not like', '%first TR-069 connection%');
            })
            ->count();

        $this->info("User backups older than {$userDays} days: {$userCount}");

        if (!$dryRun && $userCount > 0) {
            $deleted = DB::table('config_backups')
                ->where('created_at', '<', $userCutoff)
                ->where('is_auto', false)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', 'not like', '%first TR-069 connection%');
                })
                ->delete();

            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} user backups");
        }

        // 2. Clean up auto backups with smart retention (2 daily + 1 weekly per device)
        $this->newLine();
        $this->info("Processing auto backups (keeping {$dailyKeep} daily + {$weeklyKeep} weekly per device)...");

        // Get all devices with auto backups (excluding initial backups)
        $devicesWithBackups = DB::table('config_backups')
            ->select('device_id', DB::raw('COUNT(*) as backup_count'))
            ->where('is_auto', true)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', 'not like', '%first TR-069 connection%');
            })
            ->groupBy('device_id')
            ->having('backup_count', '>', $dailyKeep + $weeklyKeep)
            ->get();

        $this->info("  Devices with excess auto backups: {$devicesWithBackups->count()}");

        $autoDeleted = 0;
        $weeklyCutoff = now()->subDays($weeklyAge);

        foreach ($devicesWithBackups as $device) {
            // Get IDs of backups to KEEP for this device
            $keepIds = collect();

            // Keep the N most recent backups (daily)
            $dailyIds = DB::table('config_backups')
                ->where('device_id', $device->device_id)
                ->where('is_auto', true)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', 'not like', '%first TR-069 connection%');
                })
                ->orderByDesc('created_at')
                ->limit($dailyKeep)
                ->pluck('id');

            $keepIds = $keepIds->merge($dailyIds);

            // Keep the N oldest backups that are at least X days old (weekly)
            $weeklyIds = DB::table('config_backups')
                ->where('device_id', $device->device_id)
                ->where('is_auto', true)
                ->where('created_at', '<', $weeklyCutoff)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', 'not like', '%first TR-069 connection%');
                })
                ->whereNotIn('id', $keepIds) // Don't double-count if recent backup is also old
                ->orderByDesc('created_at') // Keep most recent weekly, not oldest
                ->limit($weeklyKeep)
                ->pluck('id');

            $keepIds = $keepIds->merge($weeklyIds);

            // Delete all other auto backups for this device
            if (!$dryRun) {
                $deleted = DB::table('config_backups')
                    ->where('device_id', $device->device_id)
                    ->where('is_auto', true)
                    ->where(function ($query) {
                        $query->whereNull('description')
                            ->orWhere('description', 'not like', '%first TR-069 connection%');
                    })
                    ->whereNotIn('id', $keepIds)
                    ->delete();

                $autoDeleted += $deleted;
            } else {
                // Count what would be deleted
                $wouldDelete = DB::table('config_backups')
                    ->where('device_id', $device->device_id)
                    ->where('is_auto', true)
                    ->where(function ($query) {
                        $query->whereNull('description')
                            ->orWhere('description', 'not like', '%first TR-069 connection%');
                    })
                    ->whereNotIn('id', $keepIds)
                    ->count();

                $autoDeleted += $wouldDelete;
            }
        }

        $totalDeleted += $autoDeleted;

        if ($dryRun) {
            $this->info("  Would delete {$autoDeleted} auto backups");
        } else {
            $this->info("  Deleted {$autoDeleted} auto backups");
        }

        // Summary
        $this->newLine();
        if ($dryRun) {
            $this->warn("DRY RUN - Would have deleted {$totalDeleted} total backups");
        } else {
            $this->info("Cleanup complete: Deleted {$totalDeleted} backups");
        }

        // Show retention summary
        $this->showRetentionSummary($userDays, $dailyKeep, $weeklyKeep);

        if (!$dryRun && $totalDeleted > 0) {
            Log::info('Backup cleanup completed', [
                'deleted_count' => $totalDeleted,
                'auto_deleted' => $autoDeleted,
                'daily_keep' => $dailyKeep,
                'weekly_keep' => $weeklyKeep,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Show current retention summary
     */
    private function showRetentionSummary(int $userDays, int $dailyKeep, int $weeklyKeep): void
    {
        $totalBackups = ConfigBackup::count();
        $initialBackups = DB::table('config_backups')
            ->where('description', 'like', '%first TR-069 connection%')
            ->count();
        $userBackups = DB::table('config_backups')
            ->where('is_auto', false)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', 'not like', '%first TR-069 connection%');
            })
            ->count();
        $autoBackups = DB::table('config_backups')
            ->where('is_auto', true)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', 'not like', '%first TR-069 connection%');
            })
            ->count();

        // Calculate expected auto backups
        $deviceCount = DB::table('config_backups')
            ->where('is_auto', true)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', 'not like', '%first TR-069 connection%');
            })
            ->distinct('device_id')
            ->count('device_id');

        $expectedAuto = $deviceCount * ($dailyKeep + $weeklyKeep);

        $this->newLine();
        $this->info("Retention summary:");
        $this->info("  Total backups: {$totalBackups}");
        $this->info("  ├─ Initial (protected): {$initialBackups}");
        $this->info("  ├─ User created ({$userDays}d): {$userBackups}");
        $this->info("  └─ Auto ({$dailyKeep}d+{$weeklyKeep}w): {$autoBackups}");
        $this->info("  Expected auto after cleanup: ~{$expectedAuto} ({$deviceCount} devices × " . ($dailyKeep + $weeklyKeep) . ")");
    }
}
