<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldData extends Command
{
    protected $signature = 'cleanup:old-data
                            {--dry-run : Show what would be deleted without deleting}
                            {--events-days=7 : Days to keep device events}
                            {--sessions-days=7 : Days to keep CWMP sessions}
                            {--tasks-days=30 : Days to keep completed/failed tasks}
                            {--backups-keep=3 : Number of backups to keep per device}
                            {--batch-size=10000 : Rows to delete per batch}';

    protected $description = 'Clean up old data from large tables to reduce database size';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $eventsDays = (int) $this->option('events-days');
        $sessionsDays = (int) $this->option('sessions-days');
        $tasksDays = (int) $this->option('tasks-days');
        $backupsKeep = (int) $this->option('backups-keep');
        $batchSize = (int) $this->option('batch-size');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $this->info('Starting database cleanup...');
        $totalDeleted = 0;

        // 1. Device Events - keep only X days
        $this->newLine();
        $this->info("=== Device Events (keeping {$eventsDays} days) ===");
        $eventsCount = DB::table('device_events')
            ->where('created_at', '<', now()->subDays($eventsDays))
            ->count();
        $this->line("  Found {$eventsCount} events older than {$eventsDays} days");

        if (!$dryRun && $eventsCount > 0) {
            $deleted = $this->batchDelete('device_events', 'created_at', now()->subDays($eventsDays), $batchSize);
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} device events");
        }

        // 2. CWMP Sessions - keep only X days
        $this->newLine();
        $this->info("=== CWMP Sessions (keeping {$sessionsDays} days) ===");
        $sessionsCount = DB::table('cwmp_sessions')
            ->where('created_at', '<', now()->subDays($sessionsDays))
            ->count();
        $this->line("  Found {$sessionsCount} sessions older than {$sessionsDays} days");

        if (!$dryRun && $sessionsCount > 0) {
            $deleted = $this->batchDelete('cwmp_sessions', 'created_at', now()->subDays($sessionsDays), $batchSize);
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} CWMP sessions");
        }

        // 3. Tasks - keep completed/failed for X days
        $this->newLine();
        $this->info("=== Completed/Failed Tasks (keeping {$tasksDays} days) ===");
        $tasksCount = DB::table('tasks')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->where('created_at', '<', now()->subDays($tasksDays))
            ->count();
        $this->line("  Found {$tasksCount} old completed/failed tasks");

        if (!$dryRun && $tasksCount > 0) {
            $deleted = 0;
            do {
                $batch = DB::table('tasks')
                    ->whereIn('status', ['completed', 'failed', 'cancelled'])
                    ->where('created_at', '<', now()->subDays($tasksDays))
                    ->limit($batchSize)
                    ->delete();
                $deleted += $batch;
                if ($batch > 0) {
                    $this->line("    Deleted batch of {$batch} tasks...");
                    usleep(100000); // 100ms delay
                }
            } while ($batch > 0);
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} tasks");
        }

        // 4. Laravel Sessions - prune old
        $this->newLine();
        $this->info("=== Laravel Sessions (keeping 7 days) ===");
        $laravelSessionsCount = DB::table('sessions')
            ->where('last_activity', '<', now()->subDays(7)->timestamp)
            ->count();
        $this->line("  Found {$laravelSessionsCount} old Laravel sessions");

        if (!$dryRun && $laravelSessionsCount > 0) {
            $deleted = DB::table('sessions')
                ->where('last_activity', '<', now()->subDays(7)->timestamp)
                ->delete();
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} Laravel sessions");
        }

        // 5. Config Backups - keep only X per device
        $this->newLine();
        $this->info("=== Config Backups (keeping {$backupsKeep} per device) ===");

        // Find devices with more than X backups
        $devicesWithExcess = DB::table('config_backups')
            ->select('device_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('device_id')
            ->having('cnt', '>', $backupsKeep)
            ->get();

        $backupsToDelete = 0;
        foreach ($devicesWithExcess as $device) {
            $backupsToDelete += $device->cnt - $backupsKeep;
        }

        $this->line("  Found {$devicesWithExcess->count()} devices with more than {$backupsKeep} backups");
        $this->line("  Total excess backups: {$backupsToDelete}");

        if (!$dryRun && $backupsToDelete > 0) {
            $deleted = 0;
            foreach ($devicesWithExcess as $device) {
                // Keep the X most recent backups (prioritize starred ones)
                $keepIds = DB::table('config_backups')
                    ->where('device_id', $device->device_id)
                    ->orderByDesc('is_starred')
                    ->orderByDesc('created_at')
                    ->limit($backupsKeep)
                    ->pluck('id');

                $batch = DB::table('config_backups')
                    ->where('device_id', $device->device_id)
                    ->whereNotIn('id', $keepIds)
                    ->delete();

                $deleted += $batch;
            }
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} config backups");
        }

        // Summary
        $this->newLine();
        $this->info('=== CLEANUP SUMMARY ===');
        if ($dryRun) {
            $this->warn('DRY RUN - No data was actually deleted');
            $this->line('Run without --dry-run to perform cleanup');
        } else {
            $this->info("Total rows deleted: {$totalDeleted}");
            $this->line('Run OPTIMIZE TABLE on cleaned tables to reclaim space');
        }

        return 0;
    }

    private function batchDelete(string $table, string $column, $threshold, int $batchSize): int
    {
        $deleted = 0;
        do {
            $batch = DB::table($table)
                ->where($column, '<', $threshold)
                ->limit($batchSize)
                ->delete();
            $deleted += $batch;
            if ($batch > 0) {
                $this->line("    Deleted batch of {$batch} from {$table}...");
                usleep(100000); // 100ms delay between batches
            }
        } while ($batch > 0);

        return $deleted;
    }
}
