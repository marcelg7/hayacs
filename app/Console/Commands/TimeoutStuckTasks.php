<?php

namespace App\Console\Commands;

use App\Models\Task;
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
        $taskTypeTimeouts = [
            'download' => 20,           // Firmware upgrades need much longer
            'reboot' => 5,              // Reboot takes a few minutes
            'factory_reset' => 5,       // Factory reset takes time
            'upload' => 10,             // Log uploads can be large
            'add_object' => 3,          // Object creation
            'delete_object' => 3,       // Object deletion
            'set_parameter_values' => $defaultMinutes,
            'get_parameter_values' => $defaultMinutes,
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
            $this->info("Successfully timed out {$timedOutCount} task(s).");
        } else {
            $this->info('No stuck tasks exceeded their timeout thresholds.');
        }

        return Command::SUCCESS;
    }
}
