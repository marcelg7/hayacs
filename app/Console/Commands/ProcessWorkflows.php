<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\GroupWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowLog;
use App\Services\DeviceGroupService;
use App\Services\WorkflowExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWorkflows extends Command
{
    protected $signature = 'workflows:process
                            {--limit=100 : Maximum number of executions to process}
                            {--workflow= : Process only a specific workflow ID}';

    protected $description = 'Process pending workflow executions based on schedules and rate limits';

    public function __construct(
        private DeviceGroupService $groupService,
        private WorkflowExecutionService $executionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $workflowId = $this->option('workflow');

        // Reset stale "queued" executions back to "pending" (stuck for >5 minutes)
        $this->resetStaleQueuedExecutions();

        $this->info('Processing workflows...');

        // Get active workflows that should run now
        $query = GroupWorkflow::active();

        if ($workflowId) {
            $query->where('id', $workflowId);
        }

        $workflows = $query->get()->filter(fn($w) => $w->shouldRunNow());

        if ($workflows->isEmpty()) {
            $this->info('No workflows ready to run.');
            return 0;
        }

        $totalProcessed = 0;

        foreach ($workflows as $workflow) {
            $processed = $this->processWorkflow($workflow, $limit - $totalProcessed);
            $totalProcessed += $processed;

            if ($totalProcessed >= $limit) {
                $this->warn("Reached processing limit of {$limit}");
                break;
            }
        }

        $this->info("Processed {$totalProcessed} executions.");

        return 0;
    }

    private function processWorkflow(GroupWorkflow $workflow, int $remainingLimit): int
    {
        $this->line("Processing workflow: {$workflow->name} (ID: {$workflow->id})");

        // Check rate limit (per minute)
        $executionsThisMinute = $this->getExecutionsThisMinute($workflow);
        $rateLimit = $workflow->rate_limit > 0 ? $workflow->rate_limit : PHP_INT_MAX;
        $available = max(0, $rateLimit - $executionsThisMinute);

        if ($available <= 0) {
            $this->warn("  Rate limit reached ({$rateLimit}/min)");
            return 0;
        }

        // Check concurrent limit
        $currentlyRunning = $this->getCurrentlyRunning($workflow);
        $maxConcurrent = $workflow->max_concurrent > 0 ? $workflow->max_concurrent : PHP_INT_MAX;
        $concurrentAvailable = max(0, $maxConcurrent - $currentlyRunning);

        if ($concurrentAvailable <= 0) {
            $this->warn("  Concurrent limit reached ({$maxConcurrent})");
            return 0;
        }

        // Calculate how many we can process
        $canProcess = min($remainingLimit, $available, $concurrentAvailable);

        // Get pending executions
        $executions = WorkflowExecution::where('group_workflow_id', $workflow->id)
            ->readyToRun()
            ->limit($canProcess)
            ->get();

        if ($executions->isEmpty()) {
            // Maybe we need to initialize executions
            if ($this->shouldInitializeExecutions($workflow)) {
                $count = $this->groupService->initializeWorkflowExecutions($workflow);
                $this->info("  Initialized {$count} new executions");

                // Re-fetch
                $executions = WorkflowExecution::where('group_workflow_id', $workflow->id)
                    ->readyToRun()
                    ->limit($canProcess)
                    ->get();
            }
        }

        if ($executions->isEmpty()) {
            $this->line("  No pending executions");
            return 0;
        }

        $processed = 0;

        foreach ($executions as $execution) {
            $device = Device::find($execution->device_id);

            if (!$device) {
                $execution->markSkipped('Device not found');
                $this->warn("  Device {$execution->device_id} not found, skipping");
                continue;
            }

            // Skip offline devices for immediate workflows - they'll be processed when they come online
            if (!$device->online && $workflow->schedule_type === 'immediate') {
                continue; // Don't process - leave pending for when device comes online
            }

            // Check dependency
            if ($workflow->depends_on_workflow_id) {
                $dependencyMet = WorkflowExecution::where('group_workflow_id', $workflow->depends_on_workflow_id)
                    ->where('device_id', $device->id)
                    ->where('status', 'completed')
                    ->exists();

                if (!$dependencyMet) {
                    $this->line("  Device {$device->id}: dependency not met, skipping");
                    continue; // Don't skip permanently - dependency might complete later
                }
            }

            $task = $this->executionService->executeForDevice($workflow, $device);

            if ($task) {
                $this->info("  Created task {$task->id} for device {$device->id}");
                $processed++;
            }
        }

        return $processed;
    }

    private function getExecutionsThisMinute(GroupWorkflow $workflow): int
    {
        return WorkflowExecution::where('group_workflow_id', $workflow->id)
            ->where('started_at', '>=', now()->subMinute())
            ->count();
    }

    private function getCurrentlyRunning(GroupWorkflow $workflow): int
    {
        return WorkflowExecution::where('group_workflow_id', $workflow->id)
            ->whereIn('status', ['queued', 'in_progress'])
            ->count();
    }

    private function shouldInitializeExecutions(GroupWorkflow $workflow): bool
    {
        // Only initialize if no executions exist yet
        return !WorkflowExecution::where('group_workflow_id', $workflow->id)->exists();
    }

    private function resetStaleQueuedExecutions(): void
    {
        // Find "queued" executions older than 5 minutes and reset to "pending"
        // This handles cases where a task was created but the device never responded
        $staleCount = WorkflowExecution::where('status', 'queued')
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'pending',
                'started_at' => null,
                'task_id' => null,
            ]);

        if ($staleCount > 0) {
            $this->warn("Reset {$staleCount} stale queued executions back to pending");
        }
    }
}
