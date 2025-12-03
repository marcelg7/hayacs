<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\GroupWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DeviceGroupService
{
    /**
     * Get all groups that a device belongs to
     */
    public function getGroupsForDevice(Device $device): Collection
    {
        return DeviceGroup::active()
            ->byPriority()
            ->with('rules')
            ->get()
            ->filter(fn($group) => $group->matchesDevice($device));
    }

    /**
     * Get all devices that match a group's rules
     */
    public function getDevicesForGroup(DeviceGroup $group): Collection
    {
        return Device::all()->filter(fn($device) => $group->matchesDevice($device));
    }

    /**
     * Get count of devices that match a group's rules
     */
    public function getDeviceCountForGroup(DeviceGroup $group): int
    {
        return $this->getDevicesForGroup($group)->count();
    }

    /**
     * Preview which devices would match given rules (before saving)
     */
    public function previewRulesMatch(string $matchType, array $rules): Collection
    {
        // Create a temporary group to test rules
        $tempGroup = new DeviceGroup([
            'match_type' => $matchType,
        ]);

        // Create temporary rules
        $tempRules = collect($rules)->map(function ($rule) use ($tempGroup) {
            return new \App\Models\DeviceGroupRule([
                'device_group_id' => 0,
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'] ?? null,
                'order' => $rule['order'] ?? 0,
            ]);
        });

        // Override the rules relationship temporarily
        $tempGroup->setRelation('rules', $tempRules);

        return Device::all()->filter(fn($device) => $tempGroup->matchesDevice($device));
    }

    /**
     * Get all active workflows that should run for a device
     */
    public function getActiveWorkflowsForDevice(Device $device): Collection
    {
        $groups = $this->getGroupsForDevice($device);

        return $groups->flatMap(function ($group) use ($device) {
            return $group->workflows()
                ->active()
                ->get()
                ->filter(fn($workflow) => $workflow->canRunForDevice($device));
        });
    }

    /**
     * Process "on_connect" workflows when a device connects
     */
    public function processOnConnectWorkflows(Device $device): void
    {
        $workflows = $this->getActiveWorkflowsForDevice($device)
            ->filter(fn($workflow) => $workflow->schedule_type === 'on_connect');

        foreach ($workflows as $workflow) {
            $this->queueWorkflowForDevice($workflow, $device);
        }
    }

    /**
     * Queue a workflow execution for a device
     */
    public function queueWorkflowForDevice(GroupWorkflow $workflow, Device $device): ?WorkflowExecution
    {
        // Check if already has an execution
        $existing = WorkflowExecution::where('group_workflow_id', $workflow->id)
            ->where('device_id', $device->id)
            ->first();

        if ($existing) {
            // If run_once and already completed/in_progress, skip
            if ($workflow->run_once_per_device &&
                in_array($existing->status, ['completed', 'in_progress', 'queued'])) {
                return null;
            }

            // If failed/cancelled and can retry, reset it
            if (in_array($existing->status, ['failed', 'cancelled', 'pending'])) {
                $existing->update([
                    'status' => 'pending',
                    'attempt' => 0,
                    'next_retry_at' => null,
                    'result' => null,
                ]);
                return $existing;
            }

            return null;
        }

        // Create new execution
        $execution = WorkflowExecution::create([
            'group_workflow_id' => $workflow->id,
            'device_id' => $device->id,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);

        WorkflowLog::info(
            $workflow->id,
            "Queued workflow for device {$device->id}",
            $execution->id,
            $device->id
        );

        return $execution;
    }

    /**
     * Initialize executions for all matching devices in a workflow
     */
    public function initializeWorkflowExecutions(GroupWorkflow $workflow): int
    {
        $group = $workflow->deviceGroup;
        $devices = $this->getDevicesForGroup($group);
        $count = 0;

        foreach ($devices as $device) {
            if ($workflow->canRunForDevice($device)) {
                $execution = $this->queueWorkflowForDevice($workflow, $device);
                if ($execution) {
                    $count++;
                }
            }
        }

        WorkflowLog::info(
            $workflow->id,
            "Initialized {$count} executions for workflow",
            null,
            null,
            ['total_matching_devices' => $devices->count()]
        );

        return $count;
    }

    /**
     * Refresh group membership for all devices
     * Useful after rules change
     */
    public function refreshGroupMembership(DeviceGroup $group): array
    {
        $devices = $this->getDevicesForGroup($group);

        // Get active on_connect workflows
        $onConnectWorkflows = $group->workflows()
            ->active()
            ->where('schedule_type', 'on_connect')
            ->get();

        $added = 0;
        foreach ($devices as $device) {
            foreach ($onConnectWorkflows as $workflow) {
                if ($workflow->canRunForDevice($device)) {
                    $execution = $this->queueWorkflowForDevice($workflow, $device);
                    if ($execution) {
                        $added++;
                    }
                }
            }
        }

        return [
            'matching_devices' => $devices->count(),
            'new_executions' => $added,
        ];
    }

    /**
     * Get summary statistics for a group
     */
    public function getGroupStats(DeviceGroup $group): array
    {
        $deviceCount = $this->getDeviceCountForGroup($group);
        $workflows = $group->workflows;

        $totalExecutions = 0;
        $completedExecutions = 0;
        $failedExecutions = 0;

        foreach ($workflows as $workflow) {
            $stats = $workflow->getStats();
            $totalExecutions += $stats['total'];
            $completedExecutions += $stats['completed'];
            $failedExecutions += $stats['failed'];
        }

        return [
            'device_count' => $deviceCount,
            'workflow_count' => $workflows->count(),
            'total_executions' => $totalExecutions,
            'completed_executions' => $completedExecutions,
            'failed_executions' => $failedExecutions,
        ];
    }
}
