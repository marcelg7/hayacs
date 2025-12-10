<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\DeviceGroupRule;
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
     * Get all devices that match a group's rules (optimized with database query)
     */
    public function getDevicesForGroup(DeviceGroup $group): Collection
    {
        $query = $this->buildQueryForGroup($group);

        if ($query === null) {
            // Fallback to PHP filtering if rules can't be converted to SQL
            return Device::all()->filter(fn($device) => $group->matchesDevice($device));
        }

        return $query->get();
    }

    /**
     * Get count of devices that match a group's rules (optimized with database COUNT)
     */
    public function getDeviceCountForGroup(DeviceGroup $group): int
    {
        $query = $this->buildQueryForGroup($group);

        if ($query === null) {
            // Fallback to PHP filtering if rules can't be converted to SQL
            return Device::all()->filter(fn($device) => $group->matchesDevice($device))->count();
        }

        return $query->count();
    }

    /**
     * Build an optimized database query for a group's rules
     * Returns null if rules cannot be fully translated to SQL
     */
    private function buildQueryForGroup(DeviceGroup $group): ?\Illuminate\Database\Eloquent\Builder
    {
        $rules = $group->rules;

        if ($rules->isEmpty()) {
            return Device::query()->whereRaw('1 = 0'); // No rules = no matches
        }

        // Check if all rules can be converted to SQL
        foreach ($rules as $rule) {
            if (!$this->canConvertRuleToSql($rule)) {
                return null; // Fall back to PHP filtering
            }
        }

        $query = Device::query();

        if ($group->match_type === 'all') {
            // AND logic - all rules must match
            foreach ($rules as $rule) {
                $this->applyRuleToQuery($query, $rule, 'and');
            }
        } else {
            // OR logic - any rule must match
            $query->where(function ($q) use ($rules) {
                foreach ($rules as $rule) {
                    $this->applyRuleToQuery($q, $rule, 'or');
                }
            });
        }

        return $query;
    }

    /**
     * Check if a rule can be converted to SQL
     */
    private function canConvertRuleToSql(DeviceGroupRule $rule): bool
    {
        // These fields/operators require PHP processing
        $phpOnlyFields = ['tags']; // tags might be JSON array
        $phpOnlyOperators = ['regex']; // MySQL REGEXP syntax differs

        if (in_array($rule->field, $phpOnlyFields)) {
            return false;
        }

        if (in_array($rule->operator, $phpOnlyOperators)) {
            return false;
        }

        return true;
    }

    /**
     * Apply a rule to a query builder
     */
    private function applyRuleToQuery($query, DeviceGroupRule $rule, string $boolean = 'and'): void
    {
        $field = $rule->field;
        $value = $rule->value;
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $methodRaw = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

        // Handle special field: data_model (computed from parameters table)
        if ($field === 'data_model') {
            $this->applyDataModelRule($query, $rule, $boolean);
            return;
        }

        // Handle boolean field mappings (convert 'true'/'false' to 1/0)
        if ($field === 'online' || $field === 'initial_backup_created') {
            $value = strtolower($value) === 'true' ? 1 : 0;
        }

        match ($rule->operator) {
            'equals' => $query->$methodRaw("LOWER({$field}) = LOWER(?)", [$value]),
            'not_equals' => $query->$methodRaw("LOWER({$field}) != LOWER(?)", [$value]),
            'contains' => $query->$methodRaw("LOWER({$field}) LIKE LOWER(?)", ['%' . $value . '%']),
            'not_contains' => $query->$methodRaw("LOWER({$field}) NOT LIKE LOWER(?)", ['%' . $value . '%']),
            'starts_with' => $query->$methodRaw("LOWER({$field}) LIKE LOWER(?)", [$value . '%']),
            'ends_with' => $query->$methodRaw("LOWER({$field}) LIKE LOWER(?)", ['%' . $value]),
            'less_than' => $query->$method($field, '<', $value),
            'greater_than' => $query->$method($field, '>', $value),
            'less_than_or_equals' => $query->$method($field, '<=', $value),
            'greater_than_or_equals' => $query->$method($field, '>=', $value),
            'in' => $query->$method(function ($q) use ($field, $value) {
                $list = json_decode($value, true) ?? array_map('trim', explode(',', $value));
                $q->whereRaw("LOWER({$field}) IN (" . implode(',', array_fill(0, count($list), 'LOWER(?)')) . ")", $list);
            }),
            'not_in' => $query->$method(function ($q) use ($field, $value) {
                $list = json_decode($value, true) ?? array_map('trim', explode(',', $value));
                $q->whereRaw("LOWER({$field}) NOT IN (" . implode(',', array_fill(0, count($list), 'LOWER(?)')) . ")", $list);
            }),
            'is_null' => $query->$method(function ($q) use ($field) {
                $q->whereNull($field)->orWhere($field, '=', '');
            }),
            'is_not_null' => $query->$method(function ($q) use ($field) {
                $q->whereNotNull($field)->where($field, '!=', '');
            }),
            default => null,
        };
    }

    /**
     * Apply a data_model rule using a subquery on the parameters table
     * TR-098 devices have parameters starting with 'InternetGatewayDevice.'
     * TR-181 devices have parameters starting with 'Device.' (but not IGD)
     */
    private function applyDataModelRule($query, DeviceGroupRule $rule, string $boolean = 'and'): void
    {
        $value = strtoupper($rule->value);
        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        $methodNot = $boolean === 'or' ? 'orWhereNotExists' : 'whereNotExists';

        // Subquery to check for InternetGatewayDevice parameters
        $igdSubquery = function ($q) {
            $q->selectRaw('1')
                ->from('parameters')
                ->whereColumn('parameters.device_id', 'devices.id')
                ->where('parameters.name', 'like', 'InternetGatewayDevice.%')
                ->limit(1);
        };

        if ($rule->operator === 'equals') {
            if ($value === 'TR-098') {
                // TR-098: Has IGD parameters
                $query->$method($igdSubquery);
            } else {
                // TR-181: Does NOT have IGD parameters
                $query->$methodNot($igdSubquery);
            }
        } elseif ($rule->operator === 'not_equals') {
            if ($value === 'TR-098') {
                // Not TR-098 means TR-181: Does NOT have IGD parameters
                $query->$methodNot($igdSubquery);
            } else {
                // Not TR-181 means TR-098: Has IGD parameters
                $query->$method($igdSubquery);
            }
        }
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
     * Process workflows when a device connects
     * Handles both "on_connect" and "immediate" workflows for new group members
     */
    public function processWorkflowsForDevice(Device $device): array
    {
        $queued = [];

        $workflows = $this->getActiveWorkflowsForDevice($device)
            ->filter(fn($workflow) => in_array($workflow->schedule_type, ['on_connect', 'immediate']));

        foreach ($workflows as $workflow) {
            $execution = $this->queueWorkflowForDevice($workflow, $device);
            if ($execution) {
                $queued[] = [
                    'workflow_id' => $workflow->id,
                    'workflow_name' => $workflow->name,
                    'execution_id' => $execution->id,
                    'schedule_type' => $workflow->schedule_type,
                ];

                Log::info("Workflow queued for device on connect", [
                    'device_id' => $device->id,
                    'workflow_id' => $workflow->id,
                    'workflow_name' => $workflow->name,
                    'schedule_type' => $workflow->schedule_type,
                ]);
            }
        }

        return $queued;
    }

    /**
     * @deprecated Use processWorkflowsForDevice() instead
     */
    public function processOnConnectWorkflows(Device $device): void
    {
        $this->processWorkflowsForDevice($device);
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
