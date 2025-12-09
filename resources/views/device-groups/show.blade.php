@extends('layouts.app')

@section('title', $deviceGroup->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <a href="{{ route('device-groups.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Groups</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $deviceGroup->name }}</h1>
            @if($deviceGroup->description)
                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $deviceGroup->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('device-groups.edit', $deviceGroup) }}"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Edit Group
            </a>
            <a href="{{ route('workflows.create', ['group_id' => $deviceGroup->id]) }}"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Add Workflow
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Group Info --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Status Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Group Status</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Status:</span>
                        @if($deviceGroup->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Active</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200">Inactive</span>
                        @endif
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Match Type:</span>
                        <span class="text-gray-900 dark:text-white">{{ $deviceGroup->match_type === 'all' ? 'All Rules (AND)' : 'Any Rule (OR)' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Priority:</span>
                        <span class="text-gray-900 dark:text-white">{{ $deviceGroup->priority }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Created:</span>
                        <span class="text-gray-900 dark:text-white">{{ $deviceGroup->created_at->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Stats Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Statistics</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['device_count']) }}</div>
                        <div class="text-sm text-blue-800 dark:text-blue-200">Devices</div>
                    </div>
                    <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['workflow_count'] }}</div>
                        <div class="text-sm text-purple-800 dark:text-purple-200">Workflows</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['completed_executions']) }}</div>
                        <div class="text-sm text-green-800 dark:text-green-200">Completed</div>
                    </div>
                    <div class="text-center p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($stats['failed_executions']) }}</div>
                        <div class="text-sm text-red-800 dark:text-red-200">Failed</div>
                    </div>
                </div>
            </div>

            {{-- Rules Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Membership Rules</h3>
                <div class="space-y-2">
                    @foreach($deviceGroup->rules as $index => $rule)
                        @if($index > 0)
                            <div class="text-center text-xs text-gray-400 dark:text-gray-500 py-1">
                                {{ $deviceGroup->match_type === 'all' ? 'AND' : 'OR' }}
                            </div>
                        @endif
                        <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ \App\Models\DeviceGroupRule::MATCHABLE_FIELDS[$rule->field] ?? $rule->field }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ \App\Models\DeviceGroupRule::OPERATORS[$rule->operator] ?? $rule->operator }}</span>
                            @if(!in_array($rule->operator, ['is_null', 'is_not_null']))
                                <span class="text-blue-600 dark:text-blue-400">"{{ $rule->value }}"</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right Column: Workflows and Devices --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Workflows --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Workflows</h3>
                    <a href="{{ route('workflows.create', ['group_id' => $deviceGroup->id]) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        + Add Workflow
                    </a>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($sortedWorkflows as $index => $workflow)
                        <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="flex justify-between items-start">
                                <div class="flex items-start gap-3">
                                    {{-- Step Number --}}
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ $index + 1 }}</span>
                                    </div>
                                    <div>
                                        <a href="{{ route('workflows.show', $workflow) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $workflow->name }}
                                        </a>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ \App\Models\GroupWorkflow::TASK_TYPES[$workflow->task_type] ?? $workflow->task_type }}
                                            &bull; {{ \App\Models\GroupWorkflow::SCHEDULE_TYPES[$workflow->schedule_type] ?? $workflow->schedule_type }}
                                        </p>
                                        @if($workflow->dependsOn)
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                <span class="inline-flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                    </svg>
                                                    After: {{ $workflow->dependsOn->name }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($workflow->status === 'active') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                    @elseif($workflow->status === 'paused') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                                    @elseif($workflow->status === 'completed') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                                    @else bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200
                                    @endif">
                                    {{ ucfirst($workflow->status) }}
                                </span>
                            </div>
                            @php $wfStats = $workflow->getStats(); @endphp
                            @if($wfStats['total'] > 0)
                                <div class="mt-2 ml-11">
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ $workflow->getProgressPercent() }}%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $wfStats['completed'] }}/{{ $wfStats['total'] }} completed
                                        @if($wfStats['failed'] > 0)
                                            ({{ $wfStats['failed'] }} failed)
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No workflows yet. <a href="{{ route('workflows.create', ['group_id' => $deviceGroup->id]) }}" class="text-blue-600 hover:underline">Create one</a>.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Matching Devices --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Matching Devices
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(showing first 100)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Serial</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Model</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Firmware</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($matchingDevices as $device)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $device->serial_number }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $device->display_name }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $device->software_version }}</td>
                                    <td class="px-6 py-3">
                                        @if($device->online)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Online</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200">Offline</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No devices match this group's rules.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
