@extends('layouts.app')

@section('title', 'Edit Workflow')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('workflows.show', $workflow) }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Workflow</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Edit Workflow: {{ $workflow->name }}</h1>

        @if($errors->any())
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('workflows.update', $workflow) }}" method="POST" x-data="workflowForm()">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                {{-- Basic Info --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="device_group_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Device Group *</label>
                        <select name="device_group_id" id="device_group_id" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a group...</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ (old('device_group_id', $workflow->device_group_id) == $group->id) ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workflow Name *</label>
                        <input type="text" name="name" id="name" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            value="{{ old('name', $workflow->name) }}"
                            placeholder="e.g., Upgrade to Firmware 25.03">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="2"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">{{ old('description', $workflow->description) }}</textarea>
                </div>

                {{-- Task Configuration --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Task Configuration</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="task_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Task Type *</label>
                            <select name="task_type" id="task_type" required x-model="taskType"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @foreach($taskTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('task_type', $workflow->task_type) == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="depends_on_workflow_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Depends On (Optional)</label>
                            <select name="depends_on_workflow_id" id="depends_on_workflow_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">No dependency</option>
                                @foreach($availableWorkflows as $wf)
                                    <option value="{{ $wf->id }}" {{ old('depends_on_workflow_id', $workflow->depends_on_workflow_id) == $wf->id ? 'selected' : '' }}>
                                        {{ $wf->deviceGroup->name }}: {{ $wf->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">This workflow will only run after the dependency completes for each device</p>
                        </div>
                    </div>

                    {{-- Firmware Upgrade Parameters --}}
                    <div x-show="taskType === 'firmware_upgrade'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                        {{-- Device Type Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Device Type *</label>
                            <select x-model="selectedDeviceType" @change="selectedFirmwareId = ''; useActiveFirmware = false"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="">Select device type...</option>
                                @foreach($deviceTypes as $dt)
                                    <option value="{{ $dt->id }}">{{ $dt->name }} ({{ $dt->manufacturer }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select the device type to see available firmware</p>
                        </div>

                        {{-- Use Active Firmware Checkbox --}}
                        <div x-show="selectedDeviceType">
                            <label class="inline-flex items-center">
                                <input type="checkbox" x-model="useActiveFirmware" @change="if(useActiveFirmware) selectedFirmwareId = ''"
                                    class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Use Active Firmware</span>
                                <span class="ml-2 text-xs text-green-600 dark:text-green-400" x-show="getActiveFirmwareForType()"
                                    x-text="'(Currently: ' + (getActiveFirmwareForType()?.version || 'None') + ')'"></span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
                                Automatically uses whatever firmware is marked as "active" at execution time. Useful for ongoing upgrades.
                            </p>
                        </div>

                        {{-- Specific Firmware Selection --}}
                        <div x-show="selectedDeviceType && !useActiveFirmware">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firmware Version *</label>
                            <select x-model="selectedFirmwareId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="">Select firmware...</option>
                                <template x-for="fw in getFirmwaresForType()" :key="fw.id">
                                    <option :value="fw.id" x-text="fw.version + ' (' + fw.file_name + ')' + (fw.is_active ? ' ★ ACTIVE' : '')"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Hidden inputs for form submission --}}
                        <input type="hidden" name="task_parameters[device_type_id]" :value="selectedDeviceType">
                        <input type="hidden" name="task_parameters[firmware_id]" :value="useActiveFirmware ? '' : selectedFirmwareId">
                        <input type="hidden" name="task_parameters[use_active_firmware]" :value="useActiveFirmware ? '1' : '0'">

                        {{-- No firmware warning --}}
                        <div x-show="selectedDeviceType && getFirmwaresForType().length === 0"
                            class="bg-yellow-50 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 p-3 rounded-lg text-sm">
                            No firmware available for this device type. Please upload firmware first.
                        </div>
                    </div>

                    {{-- Set Parameters --}}
                    <div x-show="taskType === 'set_parameter_values'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Parameters (JSON)</label>
                        <textarea name="task_parameters_json" rows="6"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white font-mono text-sm"
                            placeholder='{"values": {"Device.ManagementServer.PeriodicInformInterval": "600"}}'>{{ json_encode($workflow->task_parameters ?? [], JSON_PRETTY_PRINT) }}</textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use {serial_number}, {oui}, etc. for variable substitution</p>
                    </div>

                    {{-- Download Parameters --}}
                    <div x-show="taskType === 'download'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">File Type</label>
                            <select name="task_parameters[file_type]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="1 Firmware Upgrade Image" {{ ($workflow->task_parameters['file_type'] ?? '') == '1 Firmware Upgrade Image' ? 'selected' : '' }}>1 Firmware Upgrade Image</option>
                                <option value="3 Vendor Configuration File" {{ ($workflow->task_parameters['file_type'] ?? '') == '3 Vendor Configuration File' ? 'selected' : '' }}>3 Vendor Configuration File</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Download URL</label>
                            <input type="url" name="task_parameters[url]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                value="{{ $workflow->task_parameters['url'] ?? '' }}"
                                placeholder="https://hayacs.hay.net/files/config.xml">
                        </div>
                    </div>
                </div>

                {{-- Schedule Configuration --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Schedule</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="schedule_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Schedule Type *</label>
                            <select name="schedule_type" id="schedule_type" required x-model="scheduleType"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @foreach($scheduleTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('schedule_type', $workflow->schedule_type) == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="rate_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate Limit (per hour)</label>
                            <input type="number" name="rate_limit" id="rate_limit" min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('rate_limit', $workflow->rate_limit) }}"
                                placeholder="0 = unlimited">
                        </div>
                    </div>

                    {{-- Scheduled Time --}}
                    <div x-show="scheduleType === 'scheduled'" class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start At</label>
                            <input type="datetime-local" name="schedule_config[start_at]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ isset($workflow->schedule_config['start_at']) ? \Carbon\Carbon::parse($workflow->schedule_config['start_at'])->format('Y-m-d\TH:i') : '' }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End At (Optional)</label>
                            <input type="datetime-local" name="schedule_config[end_at]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ isset($workflow->schedule_config['end_at']) ? \Carbon\Carbon::parse($workflow->schedule_config['end_at'])->format('Y-m-d\TH:i') : '' }}">
                        </div>
                    </div>

                    {{-- Recurring Schedule --}}
                    <div x-show="scheduleType === 'recurring'" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Days of Week</label>
                            <div class="flex flex-wrap gap-2">
                                @php $selectedDays = $workflow->schedule_config['days'] ?? []; @endphp
                                @foreach(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="schedule_config[days][]" value="{{ $key }}"
                                            {{ in_array($key, $selectedDays) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                                        <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time</label>
                                <input type="time" name="schedule_config[start_time]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    value="{{ $workflow->schedule_config['start_time'] ?? '02:00' }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time</label>
                                <input type="time" name="schedule_config[end_time]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    value="{{ $workflow->schedule_config['end_time'] ?? '05:00' }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                                <select name="schedule_config[timezone]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="America/Toronto" {{ ($workflow->schedule_config['timezone'] ?? '') == 'America/Toronto' ? 'selected' : '' }}>America/Toronto (EST)</option>
                                    <option value="UTC" {{ ($workflow->schedule_config['timezone'] ?? '') == 'UTC' ? 'selected' : '' }}>UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Advanced Options --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Advanced Options</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="retry_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retry Count</label>
                            <input type="number" name="retry_count" id="retry_count" min="0" max="10"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('retry_count', $workflow->retry_count) }}">
                        </div>

                        <div>
                            <label for="retry_delay_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retry Delay (minutes)</label>
                            <input type="number" name="retry_delay_minutes" id="retry_delay_minutes" min="1"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('retry_delay_minutes', $workflow->retry_delay_minutes) }}">
                        </div>

                        <div>
                            <label for="stop_on_failure_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stop on Failure %</label>
                            <input type="number" name="stop_on_failure_percent" id="stop_on_failure_percent" min="0" max="100"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('stop_on_failure_percent', $workflow->stop_on_failure_percent) }}"
                                placeholder="0 = never stop">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="run_once_per_device" value="1"
                                {{ $workflow->run_once_per_device ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Run once per device (don't repeat for devices that completed)</span>
                        </label>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                    {{-- Delete button - triggers the separate delete form via JavaScript --}}
                    <button type="button"
                        onclick="if(confirm('Are you sure you want to delete this workflow? This will also delete all execution history.')) { document.getElementById('delete-workflow-form').submit(); }"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Delete Workflow
                    </button>

                    <div class="flex gap-3">
                        <a href="{{ route('workflows.show', $workflow) }}"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Update Workflow
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Delete form - must be OUTSIDE the main edit form to avoid nested form issues --}}
        <form id="delete-workflow-form" action="{{ route('workflows.destroy', $workflow) }}" method="POST" class="hidden"
            onsubmit="return confirm('Are you sure you want to delete this workflow? This will also delete all execution history.')">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

<script>
function workflowForm() {
    return {
        taskType: '{{ old('task_type', $workflow->task_type) }}',
        scheduleType: '{{ old('schedule_type', $workflow->schedule_type) }}',
        selectedDeviceType: '{{ $workflow->task_parameters['device_type_id'] ?? '' }}',
        selectedFirmwareId: '{{ $workflow->task_parameters['firmware_id'] ?? '' }}',
        useActiveFirmware: {{ ($workflow->task_parameters['use_active_firmware'] ?? '0') == '1' ? 'true' : 'false' }},
        firmwaresByDeviceType: @json($firmwaresByDeviceType),

        getFirmwaresForType() {
            if (!this.selectedDeviceType) return [];
            return this.firmwaresByDeviceType[this.selectedDeviceType] || [];
        },

        getActiveFirmwareForType() {
            const firmwares = this.getFirmwaresForType();
            return firmwares.find(fw => fw.is_active) || null;
        }
    }
}
</script>
@endsection
