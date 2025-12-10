<?php

namespace App\Console\Commands;

use App\Services\MacOuiService;
use Illuminate\Console\Command;

class UpdateOuiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oui:update
                            {--force : Force update even if data exists}
                            {--info : Show info about current OUI data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and update MAC OUI data from IEEE registry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = new MacOuiService();

        if ($this->option('info')) {
            $this->showInfo($service);
            return Command::SUCCESS;
        }

        if (!$this->option('force') && $service->isDataAvailable()) {
            $count = $service->getEntryCount();
            $this->info("OUI data already exists with {$count} entries.");
            $this->info("Use --force to re-download, or --info to see current data status.");
            return Command::SUCCESS;
        }

        $this->info('Downloading OUI data from IEEE...');
        $this->line('This may take a minute...');

        $start = microtime(true);

        if ($service->updateFromIeee()) {
            $elapsed = round(microtime(true) - $start, 2);
            $count = $service->getEntryCount();

            $this->newLine();
            $this->info("Successfully downloaded and processed OUI data!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Entries', number_format($count)],
                    ['Time', "{$elapsed}s"],
                    ['File', storage_path('app/oui-data.json')],
                ]
            );

            return Command::SUCCESS;
        }

        $this->error('Failed to update OUI data. Check logs for details.');
        return Command::FAILURE;
    }

    /**
     * Show info about current OUI data
     */
    protected function showInfo(MacOuiService $service): void
    {
        $dataPath = storage_path('app/oui-data.json');

        if (!$service->isDataAvailable()) {
            $this->warn('No OUI data file found.');
            $this->line("Run 'php artisan oui:update' to download OUI data.");
            return;
        }

        $count = $service->getEntryCount();
        $fileSize = filesize($dataPath);
        $modified = filemtime($dataPath);

        $this->table(
            ['Property', 'Value'],
            [
                ['File', $dataPath],
                ['Entries', number_format($count)],
                ['File Size', $this->formatBytes($fileSize)],
                ['Last Updated', date('Y-m-d H:i:s', $modified)],
                ['Age', $this->formatAge($modified)],
            ]
        );

        // Test a few lookups
        $this->newLine();
        $this->info('Sample lookups:');

        $testMacs = [
            '00:11:22:33:44:55' => 'Cimsys Inc',
            'D0:76:8F:00:00:00' => 'Calix Inc.',
            '80:AB:4D:00:00:00' => 'Nokia',
            '00:23:6A:00:00:00' => 'Calix Networks',
        ];

        foreach ($testMacs as $mac => $expected) {
            $result = $service->lookup($mac);
            $vendor = $result['vendor'] ?? 'Not found';
            $this->line("  {$mac} => {$vendor}");
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Format age of file
     */
    protected function formatAge(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 3600) {
            return round($diff / 60) . ' minutes ago';
        }
        if ($diff < 86400) {
            return round($diff / 3600) . ' hours ago';
        }
        return round($diff / 86400) . ' days ago';
    }
}
