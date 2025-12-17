<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateParameters extends Command
{
    protected $signature = 'parameters:cleanup-duplicates
                            {--batch-size=500 : Number of rows to delete per batch}
                            {--max-batches=0 : Maximum batches to run (0 = unlimited)}
                            {--delay=100 : Milliseconds to pause between batches}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove duplicate parameters keeping only the most recent (highest id) for each device_id + name combination';

    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $delayMs = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');

        $this->info('Starting duplicate parameter cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No deletions will be performed');
        }

        // Count duplicates first
        $duplicateCount = DB::selectOne('
            SELECT COUNT(*) as cnt FROM (
                SELECT device_id, name
                FROM parameters
                GROUP BY device_id, name
                HAVING COUNT(*) > 1
            ) as dups
        ')->cnt;

        $this->info("Found {$duplicateCount} duplicate groups to clean up");
        Log::info("Duplicate cleanup started: {$duplicateCount} duplicate groups");

        if ($duplicateCount == 0) {
            $this->info('No duplicates found. Exiting.');
            return 0;
        }

        $batches = 0;
        $totalDeleted = 0;
        $startTime = microtime(true);

        while (true) {
            // Find IDs to delete (older duplicates, keeping MAX id)
            $toDelete = DB::select("
                SELECT p1.id
                FROM parameters p1
                INNER JOIN (
                    SELECT device_id, name, MAX(id) as max_id
                    FROM parameters
                    GROUP BY device_id, name
                    HAVING COUNT(*) > 1
                ) p2 ON p1.device_id = p2.device_id
                    AND p1.name = p2.name
                    AND p1.id < p2.max_id
                LIMIT {$batchSize}
            ");

            if (empty($toDelete)) {
                $this->info('No more duplicates found.');
                break;
            }

            $ids = array_map(fn($r) => $r->id, $toDelete);
            $count = count($ids);

            if ($dryRun) {
                $this->line("Batch " . ($batches + 1) . ": Would delete {$count} rows");
                $totalDeleted += $count;
            } else {
                $deleted = DB::table('parameters')->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
                $this->line("Batch " . ($batches + 1) . ": Deleted {$deleted} rows (total: {$totalDeleted})");
            }

            $batches++;

            // Check max batches limit
            if ($maxBatches > 0 && $batches >= $maxBatches) {
                $this->warn("Reached max batches limit ({$maxBatches}). Stopping.");
                break;
            }

            // Pause between batches to reduce lock contention
            if ($delayMs > 0 && !empty($toDelete)) {
                usleep($delayMs * 1000);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $message = $dryRun
            ? "DRY RUN complete: Would delete {$totalDeleted} rows in {$batches} batches ({$elapsed}s)"
            : "Cleanup complete: Deleted {$totalDeleted} rows in {$batches} batches ({$elapsed}s)";

        $this->info($message);
        Log::info($message);

        // Verify remaining duplicates
        if (!$dryRun) {
            $remaining = DB::selectOne('
                SELECT COUNT(*) as cnt FROM (
                    SELECT device_id, name
                    FROM parameters
                    GROUP BY device_id, name
                    HAVING COUNT(*) > 1
                    LIMIT 1000
                ) as dups
            ')->cnt;

            if ($remaining > 0) {
                $this->warn("Remaining duplicate groups: {$remaining}");
                Log::warning("Duplicate cleanup incomplete: {$remaining} groups remaining");
            } else {
                $this->info('All duplicates removed successfully!');
                Log::info('Duplicate cleanup complete: All duplicates removed');
            }
        }

        return 0;
    }
}
