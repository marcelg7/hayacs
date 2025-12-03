@extends('layouts.app')

@section('title', 'Workflows')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Workflows</h1>
        <a href="{{ route('workflows.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Workflow
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter by Group --}}
    <div class="mb-4">
        <form method="GET" class="flex gap-2 items-center">
            <label class="text-sm text-gray-600 dark:text-gray-400">Filter by group:</label>
            <select name="group_id" onchange="this.form.submit()"
                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">All Groups</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Workflow</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Group</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Schedule</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($workflows as $workflow)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <a href="{{ route('workflows.show', $workflow) }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                {{ $workflow->name }}
                            </a>
                            @if($workflow->depends_on_workflow_id)
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Depends on: {{ $workflow->dependsOn->name ?? 'Unknown' }}
                                </p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('device-groups.show', $workflow->deviceGroup) }}" class="text-gray-600 dark:text-gray-300 hover:underline">
                                {{ $workflow->deviceGroup->name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                            {{ \App\Models\GroupWorkflow::TASK_TYPES[$workflow->task_type] ?? $workflow->task_type }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                            {{ \App\Models\GroupWorkflow::SCHEDULE_TYPES[$workflow->schedule_type] ?? $workflow->schedule_type }}
                            @if($workflow->rate_limit > 0)
                                <span class="text-xs text-gray-400">({{ $workflow->rate_limit }}/hr)</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($workflow->stats['total'] > 0)
                                <div class="w-24">
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ $workflow->getProgressPercent() }}%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $workflow->stats['completed'] }}/{{ $workflow->stats['total'] }}
                                    </p>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">No executions</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($workflow->status === 'active') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                @elseif($workflow->status === 'paused') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                                @elseif($workflow->status === 'completed') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                                @elseif($workflow->status === 'cancelled') bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                @else bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200
                                @endif">
                                {{ ucfirst($workflow->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('workflows.show', $workflow) }}" class="text-blue-600 dark:text-blue-400 hover:underline mr-2">View</a>
                            <a href="{{ route('workflows.edit', $workflow) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            No workflows yet. <a href="{{ route('workflows.create') }}" class="text-blue-600 hover:underline">Create your first workflow</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $workflows->links() }}
    </div>
</div>
@endsection
