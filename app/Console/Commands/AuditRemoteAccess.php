<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditRemoteAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:audit-remote-access
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nightly audit to disable any remaining remote access and reset passwords (runs at 10 PM)';

    protected ConnectionRequestService $connectionRequestService;

    public function __construct(ConnectionRequestService $connectionRequestService)
    {
        parent::__construct();
        $this->connectionRequestService = $connectionRequestService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting nightly remote access audit...');

        // Find all devices that have remote_support_expires_at set (even if expired)
        // This catches any devices that might have been missed by the regular cleanup
        $devicesWithRemoteSupport = Device::whereNotNull('remote_support_expires_at')
            ->where(function ($query) {
                // Exclude SmartRG devices - they use MER network access
                $query->where('manufacturer', '!=', 'SmartRG')
                    ->orWhereNull('manufacturer');
            })
            ->get();

        $this->info("Found {$devicesWithRemoteSupport->count()} device(s) with remote support flag set.");

        $resetCount = 0;
        $skipCount = 0;
        $failCount = 0;

        foreach ($devicesWithRemoteSupport as $device) {
            $this->line("Processing: {$device->serial_number} ({$device->manufacturer} {$device->product_class})");
            $this->line("  Remote support expires: " . ($device->remote_support_expires_at?->format('Y-m-d H:i:s') ?? 'N/A'));

            // Skip SmartRG devices
            if ($device->isSmartRG()) {
                $this->line("  → Skipping SmartRG device (uses MER network access)");
                if (!$dryRun) {
                    $device->update(['remote_support_expires_at' => null]);
                }
                $skipCount++;
                continue;
            }

            if ($dryRun) {
                $this->info("  → Would disable remote access and reset password");
                $resetCount++;
                continue;
            }

            try {
                // Disable remote support and reset password to random
                $task = $device->disableRemoteSupport();

                if ($task) {
                    // Send connection request to apply the changes
                    $this->connectionRequestService->sendConnectionRequest($device);

                    Log::info('Nightly audit: Disabled remote access', [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'task_id' => $task->id,
                    ]);

                    $this->info("  ✓ Remote access disabled, password reset (Task #{$task->id})");
                    $resetCount++;
                } else {
                    // Device might not support password management
                    $device->update(['remote_support_expires_at' => null]);
                    $this->warn("  ✗ Could not create reset task - cleared flag only");
                    $skipCount++;
                }
            } catch (\Exception $e) {
                Log::error('Nightly audit: Failed to disable remote access', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("  ✗ Error: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("=== Audit Summary ===");
        $this->info("Reset: {$resetCount}");
        $this->info("Skipped: {$skipCount}");
        $this->info("Failed: {$failCount}");

        if ($dryRun) {
            $this->warn('DRY RUN COMPLETE - No changes were made');
        }

        return $failCount > 0 ? 1 : 0;
    }
}
