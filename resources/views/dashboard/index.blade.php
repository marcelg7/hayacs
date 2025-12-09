@extends('layouts.app')

@section('title', 'Dashboard - TR-069 ACS')

@section('content')
<div class="space-y-6">
    <!-- Mobile Quick Search (Prominent for field techs) -->
    <div class="bg-blue-600 dark:bg-blue-700 rounded-lg shadow-lg p-4 sm:p-6">
        <form action="{{ route('quick-search') }}" method="GET" class="space-y-3">
            <div class="flex items-center space-x-2 text-white mb-2">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <span class="text-lg font-semibold">Quick Device Search</span>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    placeholder="Enter serial number (last 4-6 characters)"
                    class="flex-1 px-4 py-3 text-lg rounded-lg border-0 focus:ring-2 focus:ring-white dark:bg-slate-700 dark:text-white dark:placeholder-gray-400"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    inputmode="text"
                    required
                    minlength="3"
                >
                <button
                    type="submit"
                    class="px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition-colors touch-manipulation"
                >
                    <span class="hidden sm:inline">Find Device</span>
                    <span class="sm:hidden">Search</span>
                </button>
            </div>
            <p class="text-blue-100 text-sm">
                Single match goes directly to WiFi setup. Partial serials work.
            </p>
        </form>
    </div>

    @if(session('error'))
        <div class="rounded-md bg-red-50 dark:bg-red-900/50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-gray-100 sm:text-3xl sm:truncate">
                Dashboard
            </h2>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Total Devices -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Devices</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total_devices'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Online Devices -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Online</dt>
                            <dd class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $stats['online_devices'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offline Devices -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Offline</dt>
                            <dd class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $stats['offline_devices'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Tasks -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Tasks</dt>
                            <dd class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_tasks'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Tasks -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed Tasks</dt>
                            <dd class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $stats['completed_tasks'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failed Tasks -->
        <div class="bg-white dark:bg-slate-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Failed Tasks</dt>
                            <dd class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $stats['failed_tasks'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Devices -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-slate-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Recent Devices</h3>
        </div>
        {{-- Mobile: Card layout --}}
        <div class="sm:hidden divide-y divide-gray-200 dark:divide-slate-700">
            @forelse($recentDevices as $device)
            <a href="{{ route('device.show', $device->id) }}" class="block p-4 hover:bg-gray-50 dark:hover:bg-slate-700 touch-manipulation">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400 truncate">{{ $device->id }}</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 mt-1">{{ $device->manufacturer }} {{ $device->display_name }}</p>
                        @if($device->subscriber)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ $device->subscriber->name }}</p>
                        @endif
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}</p>
                    </div>
                    <div class="ml-3 flex-shrink-0 flex items-center space-x-2">
                        @if($device->online)
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Online</span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Offline</span>
                        @endif
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>
            </a>
            @empty
            <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                No devices found. Devices will appear here once they connect to the ACS.
            </div>
            @endforelse
        </div>
        {{-- Desktop: Table layout --}}
        <div class="hidden sm:block overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Device ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subscriber</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Manufacturer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Inform</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse($recentDevices as $device)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                {{ $device->id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            @if($device->subscriber)
                                <a href="{{ route('subscribers.show', $device->subscriber->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                    {{ $device->subscriber->name }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $device->manufacturer }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $device->display_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($device->online)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Online</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Offline</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No devices found. Devices will appear here once they connect to the ACS.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-slate-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Recent Tasks</h3>
        </div>
        {{-- Mobile: Card layout --}}
        <div class="sm:hidden divide-y divide-gray-200 dark:divide-slate-700">
            @forelse($recentTasks as $task)
            <a href="{{ route('device.show', $task->device_id) }}" class="block p-4 hover:bg-gray-50 dark:hover:bg-slate-700 touch-manipulation">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $task->task_type }}</span>
                            @if($task->status === 'pending')
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Pending</span>
                            @elseif($task->status === 'sent')
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Sent</span>
                            @elseif($task->status === 'completed')
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Done</span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Failed</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $task->device->id ?? 'Unknown' }} &bull; {{ $task->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <svg class="h-5 w-5 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </a>
            @empty
            <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">No tasks found.</div>
            @endforelse
        </div>
        {{-- Desktop: Table layout --}}
        <div class="hidden sm:block overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Device</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse($recentTasks as $task)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <a href="{{ route('device.show', $task->device_id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                {{ $task->device->id ?? 'Unknown' }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $task->task_type }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($task->status === 'pending')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Pending</span>
                            @elseif($task->status === 'sent')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Sent</span>
                            @elseif($task->status === 'completed')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Completed</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Failed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $task->created_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No tasks found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
