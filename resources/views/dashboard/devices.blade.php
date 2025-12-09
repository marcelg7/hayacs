@extends('layouts.app')

@section('title', 'Devices - TR-069 ACS')

@section('content')
<div class="space-y-6" x-data="{ showFilters: {{ request()->hasAny(['manufacturer', 'model', 'product_class', 'status']) ? 'true' : 'false' }} }">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-gray-100 sm:text-3xl sm:truncate">
                All Devices
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $devices->total() }} devices found
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-2">
            <button @click="showFilters = !showFilters" type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-100 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filters
                @if(request()->hasAny(['manufacturer', 'model', 'product_class', 'status']))
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Active</span>
                @endif
            </button>
            <a href="{{ route('devices.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Filters Panel -->
    <div x-show="showFilters" x-collapse class="bg-white dark:bg-slate-800 shadow rounded-lg p-4">
        <form method="GET" action="{{ route('devices.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-100">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Device ID, Serial, IP..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>

                <!-- Manufacturer -->
                <div>
                    <label for="manufacturer" class="block text-sm font-medium text-gray-700 dark:text-gray-100">Manufacturer</label>
                    <select name="manufacturer" id="manufacturer" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Manufacturers</option>
                        @foreach($manufacturers as $mfr)
                            <option value="{{ $mfr }}" {{ request('manufacturer') === $mfr ? 'selected' : '' }}>{{ $mfr }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Model (Product Class) -->
                <div>
                    <label for="product_class" class="block text-sm font-medium text-gray-700 dark:text-gray-100">Model</label>
                    <select name="product_class" id="product_class" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Models</option>
                        @foreach($productClassDisplayMap as $pc => $displayName)
                            <option value="{{ $pc }}" {{ request('product_class') === $pc ? 'selected' : '' }}>{{ $displayName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-100">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Status</option>
                        <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                        <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    </select>
                </div>
            </div>

            <!-- Hidden sort fields -->
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if(request('dir'))
                <input type="hidden" name="dir" value="{{ request('dir') }}">
            @endif

            <div class="flex justify-end space-x-2">
                <a href="{{ route('devices.index') }}" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-100 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700">
                    Clear
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Devices Table -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        @php
                            $currentSort = request('sort', 'last_inform');
                            $currentDir = request('dir', 'desc');
                            $sortUrl = function($column) use ($currentSort, $currentDir) {
                                $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
                                return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $newDir]);
                            };
                            $sortIcon = function($column) use ($currentSort, $currentDir) {
                                if ($currentSort !== $column) return '';
                                return $currentDir === 'asc' ? '↑' : '↓';
                            };
                        @endphp
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('id') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Device ID {{ $sortIcon('id') }}</a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subscriber</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('manufacturer') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Manufacturer {{ $sortIcon('manufacturer') }}</a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('model_name') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Model {{ $sortIcon('model_name') }}</a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('serial_number') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Serial Number {{ $sortIcon('serial_number') }}</a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('software_version') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Software {{ $sortIcon('software_version') }}</a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <a href="{{ $sortUrl('last_inform') }}" class="hover:text-gray-700 dark:hover:text-gray-100">Last Inform {{ $sortIcon('last_inform') }}</a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse($devices as $device)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                {{ $device->id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            @if($device->subscriber)
                                <a href="{{ route('subscribers.show', $device->subscriber->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ $device->subscriber->name }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $device->manufacturer ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $device->display_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $device->serial_number ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" title="{{ $device->software_version }}">{{ Str::limit($device->software_version, 20) ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($device->online)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Online
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Offline
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" title="{{ $device->last_inform?->format('Y-m-d H:i:s') }}">
                            {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No devices found.</p>
                            @if(request()->hasAny(['search', 'manufacturer', 'model', 'product_class', 'status']))
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters.</p>
                                <a href="{{ route('devices.index') }}" class="mt-2 inline-flex items-center text-sm text-blue-600 hover:text-blue-400">
                                    Clear all filters
                                </a>
                            @else
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Devices will appear here once they connect to the ACS endpoint at <code class="bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">/cwmp</code></p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($devices->hasPages())
    <div class="bg-white dark:bg-slate-800 px-4 py-3 rounded-lg shadow">
        {{ $devices->links() }}
    </div>
    @endif
</div>
@endsection
