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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">STUN Enabled Devices</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $devices->total() }} devices with STUN enabled for NAT traversal</p>
            </div>
        </div>

        <!-- Info Box -->
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-green-800 dark:text-green-200">STUN NAT Traversal</h3>
                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                        These devices use STUN (Session Traversal Utilities for NAT) to enable connection requests
                        even when behind NAT. The ACS can send UDP connection requests to these devices
                        using the STUN binding information.
                    </p>
                </div>
            </div>
        </div>

        <!-- Devices Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscriber</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Inform</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($devices as $device)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                {{ $device->serial_number }}
                            </a>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $device->manufacturer }} {{ $device->display_name }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($device->subscriber)
                                <a href="{{ route('subscribers.show', $device->subscriber_id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ $device->subscriber->name }}
                                </a>
                            @else
                                <span class="text-gray-400 italic">No subscriber</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">
                            {{ $device->ip_address ?? '-' }}
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
                                {{ $device->last_inform->diffForHumans() }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-lg font-medium">No STUN devices found</p>
                            <p class="text-sm">No devices have STUN enabled for NAT traversal.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $devices->links() }}
        </div>
    </div>
</div>
@endsection
