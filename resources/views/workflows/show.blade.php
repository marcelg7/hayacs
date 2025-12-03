@extends('layouts.app')

@section('title', $workflow->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6 flex justify-between items-start">
        <div>
            <a href="{{ route('workflows.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Workflows</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $workflow->name }}</h1>
            <p class="text-gray-600 dark:text-gray-400">
                Group: <a href="{{ route('device-groups.show', $workflow->deviceGroup) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $workflow->deviceGroup->name }}</a>
            </p>
        </div>
        <div class="flex gap-2">
            @if($workflow->status === 'draft')
                <form action="{{ route('workflows.activate', $workflow) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Activate
                    </button>
                </form>
            @elseif($workflow->status === 'active')
                <form action="{{ route('workflows.pause', $workflow) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                        Pause
                    </button>
                </form>
            @elseif($workflow->status === 'paused')
                <form action="{{ route('workflows.resume', $workflow) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Resume
                    </button>
                </form>
            @endif

            @if(in_array($workflow->status, ['active', 'paused']))
                <form action="{{ route('workflows.cancel', $workflow) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this workflow?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Cancel
                    </button>
                </form>
            @endif

            <a href="{{ route('workflows.edit', $workflow) }}"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Edit
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Workflow Details --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Status Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Workflow Status</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Status:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($workflow->status === 'active') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                            @elseif($workflow->status === 'paused') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                            @elseif($workflow->status === 'completed') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                            @else bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200
                            @endif">
                            {{ ucfirst($workflow->status) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Task Type:</span>
                        <span class="text-gray-900 dark:text-white">{{ \App\Models\GroupWorkflow::TASK_TYPES[$workflow->task_type] ?? $workflow->task_type }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Schedule:</span>
                        <span class="text-gray-900 dark:text-white">{{ \App\Models\GroupWorkflow::SCHEDULE_TYPES[$workflow->schedule_type] ?? $workflow->schedule_type }}</span>
                    </div>
                    @if($workflow->rate_limit > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Rate Limit:</span>
                            <span class="text-gray-900 dark:text-white">{{ $workflow->rate_limit }}/hour</span>
                        </div>
                    @endif
                    @if($workflow->depends_on_workflow_id)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Depends On:</span>
                            <a href="{{ route('workflows.show', $workflow->dependsOn) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $workflow->dependsOn->name }}
                            </a>
                        </div>
                    @endif
                    @if($workflow->started_at)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Started:</span>
                            <span class="text-gray-900 dark:text-white">{{ $workflow->started_at->format('M j, Y g:i A') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Progress Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Progress</h3>

                <div class="mb-4">
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-4">
                        <div class="bg-green-500 h-4 rounded-full transition-all" style="width: {{ $workflow->getProgressPercent() }}%"></div>
                    </div>
                    <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $workflow->getProgressPercent() }}% Complete
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="text-lg font-bold text-gray-600 dark:text-gray-300">{{ $stats['pending'] }}</div>
                        <div class="text-xs text-gray-500">Pending</div>
                    </div>
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded">
                        <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $stats['in_progress'] + $stats['queued'] }}</div>
                        <div class="text-xs text-blue-600 dark:text-blue-300">In Progress</div>
                    </div>
                    <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded">
                        <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ $stats['completed'] }}</div>
                        <div class="text-xs text-green-600 dark:text-green-300">Completed</div>
                    </div>
                    <div class="p-2 bg-red-50 dark:bg-red-900/30 rounded">
                        <div class="text-lg font-bold text-red-600 dark:text-red-400">{{ $stats['failed'] }}</div>
                        <div class="text-xs text-red-600 dark:text-red-300">Failed</div>
                    </div>
                </div>

                @if($stats['failed'] > 0)
                    <form action="{{ route('workflows.retry-failed', $workflow) }}" method="POST" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm">
                            Retry Failed ({{ $stats['failed'] }})
                        </button>
                    </form>
                @endif
            </div>

            {{-- Task Parameters --}}
            @if($workflow->task_parameters)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Task Parameters</h3>
                    <pre class="text-xs bg-gray-50 dark:bg-gray-700 p-3 rounded overflow-x-auto">{{ json_encode($workflow->task_parameters, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>

        {{-- Right Column: Executions and Logs --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Recent Executions --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Executions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($recentExecutions as $execution)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-3">
                                        @if($execution->device)
                                            <a href="{{ route('device.show', $execution->device_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $execution->device->serial_number }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">{{ $execution->device_id }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if($execution->status === 'completed') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                            @elseif($execution->status === 'failed') bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                            @elseif($execution->status === 'in_progress' || $execution->status === 'queued') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                                            @else bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $execution->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $execution->getDuration() }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $execution->updated_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No executions yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Logs --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Log</h3>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    @forelse($recentLogs as $log)
                        <div class="px-6 py-3 flex items-start gap-3">
                            <span class="flex-shrink-0 w-2 h-2 mt-2 rounded-full
                                @if($log->level === 'error') bg-red-500
                                @elseif($log->level === 'warning') bg-yellow-500
                                @else bg-blue-500
                                @endif"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $log->message }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->created_at->format('M j, g:i A') }}
                                    @if($log->device_id)
                                        &bull; {{ $log->device_id }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No log entries yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
