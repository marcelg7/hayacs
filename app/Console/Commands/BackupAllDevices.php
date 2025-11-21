<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupAllDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backups:create-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create daily backup for all devices with parameters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily backup for all devices...');

        $devices = Device::whereHas('parameters')->get();
        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;

        foreach ($devices as $device) {
            try {
                // Get all current parameters
                $parameters = $device->parameters()
                    ->get()
                    ->mapWithKeys(function ($param) {
                        return [$param->name => [
                            'value' => $param->value,
                            'type' => $param->type,
                            'writable' => $param->writable,
                        ]];
                    })
                    ->toArray();

                $parameterCount = count($parameters);

                if ($parameterCount === 0) {
                    $this->warn("  Skipping {$device->id} - No parameters");
                    $skipCount++;
                    continue;
                }

                // Create the backup
                $backup = $device->configBackups()->create([
                    'name' => 'Daily Backup - ' . now()->format('Y-m-d H:i:s'),
                    'description' => 'Scheduled daily backup',
                    'backup_data' => $parameters,
                    'is_auto' => true,
                    'parameter_count' => $parameterCount,
                ]);

                // Update device backup tracking
                $device->update([
                    'last_backup_at' => now(),
                ]);

                $this->info("  ✓ Backed up {$device->id} ({$parameterCount} parameters)");
                $successCount++;

                try {
                    Log::info('Daily backup created', [
                        'device_id' => $device->id,
                        'backup_id' => $backup->id,
                        'parameter_count' => $parameterCount,
                    ]);
                } catch (\Exception $logError) {
                    // Logging failed, but backup succeeded - continue
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to backup {$device->id}: {$e->getMessage()}");
                $errorCount++;

                try {
                    Log::error('Daily backup failed', [
                        'device_id' => $device->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $logError) {
                    // Ignore logging errors
                }
            }
        }

        $this->newLine();
        $this->info("Daily backup complete:");
        $this->info("  Success: {$successCount}");
        $this->info("  Skipped: {$skipCount}");
        $this->info("  Errors: {$errorCount}");

        try {
            Log::info('Daily backup job completed', [
                'success' => $successCount,
                'skipped' => $skipCount,
                'errors' => $errorCount,
            ]);
        } catch (\Exception $logError) {
            // Ignore logging errors
        }

        return Command::SUCCESS;
    }
}
