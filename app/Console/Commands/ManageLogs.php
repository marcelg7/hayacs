<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ManageLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:manage
                            {--rotate : Rotate logs now (move current to dated file)}
                            {--compress : Compress old log files with xz}
                            {--cleanup : Delete logs older than retention period}
                            {--all : Run rotate, compress, and cleanup}
                            {--retention=30 : Days to keep compressed logs}
                            {--max-size=100 : Max size in MB before forced rotation}
                            {--dry-run : Show what would be done without doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Laravel log files: rotate, compress with xz, and cleanup old logs';

    /**
     * Log files to manage
     */
    protected array $logFiles = [
        'laravel.log',
        'queue.log',
        'queue-worker.log',
        'workflows.log',
        'task-timeout.log',
        'remote-support.log',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs');
        $dryRun = $this->option('dry-run');
        $retention = (int) $this->option('retention');
        $maxSizeMB = (int) $this->option('max-size');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info("Log Management Started");
        $this->info("Log path: {$logPath}");
        $this->info("Retention: {$retention} days");
        $this->info("Max size before rotation: {$maxSizeMB} MB");
        $this->newLine();

        // Run all operations if --all is specified
        $runAll = $this->option('all');
        $rotate = $this->option('rotate') || $runAll;
        $compress = $this->option('compress') || $runAll;
        $cleanup = $this->option('cleanup') || $runAll;

        // If no options specified, show help
        if (!$rotate && !$compress && !$cleanup) {
            $this->showStatus($logPath);
            $this->newLine();
            $this->info('Use --all to run all operations, or specify individual operations:');
            $this->info('  --rotate   : Rotate logs exceeding max size');
            $this->info('  --compress : Compress rotated log files');
            $this->info('  --cleanup  : Delete old compressed logs');
            return Command::SUCCESS;
        }

        // Step 1: Rotate logs if needed
        if ($rotate) {
            $this->rotateLogsIfNeeded($logPath, $maxSizeMB, $dryRun);
        }

        // Step 2: Compress old logs
        if ($compress) {
            $this->compressOldLogs($logPath, $dryRun);
        }

        // Step 3: Cleanup old compressed logs
        if ($cleanup) {
            $this->cleanupOldLogs($logPath, $retention, $dryRun);
        }

        $this->newLine();
        $this->info('Log management completed.');
        $this->showStatus($logPath);

        return Command::SUCCESS;
    }

    /**
     * Show current log status
     */
    protected function showStatus(string $logPath): void
    {
        $this->newLine();
        $this->info('=== Current Log Status ===');

        $files = File::files($logPath);
        $totalSize = 0;
        $rows = [];

        foreach ($files as $file) {
            $size = $file->getSize();
            $totalSize += $size;
            $rows[] = [
                $file->getFilename(),
                $this->formatBytes($size),
                Carbon::createFromTimestamp($file->getMTime())->diffForHumans(),
            ];
        }

        // Sort by size descending
        usort($rows, fn($a, $b) => $this->parseBytes($b[1]) <=> $this->parseBytes($a[1]));

        $this->table(['File', 'Size', 'Modified'], $rows);
        $this->info("Total: " . $this->formatBytes($totalSize));
    }

    /**
     * Rotate logs that exceed max size
     */
    protected function rotateLogsIfNeeded(string $logPath, int $maxSizeMB, bool $dryRun): void
    {
        $this->info('=== Rotating Large Logs ===');
        $maxBytes = $maxSizeMB * 1024 * 1024;

        foreach ($this->logFiles as $logFile) {
            $filePath = "{$logPath}/{$logFile}";

            if (!File::exists($filePath)) {
                continue;
            }

            $size = File::size($filePath);

            if ($size > $maxBytes) {
                $date = Carbon::now()->format('Y-m-d_His');
                $baseName = pathinfo($logFile, PATHINFO_FILENAME);
                $rotatedName = "{$baseName}-{$date}.log";
                $rotatedPath = "{$logPath}/{$rotatedName}";

                $this->line("  Rotating {$logFile} (" . $this->formatBytes($size) . ") -> {$rotatedName}");

                if (!$dryRun) {
                    // Move the file
                    File::move($filePath, $rotatedPath);
                    // Create empty new log file with same permissions
                    File::put($filePath, '');
                    chmod($filePath, 0664);
                }
            }
        }
    }

    /**
     * Compress uncompressed rotated log files
     */
    protected function compressOldLogs(string $logPath, bool $dryRun): void
    {
        $this->info('=== Compressing Old Logs ===');

        $files = File::files($logPath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip if already compressed or is a current log file
            if (str_ends_with($filename, '.xz') || str_ends_with($filename, '.gz')) {
                continue;
            }

            // Only compress rotated logs (contain date pattern)
            if (!preg_match('/\d{4}-\d{2}-\d{2}/', $filename)) {
                continue;
            }

            // Skip .gitignore
            if ($filename === '.gitignore') {
                continue;
            }

            $filePath = $file->getRealPath();
            $compressedPath = "{$filePath}.xz";
            $originalSize = $file->getSize();

            $this->line("  Compressing {$filename} (" . $this->formatBytes($originalSize) . ")");

            if (!$dryRun) {
                // Use xz for best compression
                $result = null;
                $output = [];
                exec("xz -9 -T0 " . escapeshellarg($filePath) . " 2>&1", $output, $result);

                if ($result === 0 && File::exists($compressedPath)) {
                    $compressedSize = File::size($compressedPath);
                    $ratio = round((1 - $compressedSize / $originalSize) * 100, 1);
                    $this->line("    -> " . $this->formatBytes($compressedSize) . " ({$ratio}% reduction)");
                } else {
                    $this->error("    Failed to compress: " . implode("\n", $output));
                }
            }
        }
    }

    /**
     * Delete compressed logs older than retention period
     */
    protected function cleanupOldLogs(string $logPath, int $retentionDays, bool $dryRun): void
    {
        $this->info("=== Cleaning Up Logs Older Than {$retentionDays} Days ===");

        $cutoff = Carbon::now()->subDays($retentionDays);
        $files = File::files($logPath);
        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Only cleanup compressed files
            if (!str_ends_with($filename, '.xz') && !str_ends_with($filename, '.gz')) {
                continue;
            }

            $modifiedAt = Carbon::createFromTimestamp($file->getMTime());

            if ($modifiedAt->lt($cutoff)) {
                $size = $file->getSize();
                $this->line("  Deleting {$filename} (modified {$modifiedAt->diffForHumans()})");

                if (!$dryRun) {
                    File::delete($file->getRealPath());
                }

                $deletedCount++;
                $deletedSize += $size;
            }
        }

        if ($deletedCount > 0) {
            $this->info("  Deleted {$deletedCount} files, freed " . $this->formatBytes($deletedSize));
        } else {
            $this->info("  No old logs to cleanup");
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Parse human readable bytes back to number
     */
    protected function parseBytes(string $formatted): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024];

        if (preg_match('/^([\d.]+)\s*(\w+)$/', $formatted, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);
            return (int) ($value * ($units[$unit] ?? 1));
        }

        return 0;
    }
}
