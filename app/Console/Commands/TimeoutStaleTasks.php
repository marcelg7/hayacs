<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TimeoutStaleTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:timeout-pending
                            {--hours=24 : Hours before pending tasks timeout}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark stale pending tasks as failed due to timeout (for tasks waiting for device to inform)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("Pending Task Timeout Cleanup" . ($dryRun ? ' (DRY RUN)' : ''));
        $this->line("Timeout: {$hours} hours");
        $this->newLine();

        $cutoff = Carbon::now()->subHours($hours);

        // Find stale pending tasks
        $stalePending = Task::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $this->info("Found {$stalePending->count()} stale pending tasks");

        if ($stalePending->isEmpty()) {
            $this->info('No stale tasks to process.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->warn('Stale Pending Tasks:');

        $headers = ['ID', 'Device', 'Type', 'Created', 'Age'];
        $rows = $stalePending->map(function ($task) {
            return [
                $task->id,
                $task->device_id,
                $task->task_type,
                $task->created_at->format('Y-m-d H:i'),
                $task->created_at->diffForHumans(),
            ];
        })->toArray();

        $this->table($headers, $rows);

        if (!$dryRun) {
            foreach ($stalePending as $task) {
                $task->update([
                    'status' => 'failed',
                    'result' => json_encode([
                        'error' => "Task timed out - device did not inform within {$hours} hours",
                        'timeout_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
            $this->info("Marked {$stalePending->count()} pending tasks as failed");
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN: Would have timed out {$stalePending->count()} tasks");
        } else {
            $this->info("Successfully timed out {$stalePending->count()} tasks");
        }

        return Command::SUCCESS;
    }
}
