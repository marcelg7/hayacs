@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">All Tasks</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">View and manage all tasks across all devices</p>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-yellow-600 dark:text-yellow-400 uppercase">Pending</div>
                <div class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($stats['pending']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase">Sent</div>
                <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ number_format($stats['sent']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-green-600 dark:text-green-400 uppercase">Completed</div>
                <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ number_format($stats['completed']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-red-600 dark:text-red-400 uppercase">Failed</div>
                <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($stats['failed']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Cancelled</div>
                <div class="mt-1 text-2xl font-semibold text-gray-600 dark:text-gray-400">{{ number_format($stats['cancelled']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-purple-600 dark:text-purple-400 uppercase">User</div>
                <div class="mt-1 text-2xl font-semibold text-purple-600 dark:text-purple-400">{{ number_format($stats['user_initiated']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-4">
                <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase">ACS</div>
                <div class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($stats['acs_initiated']) }}</div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <form method="GET" action="{{ route('admin.tasks.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    {{-- Status --}}
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>

                    {{-- Task Type --}}
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Task Type</label>
                        <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Types</option>
                            @foreach($taskTypes as $type)
                                <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ str_replace('_', ' ', ucwords($type, '_')) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Initiated By --}}
                    <div>
                        <label for="initiated_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Initiated By</label>
                        <select id="initiated_by" name="initiated_by" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Sources</option>
                            <option value="user" {{ request('initiated_by') === 'user' ? 'selected' : '' }}>Any User</option>
                            <option value="acs" {{ request('initiated_by') === 'acs' ? 'selected' : '' }}>ACS (System)</option>
                            @foreach($initiators as $user)
                                <option value="{{ $user->id }}" {{ request('initiated_by') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Device Serial --}}
                    <div>
                        <label for="serial" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Device Serial</label>
                        <input type="text" id="serial" name="serial" value="{{ request('serial') }}" placeholder="Search serial..."
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    {{-- Date Range --}}
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Date</label>
                        <input type="date" id="from" name="from" value="{{ request('from') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">To Date</label>
                        <input type="date" id="to" name="to" value="{{ request('to') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="lg:col-span-6 flex items-end gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Filter
                        </button>
                        <a href="{{ route('admin.tasks.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Session Messages --}}
        @if(session('success'))
            <div class="mb-4 bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-md p-4">
                <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-700 rounded-md p-4">
                <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Tasks Table --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Device</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Initiated By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tasks as $task)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">#{{ $task->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($task->device)
                                <a href="{{ route('device.show', $task->device_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $task->device->serial_number }}
                                </a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $task->device->manufacturer }}</div>
                            @else
                                <span class="text-gray-400">Unknown</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            {{ str_replace('_', ' ', ucwords($task->task_type, '_')) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" title="{{ $task->description }}">
                            {{ $task->description ? Str::limit($task->description, 40) : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($task->initiated_by_user_id)
                                <span class="inline-flex items-center text-purple-700 dark:text-purple-300">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    {{ $task->initiator?->name ?? 'Unknown' }}
                                </span>
                            @else
                                <span class="inline-flex items-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    ACS
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($task->status === 'pending')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pending</span>
                            @elseif($task->status === 'sent')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Sent</span>
                            @elseif($task->status === 'completed')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
                            @elseif($task->status === 'failed')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                            @elseif($task->status === 'cancelled')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Cancelled</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ ucfirst($task->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $task->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($task->status === 'pending')
                                <form action="{{ route('admin.tasks.cancel', $task) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Cancel this task?')" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">Cancel</button>
                                </form>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No tasks found matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($tasks->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $tasks->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
