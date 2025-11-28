@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-4">
                        <h2 class="text-2xl font-semibold">Subscribers</h2>
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('subscribers.import') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm">
                                Import Data
                            </a>
                        @endif
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ number_format($subscribers->total()) }} subscribers
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" action="{{ route('subscribers.index') }}" class="mb-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <div class="flex flex-wrap gap-4 items-end">
                        <!-- Search -->
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Name, customer, or account..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                        </div>

                        <!-- Service Type Filter -->
                        <div class="min-w-[150px]">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Service Type</label>
                            <select
                                name="service_type"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">All Types</option>
                                @foreach($serviceTypes as $type)
                                    <option value="{{ $type }}" {{ request('service_type') === $type ? 'selected' : '' }}>
                                        {{ $type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Has Devices Filter -->
                        <div class="min-w-[150px]">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Has Devices</label>
                            <select
                                name="has_devices"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">All</option>
                                <option value="yes" {{ request('has_devices') === 'yes' ? 'selected' : '' }}>With Devices</option>
                                <option value="no" {{ request('has_devices') === 'no' ? 'selected' : '' }}>Without Devices</option>
                            </select>
                        </div>

                        <!-- Preserve sort -->
                        <input type="hidden" name="sort" value="{{ request('sort', 'name') }}">
                        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">

                        <!-- Buttons -->
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm">
                                Filter
                            </button>
                            @if(request()->hasAny(['search', 'service_type', 'has_devices']))
                                <a href="{{ route('subscribers.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md text-sm">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                @if($subscribers->isEmpty())
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <p class="text-lg">No subscribers found.</p>
                        @if(request()->hasAny(['search', 'service_type', 'has_devices']))
                            <p class="mt-2">Try adjusting your filters.</p>
                        @else
                            <p class="mt-2">Run <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">php artisan subscriber:import</code> to import data.</p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    @php
                                        $currentParams = request()->except(['sort', 'direction']);
                                    @endphp
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <a href="{{ route('subscribers.index', array_merge($currentParams, ['sort' => 'customer', 'direction' => ($sortField === 'customer' && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}"
                                           class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                            Customer
                                            @if($sortField === 'customer')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($sortDirection === 'asc')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    @endif
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <a href="{{ route('subscribers.index', array_merge($currentParams, ['sort' => 'name', 'direction' => ($sortField === 'name' && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}"
                                           class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                            Name
                                            @if($sortField === 'name')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($sortDirection === 'asc')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    @endif
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <a href="{{ route('subscribers.index', array_merge($currentParams, ['sort' => 'service_type', 'direction' => ($sortField === 'service_type' && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}"
                                           class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                            Service Type
                                            @if($sortField === 'service_type')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($sortDirection === 'asc')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    @endif
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Equipment
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <a href="{{ route('subscribers.index', array_merge($currentParams, ['sort' => 'devices_count', 'direction' => ($sortField === 'devices_count' && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}"
                                           class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                            Devices
                                            @if($sortField === 'devices_count')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($sortDirection === 'asc')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    @endif
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($subscribers as $subscriber)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {{ $subscriber->customer }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="{{ route('subscribers.show', $subscriber) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $subscriber->name }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($subscriber->service_type)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                    {{ $subscriber->service_type }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ $subscriber->equipment->count() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($subscriber->devices_count > 0)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    {{ $subscriber->devices_count }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="{{ route('subscribers.show', $subscriber) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $subscribers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
