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
    protected $description = 'Automatically fail tasks stuck in "sent" status for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $cutoffTime = Carbon::now()->subMinutes($minutes);

        // Find tasks stuck in "sent" status
        $stuckTasks = Task::where('status', 'sent')
            ->where('updated_at', '<=', $cutoffTime)
            ->get();

        if ($stuckTasks->isEmpty()) {
            $this->info('No stuck tasks found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$stuckTasks->count()} stuck task(s)...");

        foreach ($stuckTasks as $task) {
            $elapsedMinutes = Carbon::parse($task->updated_at)->diffInMinutes(Carbon::now());

            // Mark task as failed using the model method
            $task->markAsFailed("Task timed out after {$elapsedMinutes} minutes. Device did not respond to the command.");

            Log::warning("Task timeout detected", [
                'task_id' => $task->id,
                'device_id' => $task->device_id,
                'task_type' => $task->task_type,
                'elapsed_minutes' => $elapsedMinutes,
                'sent_at' => $task->updated_at,
            ]);

            $this->warn("Task {$task->id} ({$task->task_type}) timed out after {$elapsedMinutes} minutes");
        }

        $this->info("Successfully timed out {$stuckTasks->count()} task(s).");
        return Command::SUCCESS;
    }
}
