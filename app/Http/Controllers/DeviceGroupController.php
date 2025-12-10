<?php

namespace App\Http\Controllers;

use App\Models\DeviceGroup;
use App\Models\DeviceGroupRule;
use App\Services\DeviceGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceGroupController extends Controller
{
    public function __construct(
        private DeviceGroupService $groupService
    ) {}

    /**
     * Display a listing of device groups
     */
    public function index()
    {
        $groups = DeviceGroup::with(['rules', 'workflows', 'creator'])
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->paginate(20);

        // Add device counts
        foreach ($groups as $group) {
            $group->device_count = $this->groupService->getDeviceCountForGroup($group);
        }

        return view('device-groups.index', compact('groups'));
    }

    /**
     * Show the form for creating a new group
     */
    public function create()
    {
        $fields = DeviceGroupRule::MATCHABLE_FIELDS;
        $operators = DeviceGroupRule::OPERATORS;
        $fieldValues = $this->getDistinctFieldValues();

        return view('device-groups.create', compact('fields', 'operators', 'fieldValues'));
    }

    /**
     * Store a newly created group
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'match_type' => 'required|in:all,any',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'rules' => 'required|array|min:1',
            'rules.*.field' => 'required|string',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'nullable|string',
        ]);

        $group = DeviceGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'match_type' => $validated['match_type'],
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // Create rules
        foreach ($validated['rules'] as $index => $rule) {
            $group->rules()->create([
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'] ?? null,
                'order' => $index,
            ]);
        }

        return redirect()->route('device-groups.show', $group)
            ->with('success', 'Device group created successfully.');
    }

    /**
     * Display the specified group
     */
    public function show(DeviceGroup $deviceGroup)
    {
        $deviceGroup->load(['rules', 'workflows.executions', 'workflows.dependsOn', 'creator', 'updater']);

        // Sort workflows by dependency order (workflows with no dependencies first, then by dependency chain)
        $sortedWorkflows = $this->sortWorkflowsByDependency($deviceGroup->workflows);

        $stats = $this->groupService->getGroupStats($deviceGroup);
        $matchingDevices = $this->groupService->getDevicesForGroup($deviceGroup)->take(100);

        return view('device-groups.show', compact('deviceGroup', 'stats', 'matchingDevices', 'sortedWorkflows'));
    }

    /**
     * Sort workflows by dependency order (topological sort)
     */
    private function sortWorkflowsByDependency($workflows)
    {
        $sorted = collect();
        $remaining = $workflows->keyBy('id');
        $maxIterations = $remaining->count() * 2; // Prevent infinite loops
        $iterations = 0;

        while ($remaining->isNotEmpty() && $iterations < $maxIterations) {
            $iterations++;

            foreach ($remaining as $id => $workflow) {
                // If no dependency, or dependency is already sorted, or dependency is not in this group
                $dependsOnId = $workflow->depends_on_workflow_id;

                if (!$dependsOnId ||
                    $sorted->contains('id', $dependsOnId) ||
                    !$remaining->has($dependsOnId)) {
                    $sorted->push($workflow);
                    $remaining->forget($id);
                    break; // Start over to maintain order
                }
            }
        }

        // Add any remaining (circular dependencies) at the end
        foreach ($remaining as $workflow) {
            $sorted->push($workflow);
        }

        return $sorted;
    }

    /**
     * Show the form for editing the group
     */
    public function edit(DeviceGroup $deviceGroup)
    {
        $deviceGroup->load('rules');

        $fields = DeviceGroupRule::MATCHABLE_FIELDS;
        $operators = DeviceGroupRule::OPERATORS;
        $fieldValues = $this->getDistinctFieldValues();

        return view('device-groups.edit', compact('deviceGroup', 'fields', 'operators', 'fieldValues'));
    }

    /**
     * Update the specified group
     */
    public function update(Request $request, DeviceGroup $deviceGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'match_type' => 'required|in:all,any',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'rules' => 'required|array|min:1',
            'rules.*.field' => 'required|string',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'nullable|string',
        ]);

        $deviceGroup->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'match_type' => $validated['match_type'],
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'updated_by' => Auth::id(),
        ]);

        // Recreate rules
        $deviceGroup->rules()->delete();

        foreach ($validated['rules'] as $index => $rule) {
            $deviceGroup->rules()->create([
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'] ?? null,
                'order' => $index,
            ]);
        }

        return redirect()->route('device-groups.show', $deviceGroup)
            ->with('success', 'Device group updated successfully.');
    }

    /**
     * Remove the specified group
     */
    public function destroy(DeviceGroup $deviceGroup)
    {
        $deviceGroup->delete();

        return redirect()->route('device-groups.index')
            ->with('success', 'Device group deleted successfully.');
    }

    /**
     * Preview matching devices for rules (AJAX)
     */
    public function previewRules(Request $request)
    {
        $validated = $request->validate([
            'match_type' => 'required|in:all,any',
            'rules' => 'required|array|min:1',
            'rules.*.field' => 'required|string',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'nullable|string',
        ]);

        $devices = $this->groupService->previewRulesMatch(
            $validated['match_type'],
            $validated['rules']
        );

        return response()->json([
            'count' => $devices->count(),
            'devices' => $devices->take(20)->map(fn($d) => [
                'id' => $d->id,
                'serial_number' => $d->serial_number,
                'product_class' => $d->product_class,
                'software_version' => $d->software_version,
            ])->values(),
        ]);
    }

    /**
     * Toggle group active status
     */
    public function toggleActive(DeviceGroup $deviceGroup)
    {
        $deviceGroup->update([
            'is_active' => !$deviceGroup->is_active,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Group status updated.');
    }

    /**
     * Get distinct values for fields that should have dropdowns
     */
    private function getDistinctFieldValues(): array
    {
        $device = new \App\Models\Device();

        return [
            'oui' => \App\Models\Device::distinct()
                ->whereNotNull('oui')
                ->where('oui', '!=', '')
                ->orderBy('oui')
                ->pluck('oui')
                ->toArray(),

            'manufacturer' => \App\Models\Device::distinct()
                ->whereNotNull('manufacturer')
                ->where('manufacturer', '!=', '')
                ->orderBy('manufacturer')
                ->pluck('manufacturer')
                ->toArray(),

            'product_class' => \App\Models\Device::distinct()
                ->whereNotNull('product_class')
                ->where('product_class', '!=', '')
                ->orderBy('product_class')
                ->pluck('product_class')
                ->toArray(),

            'software_version' => \App\Models\Device::distinct()
                ->whereNotNull('software_version')
                ->where('software_version', '!=', '')
                ->orderBy('software_version')
                ->pluck('software_version')
                ->toArray(),

            'hardware_version' => \App\Models\Device::distinct()
                ->whereNotNull('hardware_version')
                ->where('hardware_version', '!=', '')
                ->orderBy('hardware_version')
                ->pluck('hardware_version')
                ->toArray(),

            'data_model' => ['TR-098', 'TR-181'],

            'online' => ['true', 'false'],

            'initial_backup_created' => ['true', 'false'],
        ];
    }
}
