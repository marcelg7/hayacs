<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\ConnectionRequestService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TimeoutStuckTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:timeout {--minutes=2 : Number of minutes before timing out}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fail tasks stuck in "sent" status with task-type-specific timeouts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $defaultMinutes = (int) $this->option('minutes');

        // Task-type-specific timeouts (in minutes)
        // Note: TR-181 Nokia Beacon G6 WiFi operations take ~2.5 minutes per radio
        $taskTypeTimeouts = [
            'download' => 20,           // Firmware upgrades need much longer
            'reboot' => 5,              // Reboot takes a few minutes
            'factory_reset' => 5,       // Factory reset takes time
            'upload' => 10,             // Log uploads can be large
            'add_object' => 3,          // Object creation
            'delete_object' => 3,       // Object deletion
            'set_parameter_values' => 3, // 2.5 min + buffer; WiFi tasks get verification on timeout
            'get_parameter_values' => 3, // Large parameter sets can take time
        ];

        // Find tasks stuck in "sent" status
        $stuckTasks = Task::where('status', 'sent')->get();

        if ($stuckTasks->isEmpty()) {
            $this->info('No stuck tasks found.');
            return Command::SUCCESS;
        }

        $timedOutCount = 0;

        foreach ($stuckTasks as $task) {
            // Get timeout for this task type, or use default
            $timeoutMinutes = $taskTypeTimeouts[$task->task_type] ?? $defaultMinutes;
            $cutoffTime = Carbon::now()->subMinutes($timeoutMinutes);

            // Check if this task has exceeded its timeout
            if ($task->updated_at <= $cutoffTime) {
                $elapsedMinutes = Carbon::parse($task->updated_at)->diffInMinutes(Carbon::now());

                // Special handling for WiFi tasks - queue verification instead of immediately failing
                if ($this->isWifiTask($task)) {
                    $this->queueWifiVerification($task, $elapsedMinutes);
                    $timedOutCount++;
                    continue;
                }

                // Mark task as failed using the model method
                $task->markAsFailed("Task timed out after {$elapsedMinutes} minutes. Device did not respond to the command.");

                Log::warning("Task timeout detected", [
                    'task_id' => $task->id,
                    'device_id' => $task->device_id,
                    'task_type' => $task->task_type,
                    'timeout_minutes' => $timeoutMinutes,
                    'elapsed_minutes' => $elapsedMinutes,
                    'sent_at' => $task->updated_at,
                ]);

                $this->warn("Task {$task->id} ({$task->task_type}) timed out after {$elapsedMinutes} minutes (timeout: {$timeoutMinutes}m)");
                $timedOutCount++;
            }
        }

        if ($timedOutCount > 0) {
            $this->info("Successfully processed {$timedOutCount} task(s).");
        } else {
            $this->info('No stuck tasks exceeded their timeout thresholds.');
        }

        return Command::SUCCESS;
    }

    /**
     * Check if this is a WiFi configuration task
     */
    private function isWifiTask(Task $task): bool
    {
        if ($task->task_type !== 'set_parameter_values') {
            return false;
        }

        // Check description for WiFi indicator
        if ($task->description && str_contains($task->description, 'WiFi:')) {
            return true;
        }

        // Check parameters for WiFi-related paths
        if (is_array($task->parameters)) {
            foreach (array_keys($task->parameters) as $paramName) {
                if (str_contains($paramName, 'WiFi') ||
                    str_contains($paramName, 'WLAN') ||
                    str_contains($paramName, 'WLANConfiguration') ||
                    str_contains($paramName, 'SSID') ||
                    str_contains($paramName, 'Radio')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Queue a verification task for WiFi settings
     * Instead of immediately failing, we create a get_params task to verify if settings were applied
     */
    private function queueWifiVerification(Task $task, int $elapsedMinutes): void
    {
        $device = $task->device;

        if (!$device) {
            $task->markAsFailed("Task timed out after {$elapsedMinutes} minutes. Device not found for verification.");
            return;
        }

        // Get the parameter names that were being set
        $paramNames = [];
        if (is_array($task->parameters)) {
            $paramNames = array_keys($task->parameters);
        }

        if (empty($paramNames)) {
            $task->markAsFailed("Task timed out after {$elapsedMinutes} minutes. No parameters to verify.");
            return;
        }

        // Mark the original task as pending verification
        $task->update([
            'status' => 'verifying',
            'result' => json_encode([
                'message' => "Task timed out after {$elapsedMinutes} minutes. Queuing verification to check if settings were applied.",
                'verification_started_at' => now()->toIso8601String(),
            ]),
        ]);

        // Create a verification task to read back the WiFi parameters
        $verificationTask = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'description' => 'WiFi verification: Check if settings were applied',
            'status' => 'pending',
            'parameters' => ['names' => $paramNames],
            'progress_info' => [
                'verification_for_task_id' => $task->id,
                'expected_values' => $task->parameters,
            ],
        ]);

        Log::info('WiFi verification task queued', [
            'original_task_id' => $task->id,
            'verification_task_id' => $verificationTask->id,
            'device_id' => $device->id,
            'param_count' => count($paramNames),
            'elapsed_minutes' => $elapsedMinutes,
        ]);

        $this->info("Task {$task->id} (WiFi) timed out - queued verification task {$verificationTask->id}");

        // Send connection request to bring device back quickly for verification
        // This prevents waiting up to 15 minutes for the next periodic inform
        $this->sendConnectionRequestForVerification($device, $task->id, $verificationTask->id);
    }

    /**
     * Send a connection request to bring the device back for verification
     */
    private function sendConnectionRequestForVerification($device, int $originalTaskId, int $verificationTaskId): void
    {
        try {
            $connectionService = app(ConnectionRequestService::class);
            $result = $connectionService->sendConnectionRequest($device);

            if ($result['success']) {
                Log::info('Connection request sent for WiFi verification', [
                    'device_id' => $device->id,
                    'original_task_id' => $originalTaskId,
                    'verification_task_id' => $verificationTaskId,
                ]);
                $this->info("  → Connection request sent to device for faster verification");
            } else {
                Log::warning('Connection request failed for WiFi verification', [
                    'device_id' => $device->id,
                    'original_task_id' => $originalTaskId,
                    'verification_task_id' => $verificationTaskId,
                    'error' => $result['message'],
                ]);
                $this->warn("  → Connection request failed: {$result['message']} (will verify on next periodic inform)");
            }
        } catch (\Exception $e) {
            Log::error('Exception sending connection request for WiFi verification', [
                'device_id' => $device->id,
                'original_task_id' => $originalTaskId,
                'verification_task_id' => $verificationTaskId,
                'error' => $e->getMessage(),
            ]);
            $this->warn("  → Connection request error: {$e->getMessage()}");
        }
    }
}
