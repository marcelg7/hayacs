<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\ConnectionRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetExpiredRemoteSupport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:reset-expired-remote-support';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset passwords for devices with expired remote support sessions';

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
        // Find all devices with expired remote support sessions
        $expiredDevices = Device::whereNotNull('remote_support_expires_at')
            ->where('remote_support_expires_at', '<', now())
            ->get();

        if ($expiredDevices->isEmpty()) {
            $this->info('No expired remote support sessions found.');
            return 0;
        }

        $this->info("Found {$expiredDevices->count()} device(s) with expired remote support sessions.");

        $resetCount = 0;
        $failCount = 0;

        foreach ($expiredDevices as $device) {
            $this->line("Processing: {$device->serial_number} ({$device->id})");

            try {
                // Create task to reset password to device-specific password
                $task = $device->disableRemoteSupport();

                if ($task) {
                    // Send connection request to apply the password reset
                    $this->connectionRequestService->sendConnectionRequest($device);

                    Log::info('Reset expired remote support session', [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'task_id' => $task->id,
                        'expired_at' => $device->remote_support_expires_at,
                    ]);

                    $this->info("  ✓ Password reset task created (Task #{$task->id})");
                    $resetCount++;
                } else {
                    $this->warn("  ✗ Failed to create password reset task");
                    $failCount++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to reset expired remote support', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("  ✗ Error: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Summary: {$resetCount} reset, {$failCount} failed");

        return $failCount > 0 ? 1 : 0;
    }
}
