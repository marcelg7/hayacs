@extends('layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Daily Activity Report</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $reportDate->format('l, F j, Y') }}</p>
            </div>

            <!-- Date Navigation -->
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.daily-activity', ['date' => $reportDate->copy()->subDay()->format('Y-m-d')]) }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Previous Day
                </a>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="date" value="{{ $reportDate->format('Y-m-d') }}"
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <button type="submit" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                        Go
                    </button>
                </form>
                @if(!$reportDate->isToday())
                <a href="{{ route('reports.daily-activity', ['date' => $reportDate->copy()->addDay()->format('Y-m-d')]) }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md transition-colors">
                    Next Day
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                @endif
                <a href="{{ route('reports.daily-activity', ['date' => $reportDate->format('Y-m-d'), 'refresh' => 1]) }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md transition-colors"
                   title="Refresh report data">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Cache Indicator -->
        @if(isset($cached) && $cached && isset($cacheTime))
        <div class="mb-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Cached {{ $cacheTime->diffForHumans() }} &bull; <a href="{{ route('reports.daily-activity', ['date' => $reportDate->format('Y-m-d'), 'refresh' => 1]) }}" class="text-blue-600 hover:underline dark:text-blue-400">Refresh</a></span>
        </div>
        @endif

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $taskStats['total'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Tasks</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $taskStats['completed'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Completed</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $taskStats['failed'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    @if($taskStats['avg_duration'])
                        {{ $taskStats['avg_duration'] < 60 ? $taskStats['avg_duration'] . 's' : round($taskStats['avg_duration'] / 60, 1) . 'm' }}
                    @else
                        N/A
                    @endif
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg Duration</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Activity -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        User Activity
                    </h2>
                </div>
                <div class="p-4">
                    @if($tasksByUser->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No user activity</p>
                    @else
                        <div class="space-y-3">
                            @foreach($tasksByUser as $user)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $user['user_name'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $user['devices'] }} device(s) managed
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $user['total'] }}</div>
                                        <div class="text-xs">
                                            <span class="text-green-600 dark:text-green-400">{{ $user['completed'] }} done</span>
                                            @if($user['failed'] > 0)
                                                <span class="text-red-600 dark:text-red-400 ml-2">{{ $user['failed'] }} failed</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Task Types -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Task Types
                    </h2>
                </div>
                <div class="p-4">
                    @if($tasksByType->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No tasks</p>
                    @else
                        <div class="space-y-2">
                            @foreach($tasksByType as $type => $count)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $type }}</span>
                                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs font-medium rounded">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Hourly Breakdown -->
        @if($hourlyBreakdown->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Hourly Activity
                </h2>
            </div>
            <div class="p-4 overflow-x-auto">
                <div class="flex gap-1 min-w-max">
                    @for($hour = 0; $hour < 24; $hour++)
                        @php
                            $hourKey = sprintf('%02d:00', $hour);
                            $hourData = $hourlyBreakdown->get($hourKey, ['total' => 0, 'completed' => 0, 'failed' => 0]);
                            $maxTasks = $hourlyBreakdown->max('total') ?: 1;
                            $height = ($hourData['total'] / $maxTasks) * 60;
                        @endphp
                        <div class="flex flex-col items-center" title="{{ $hourKey }}: {{ $hourData['total'] }} tasks">
                            <div class="w-8 bg-gray-100 dark:bg-gray-700 rounded-t relative" style="height: 60px;">
                                <div class="absolute bottom-0 w-full rounded-t transition-all
                                    {{ $hourData['failed'] > 0 ? 'bg-red-400' : 'bg-blue-400' }}"
                                    style="height: {{ $height }}px;">
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $hour }}</span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
        @endif

        <!-- Top Devices -->
        @if($tasksByDevice->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                    Top Devices by Activity
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subscriber</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Model</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tasks</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($tasksByDevice as $device)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('device.show', $device['device_id']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono text-sm">
                                        {{ $device['serial_number'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $device['subscriber'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $device['product_class'] }}</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">{{ $device['total'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-green-600 dark:text-green-400">{{ $device['completed'] }}</span>
                                    @if($device['failed'] > 0)
                                        / <span class="text-red-600 dark:text-red-400">{{ $device['failed'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Failed Tasks -->
        @if($failedTasks->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                <h2 class="text-lg font-medium text-red-800 dark:text-red-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Failed Tasks ({{ $failedTasks->count() }})
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subscriber</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($failedTasks as $task)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">#{{ $task['id'] }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $task['type'] }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('device.show', $task['device_id']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono text-sm">
                                        {{ $task['device_serial'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $task['subscriber'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $task['user'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $task['created_at']->format('g:i A') }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 max-w-xs truncate" title="{{ $task['error'] }}">
                                    {{ Str::limit($task['error'] ?? 'Unknown error', 50) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Slowest Tasks -->
        @if($slowestTasks->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-yellow-50 dark:bg-yellow-900/20">
                <h2 class="text-lg font-medium text-yellow-800 dark:text-yellow-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Slowest Tasks
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subscriber</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($slowestTasks as $task)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">#{{ $task['id'] }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $task['type'] }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-blue-600 dark:text-blue-400">{{ $task['device_serial'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $task['subscriber'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $task['user'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-yellow-600 dark:text-yellow-400">
                                    @if($task['duration_seconds'] < 60)
                                        {{ $task['duration_seconds'] }}s
                                    @elseif($task['duration_seconds'] < 3600)
                                        {{ round($task['duration_seconds'] / 60, 1) }}m
                                    @else
                                        {{ round($task['duration_seconds'] / 3600, 1) }}h
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Device Events Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Device Events
                </h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $deviceEvents['total'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Events</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $deviceEvents['unique_devices'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Devices Reporting</div>
                    </div>
                </div>

                @if($deviceEvents['by_type']->isNotEmpty())
                <div class="mt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Events by Type</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($deviceEvents['by_type'] as $type => $count)
                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-medium rounded">
                                {{ $type }}: {{ $count }}
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- All Tasks (Collapsible) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-6" x-data="{ expanded: false }">
            <button @click="expanded = !expanded" class="w-full px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between text-left">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    All Tasks ({{ $tasks->count() }})
                </h2>
                <svg :class="expanded ? 'rotate-180' : ''" class="w-5 h-5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="expanded" x-collapse class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subscriber</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($tasks as $task)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm font-mono text-gray-500 dark:text-gray-400">#{{ $task->id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $task->created_at->format('g:i:s A') }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $task->task_type }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('device.show', $task->device_id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono text-sm">
                                        {{ $task->device?->serial_number ?? 'Unknown' }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $task->device?->subscriber?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $task->getInitiatorDisplayName() }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($task->status === 'completed')
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Completed</span>
                                    @elseif($task->status === 'failed')
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Failed</span>
                                    @elseif($task->status === 'pending')
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Pending</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">{{ $task->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
