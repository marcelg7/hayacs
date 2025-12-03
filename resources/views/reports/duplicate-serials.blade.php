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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Duplicate Serial Numbers</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ count($duplicates) }} duplicate serial numbers found</p>
            </div>
        </div>

        <!-- Warning Box -->
        @if(count($duplicates) > 0)
        <div class="bg-pink-50 dark:bg-pink-900/30 border border-pink-200 dark:border-pink-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-pink-600 dark:text-pink-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-pink-800 dark:text-pink-200">Data Integrity Issue</h3>
                    <p class="text-sm text-pink-700 dark:text-pink-300 mt-1">
                        Duplicate serial numbers indicate a data issue. This could be caused by:
                        devices being factory reset and re-registering, database import errors,
                        or devices sending incorrect serial numbers due to firmware bugs.
                        Consider merging these records or investigating the root cause.
                    </p>
                </div>
            </div>
        </div>
        @endif

        @forelse($duplicates as $dup)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-4 py-3 bg-pink-50 dark:bg-pink-900/30 border-b border-pink-200 dark:border-pink-800">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900 dark:text-white font-mono">{{ $dup->serial_number }}</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200">
                        {{ $dup->count }} duplicates
                    </span>
                </div>
            </div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscriber</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Inform</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($duplicateDevices[$dup->serial_number] as $device)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                #{{ $device->id }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900 dark:text-white">{{ $device->manufacturer }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $device->product_class }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($device->subscriber)
                                <a href="{{ route('subscribers.show', $device->subscriber_id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ $device->subscriber->name }}
                                </a>
                            @else
                                <span class="text-gray-400 italic">None</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($device->online)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Online
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Offline
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            @if($device->last_inform)
                                {{ $device->last_inform->format('M j, Y g:i A') }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            {{ $device->created_at->format('M j, Y g:i A') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @empty
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
            <svg class="w-12 h-12 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-lg font-medium text-gray-900 dark:text-white">No duplicate serials found!</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">All device serial numbers are unique.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
