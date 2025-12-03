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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">SmartRG on Non-DSL Connections</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $devices->count() }} SmartRG devices found on Fibre/Cable/Wireless connections</p>
            </div>
        </div>

        <!-- Warning Box -->
        @if($devices->count() > 0)
        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Equipment Mismatch Detected</h3>
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                        SmartRG devices are DSL routers and are not optimal for Fibre, Cable, G.hn, or Fixed Wireless connections.
                        Consider replacing these with more appropriate equipment like Calix GigaSpire or Nokia Beacon for better performance.
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Summary Cards -->
        @if($devices->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- By Service Type -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">By Service Type</h3>
                <div class="space-y-2">
                    @foreach($byServiceType->sortDesc() as $serviceType => $count)
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $serviceType }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                            {{ number_format($count) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- By Device Model -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">By Device Model</h3>
                <div class="space-y-2">
                    @foreach($byModel->sortDesc() as $model => $count)
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $model ?: 'Unknown' }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ number_format($count) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Devices Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscriber</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Firmware</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Contact</th>
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
                                {{ $device->manufacturer }} {{ $device->product_class }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($device->subscriber)
                                <a href="{{ route('subscribers.show', $device->subscriber_id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ $device->subscriber->name }}
                                </a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $device->subscriber->customer_name }}
                                </div>
                            @else
                                <span class="text-gray-400 italic">No subscriber</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if(str_contains($device->subscriber->service_type ?? '', 'Fibre'))
                                    bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                @elseif(str_contains($device->subscriber->service_type ?? '', 'Cable'))
                                    bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                @elseif(str_contains($device->subscriber->service_type ?? '', 'Wireless'))
                                    bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @else
                                    bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                @endif
                            ">
                                {{ $device->subscriber->service_type ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">
                            {{ $device->software_version ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            @if($device->last_contact)
                                {{ $device->last_contact->diffForHumans() }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-lg font-medium">No equipment mismatches found!</p>
                            <p class="text-sm">All SmartRG devices are properly assigned to DSL connections.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">About This Report</h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        This report identifies SmartRG and Sagemcom devices assigned to subscribers with non-DSL service types.
                        SmartRG devices are designed for DSL connections and may not perform optimally on:
                    </p>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 mt-2 list-disc list-inside">
                        @foreach($nonDslServiceTypes as $type)
                        <li>{{ $type }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
