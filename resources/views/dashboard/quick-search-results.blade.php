@extends('layouts.app')

@section('title', 'Search Results - ' . $query)

@section('content')
<div class="space-y-6">
    <!-- Back to Dashboard with Search -->
    <div class="bg-blue-600 dark:bg-blue-700 rounded-lg shadow-lg p-4 sm:p-6">
        <form action="{{ route('quick-search') }}" method="GET" class="space-y-3">
            <div class="flex items-center space-x-2 text-white mb-2">
                <a href="{{ route('dashboard') }}" class="text-white hover:text-blue-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <span class="text-lg font-semibold">Quick Device Search</span>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ $query }}"
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
        </form>
    </div>

    <!-- Results Header -->
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            @if($devices->isEmpty())
                No devices found for "{{ $query }}"
            @else
                {{ $devices->count() }} device(s) found for "{{ $query }}"
            @endif
        </h2>
    </div>

    @if($devices->isEmpty())
        <!-- No Results -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No devices found</h3>
            <p class="mt-2 text-gray-500 dark:text-gray-400">
                Try a different search term or check the serial number.
            </p>
            <div class="mt-6">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    @else
        <!-- Results List (Mobile-friendly cards) -->
        <div class="space-y-3">
            @foreach($devices as $device)
                <a href="{{ route('device.show', ['id' => $device->id, 'tab' => 'wifi']) }}"
                   class="block bg-white dark:bg-slate-800 rounded-lg shadow hover:shadow-md transition-shadow p-4 touch-manipulation">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <!-- Serial Number (prominent) -->
                            <p class="text-lg font-semibold text-blue-600 dark:text-blue-400 truncate">
                                {{ $device->serial_number }}
                            </p>

                            <!-- Device Model -->
                            <p class="text-sm text-gray-900 dark:text-gray-100 mt-1">
                                {{ $device->manufacturer }} {{ $device->display_name }}
                            </p>

                            <!-- Subscriber -->
                            @if($device->subscriber)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $device->subscriber->name }}
                                </p>
                            @endif

                            <!-- Last seen -->
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                                Last seen: {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                            </p>
                        </div>

                        <!-- Status badge and arrow -->
                        <div class="flex items-center space-x-3 ml-4">
                            @if($device->online)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    Online
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                    Offline
                                </span>
                            @endif
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <!-- Help text -->
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            Tap a device to go to its WiFi configuration
        </p>
    @endif
</div>
@endsection
