<?php

namespace App\Http\Controllers;

use App\Models\DeviceGroup;
use App\Models\Firmware;
use App\Models\GroupWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowLog;
use App\Services\DeviceGroupService;
use App\Services\WorkflowExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupWorkflowController extends Controller
{
    public function __construct(
        private DeviceGroupService $groupService,
        private WorkflowExecutionService $executionService
    ) {}

    /**
     * Display a listing of workflows
     */
    public function index(Request $request)
    {
        $query = GroupWorkflow::with(['deviceGroup', 'dependsOn', 'creator']);

        if ($request->has('group_id')) {
            $query->where('device_group_id', $request->group_id);
        }

        $workflows = $query->orderBy('created_at', 'desc')->paginate(20);

        // Add stats to each workflow
        foreach ($workflows as $workflow) {
            $workflow->stats = $workflow->getStats();
        }

        $groups = DeviceGroup::orderBy('name')->get();

        return view('workflows.index', compact('workflows', 'groups'));
    }

    /**
     * Show the form for creating a new workflow
     */
    public function create(Request $request)
    {
        $groups = DeviceGroup::active()->orderBy('name')->get();
        $taskTypes = GroupWorkflow::TASK_TYPES;
        $scheduleTypes = GroupWorkflow::SCHEDULE_TYPES;

        // Get device types with their firmwares for smart selection
        $deviceTypes = \App\Models\DeviceType::with(['firmware' => function($q) {
            $q->orderBy('is_active', 'desc')->orderBy('version', 'desc');
        }])->orderBy('name')->get();

        // Build firmware lookup by device type for JavaScript
        $firmwaresByDeviceType = $deviceTypes->mapWithKeys(function($dt) {
            return [$dt->id => $dt->firmware->map(function($fw) {
                return [
                    'id' => $fw->id,
                    'version' => $fw->version,
                    'file_name' => $fw->file_name,
                    'is_active' => $fw->is_active,
                ];
            })];
        });

        // Map product_class to device_type_id for group matching
        $productClassToDeviceType = $deviceTypes->mapWithKeys(function($dt) {
            return [$dt->product_class => $dt->id];
        });

        // Get workflows for dependency selection
        $availableWorkflows = GroupWorkflow::with('deviceGroup')
            ->orderBy('device_group_id')
            ->orderBy('name')
            ->get();

        $selectedGroupId = $request->group_id;

        return view('workflows.create', compact(
            'groups',
            'taskTypes',
            'scheduleTypes',
            'deviceTypes',
            'firmwaresByDeviceType',
            'productClassToDeviceType',
            'availableWorkflows',
            'selectedGroupId'
        ));
    }

    /**
     * Store a newly created workflow
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_group_id' => 'required|exists:device_groups,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'task_type' => 'required|string',
            'task_parameters' => 'nullable|array',
            'schedule_type' => 'required|in:immediate,scheduled,recurring,on_connect',
            'schedule_config' => 'nullable|array',
            'rate_limit' => 'integer|min:0',
            'max_concurrent' => 'integer|min:0',
            'retry_count' => 'integer|min:0|max:10',
            'retry_delay_minutes' => 'integer|min:1',
            'stop_on_failure_percent' => 'integer|min:0|max:100',
            'run_once_per_device' => 'boolean',
            'depends_on_workflow_id' => 'nullable|exists:group_workflows,id',
        ]);

        $workflow = GroupWorkflow::create([
            'device_group_id' => $validated['device_group_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'task_type' => $validated['task_type'],
            'task_parameters' => $validated['task_parameters'] ?? null,
            'schedule_type' => $validated['schedule_type'],
            'schedule_config' => $validated['schedule_config'] ?? null,
            'rate_limit' => $validated['rate_limit'] ?? 0,
            'max_concurrent' => $validated['max_concurrent'] ?? 0,
            'retry_count' => $validated['retry_count'] ?? 0,
            'retry_delay_minutes' => $validated['retry_delay_minutes'] ?? 5,
            'stop_on_failure_percent' => $validated['stop_on_failure_percent'] ?? 0,
            'run_once_per_device' => $validated['run_once_per_device'] ?? true,
            'depends_on_workflow_id' => $validated['depends_on_workflow_id'] ?? null,
            'status' => 'draft',
            'is_active' => false,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        WorkflowLog::info($workflow->id, 'Workflow created', null, null, [
            'created_by' => Auth::user()->name,
        ]);

        return redirect()->route('workflows.show', $workflow)
            ->with('success', 'Workflow created. Review and activate when ready.');
    }

    /**
     * Display the specified workflow
     */
    public function show(GroupWorkflow $workflow)
    {
        $workflow->load(['deviceGroup', 'dependsOn', 'dependents', 'creator', 'updater']);

        $stats = $workflow->getStats();
        $recentExecutions = $workflow->executions()
            ->with('device')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        $recentLogs = $workflow->logs()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('workflows.show', compact('workflow', 'stats', 'recentExecutions', 'recentLogs'));
    }

    /**
     * Show the form for editing the workflow
     */
    public function edit(GroupWorkflow $workflow)
    {
        $groups = DeviceGroup::orderBy('name')->get();
        $taskTypes = GroupWorkflow::TASK_TYPES;
        $scheduleTypes = GroupWorkflow::SCHEDULE_TYPES;

        // Get device types with their firmwares for smart selection
        $deviceTypes = \App\Models\DeviceType::with(['firmware' => function($q) {
            $q->orderBy('is_active', 'desc')->orderBy('version', 'desc');
        }])->orderBy('name')->get();

        // Build firmware lookup by device type for JavaScript
        $firmwaresByDeviceType = $deviceTypes->mapWithKeys(function($dt) {
            return [$dt->id => $dt->firmware->map(function($fw) {
                return [
                    'id' => $fw->id,
                    'version' => $fw->version,
                    'file_name' => $fw->file_name,
                    'is_active' => $fw->is_active,
                ];
            })];
        });

        // Map product_class to device_type_id for group matching
        $productClassToDeviceType = $deviceTypes->mapWithKeys(function($dt) {
            return [$dt->product_class => $dt->id];
        });

        // Get workflows for dependency selection (exclude self and dependents)
        $excludeIds = $workflow->dependents->pluck('id')->push($workflow->id);
        $availableWorkflows = GroupWorkflow::with('deviceGroup')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('device_group_id')
            ->orderBy('name')
            ->get();

        return view('workflows.edit', compact(
            'workflow',
            'groups',
            'taskTypes',
            'scheduleTypes',
            'deviceTypes',
            'firmwaresByDeviceType',
            'productClassToDeviceType',
            'availableWorkflows'
        ));
    }

    /**
     * Update the specified workflow
     */
    public function update(Request $request, GroupWorkflow $workflow)
    {
        $validated = $request->validate([
            'device_group_id' => 'required|exists:device_groups,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'task_type' => 'required|string',
            'task_parameters' => 'nullable|array',
            'schedule_type' => 'required|in:immediate,scheduled,recurring,on_connect',
            'schedule_config' => 'nullable|array',
            'rate_limit' => 'integer|min:0',
            'max_concurrent' => 'integer|min:0',
            'retry_count' => 'integer|min:0|max:10',
            'retry_delay_minutes' => 'integer|min:1',
            'stop_on_failure_percent' => 'integer|min:0|max:100',
            'run_once_per_device' => 'boolean',
            'depends_on_workflow_id' => 'nullable|exists:group_workflows,id',
        ]);

        $workflow->update([
            'device_group_id' => $validated['device_group_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'task_type' => $validated['task_type'],
            'task_parameters' => $validated['task_parameters'] ?? null,
            'schedule_type' => $validated['schedule_type'],
            'schedule_config' => $validated['schedule_config'] ?? null,
            'rate_limit' => $validated['rate_limit'] ?? 0,
            'max_concurrent' => $validated['max_concurrent'] ?? 0,
            'retry_count' => $validated['retry_count'] ?? 0,
            'retry_delay_minutes' => $validated['retry_delay_minutes'] ?? 5,
            'stop_on_failure_percent' => $validated['stop_on_failure_percent'] ?? 0,
            'run_once_per_device' => $validated['run_once_per_device'] ?? true,
            'depends_on_workflow_id' => $validated['depends_on_workflow_id'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        WorkflowLog::info($workflow->id, 'Workflow updated', null, null, [
            'updated_by' => Auth::user()->name,
        ]);

        return redirect()->route('workflows.show', $workflow)
            ->with('success', 'Workflow updated successfully.');
    }

    /**
     * Remove the specified workflow
     */
    public function destroy(GroupWorkflow $workflow)
    {
        $workflowName = $workflow->name;
        $workflow->delete();

        return redirect()->route('workflows.index')
            ->with('success', "Workflow '{$workflowName}' deleted successfully.");
    }

    /**
     * Activate a workflow
     */
    public function activate(GroupWorkflow $workflow)
    {
        $workflow->update([
            'status' => 'active',
            'is_active' => true,
            'started_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        // Initialize executions for matching devices
        $count = $this->groupService->initializeWorkflowExecutions($workflow);

        WorkflowLog::info($workflow->id, "Workflow activated with {$count} pending executions", null, null, [
            'activated_by' => Auth::user()->name,
        ]);

        return back()->with('success', "Workflow activated. {$count} devices queued.");
    }

    /**
     * Pause a workflow
     */
    public function pause(GroupWorkflow $workflow)
    {
        $workflow->update([
            'status' => 'paused',
            'updated_by' => Auth::id(),
        ]);

        WorkflowLog::info($workflow->id, 'Workflow paused', null, null, [
            'paused_by' => Auth::user()->name,
        ]);

        return back()->with('success', 'Workflow paused.');
    }

    /**
     * Resume a paused workflow
     */
    public function resume(GroupWorkflow $workflow)
    {
        $workflow->update([
            'status' => 'active',
            'updated_by' => Auth::id(),
        ]);

        WorkflowLog::info($workflow->id, 'Workflow resumed', null, null, [
            'resumed_by' => Auth::user()->name,
        ]);

        return back()->with('success', 'Workflow resumed.');
    }

    /**
     * Cancel a workflow
     */
    public function cancel(GroupWorkflow $workflow)
    {
        // Cancel all pending executions
        $cancelled = $workflow->executions()
            ->whereIn('status', ['pending', 'queued'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

        $workflow->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        WorkflowLog::info($workflow->id, "Workflow cancelled, {$cancelled} executions cancelled", null, null, [
            'cancelled_by' => Auth::user()->name,
        ]);

        return back()->with('success', "Workflow cancelled. {$cancelled} pending executions cancelled.");
    }

    /**
     * Retry failed executions
     */
    public function retryFailed(GroupWorkflow $workflow)
    {
        $retried = $workflow->executions()
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'attempt' => 0,
                'next_retry_at' => null,
                'result' => null,
            ]);

        // Make sure workflow is active
        if ($workflow->status !== 'active') {
            $workflow->update([
                'status' => 'active',
                'is_active' => true,
            ]);
        }

        WorkflowLog::info($workflow->id, "Reset {$retried} failed executions for retry", null, null, [
            'retried_by' => Auth::user()->name,
        ]);

        return back()->with('success', "{$retried} failed executions queued for retry.");
    }

    /**
     * View execution details
     */
    public function showExecution(GroupWorkflow $workflow, WorkflowExecution $execution)
    {
        $execution->load(['device', 'task', 'logs']);

        return view('workflows.execution', compact('workflow', 'execution'));
    }

    /**
     * Get workflow stats (AJAX)
     */
    public function stats(GroupWorkflow $workflow)
    {
        return response()->json([
            'stats' => $workflow->getStats(),
            'progress' => $workflow->getProgressPercent(),
            'status' => $workflow->status,
            'is_running' => $workflow->isRunning(),
        ]);
    }
}
