<?php

namespace App\Console\Commands;

use App\Jobs\ImportSubscribersJob;
use App\Models\ImportStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncBillingData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:sync
                            {--force : Force sync even if files haven\'t changed}
                            {--dry-run : Show what would be synced without actually syncing}
                            {--keep-files : Don\'t delete old files after sync}';

    /**
     * The console command description.
     */
    protected $description = 'Sync subscriber data from billing system CSV exports';

    /**
     * The directory where billing files are uploaded.
     */
    protected string $syncDirectory;

    /**
     * Cache key for tracking last sync.
     */
    protected string $cacheKey = 'billing_sync_last_files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->syncDirectory = storage_path('billing-sync');

        // Check if sync directory exists
        if (!File::isDirectory($this->syncDirectory)) {
            $this->error("Sync directory does not exist: {$this->syncDirectory}");
            return self::FAILURE;
        }

        // Check if an import is already running
        $runningImport = ImportStatus::where('type', 'subscriber')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($runningImport) {
            $this->warn('An import is already in progress. Skipping sync.');
            Log::info('billing:sync skipped - import already in progress', [
                'import_id' => $runningImport->id,
                'status' => $runningImport->status,
            ]);
            return self::SUCCESS;
        }

        // Find the latest files
        $latestFiles = $this->findLatestFiles();

        if (empty($latestFiles)) {
            $this->info('No CSV files found in sync directory.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($latestFiles) . ' file(s):');
        foreach ($latestFiles as $type => $file) {
            $this->line("  - [{$type}] " . basename($file['path']) . ' (' . $this->formatBytes($file['size']) . ')');
        }

        // Check if files have changed since last sync
        $lastSyncedFiles = Cache::get($this->cacheKey, []);
        $filesChanged = $this->haveFilesChanged($latestFiles, $lastSyncedFiles);

        if (!$filesChanged && !$this->option('force')) {
            $this->info('Files have not changed since last sync. Use --force to sync anyway.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - Would sync the following files:');
            foreach ($latestFiles as $type => $file) {
                $this->line("  - " . basename($file['path']));
            }
            $this->line('');
            $this->info('Would truncate existing data and reimport.');
            return self::SUCCESS;
        }

        // Prepare file paths for import
        $filePaths = array_map(fn($f) => $f['path'], $latestFiles);

        // Create import status record
        $importStatus = ImportStatus::create([
            'type' => 'subscriber',
            'status' => 'pending',
            'filename' => implode(', ', array_map('basename', $filePaths)),
            'message' => 'Scheduled sync from billing system',
        ]);

        $this->info('Starting import (ID: ' . $importStatus->id . ')...');
        Log::info('billing:sync starting import', [
            'import_id' => $importStatus->id,
            'files' => array_map('basename', $filePaths),
        ]);

        // Dispatch the import job with truncate=true (full replace)
        ImportSubscribersJob::dispatch($filePaths, true, $importStatus->id);

        // Store the synced files info for change detection (including hash)
        $syncInfo = [];
        foreach ($latestFiles as $type => $file) {
            $syncInfo[$type] = [
                'path' => $file['path'],
                'mtime' => $file['mtime'],
                'size' => $file['size'],
                'hash' => $file['hash'],
            ];
        }
        Cache::put($this->cacheKey, $syncInfo, now()->addDays(7));

        // Clean up old files (keep only the latest of each type)
        if (!$this->option('keep-files')) {
            $this->cleanupOldFiles($latestFiles);
        }

        $this->info('Import job dispatched. Check /subscribers/import for progress.');

        return self::SUCCESS;
    }

    /**
     * Find the latest file of each type (Agreement-Based and Location-Based).
     */
    protected function findLatestFiles(): array
    {
        $files = File::glob($this->syncDirectory . '/*.csv');

        $latestFiles = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $mtime = File::lastModified($file);
            $size = File::size($file);

            // Determine file type based on filename
            if (stripos($filename, 'Agreement-Based') !== false) {
                $type = 'agreement';
            } elseif (stripos($filename, 'Location-Based') !== false) {
                $type = 'location';
            } else {
                // Unknown file type - include it anyway
                $type = 'other_' . md5($filename);
            }

            // Keep only the most recent file of each type
            if (!isset($latestFiles[$type]) || $mtime > $latestFiles[$type]['mtime']) {
                $latestFiles[$type] = [
                    'path' => $file,
                    'mtime' => $mtime,
                    'size' => $size,
                    'hash' => md5_file($file), // Content hash for change detection
                ];
            }
        }

        return $latestFiles;
    }

    /**
     * Check if files have changed since last sync.
     * Uses content hash (MD5) for reliable change detection.
     */
    protected function haveFilesChanged(array $currentFiles, array $lastSyncedFiles): bool
    {
        // If no previous sync, files have "changed"
        if (empty($lastSyncedFiles)) {
            $this->line('  (No previous sync found)');
            return true;
        }

        // Check each file type
        foreach ($currentFiles as $type => $file) {
            // New file type
            if (!isset($lastSyncedFiles[$type])) {
                $this->line("  (New file type: {$type})");
                return true;
            }

            // Compare content hash - most reliable way to detect changes
            if (isset($file['hash']) && isset($lastSyncedFiles[$type]['hash'])) {
                if ($file['hash'] !== $lastSyncedFiles[$type]['hash']) {
                    $this->line("  (Content changed: {$type})");
                    return true;
                }
            } else {
                // Fallback to size comparison if hash not available
                if ($file['size'] !== $lastSyncedFiles[$type]['size']) {
                    $this->line("  (Size changed: {$type})");
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clean up old files, keeping only the latest of each type.
     */
    protected function cleanupOldFiles(array $latestFiles): void
    {
        $files = File::glob($this->syncDirectory . '/*.csv');
        $latestPaths = array_map(fn($f) => $f['path'], $latestFiles);
        $deletedCount = 0;

        foreach ($files as $file) {
            if (!in_array($file, $latestPaths)) {
                File::delete($file);
                $deletedCount++;
                $this->line("  Deleted old file: " . basename($file));
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleaned up {$deletedCount} old file(s).");
            Log::info('billing:sync cleaned up old files', ['count' => $deletedCount]);
        }
    }

    /**
     * Format bytes to human-readable size.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
