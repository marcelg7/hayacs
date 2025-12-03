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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Firmware Version Report</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $grouped->count() }} device types with {{ $firmware->count() }} firmware versions</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Firmware Versions</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $firmware->count() }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Device Types</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $grouped->count() }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Devices</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($firmware->sum('count')) }}</div>
            </div>
        </div>

        <!-- Firmware by Device Type -->
        @foreach($grouped as $deviceType => $versions)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $deviceType }}</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ $versions->sum('count') }} devices
                    </span>
                </div>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    @foreach($versions as $version)
                    @php
                        $percentage = $versions->sum('count') > 0 ? ($version->count / $versions->sum('count')) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $version->software_version }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($version->count) }} devices ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        @if($grouped->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium text-gray-900 dark:text-white">No firmware data available</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">No devices have reported their firmware version yet.</p>
        </div>
        @endif
    </div>
</div>
@endsection
