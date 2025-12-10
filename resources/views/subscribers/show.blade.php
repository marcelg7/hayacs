@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Subscriber Info -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-2xl font-semibold">{{ $subscriber->name }}</h2>
                    <a href="{{ route('subscribers.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md">
                        Back to List
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Customer</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-28">Customer ID:</dt>
                                <dd class="font-mono">{{ $subscriber->customer }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-28">Name:</dt>
                                <dd>{{ $subscriber->name }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Account</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-28">Account #:</dt>
                                <dd class="font-mono">{{ $subscriber->account }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-28">Agreement #:</dt>
                                <dd class="font-mono">{{ $subscriber->agreement ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Service</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-32">Service Type:</dt>
                                <dd>
                                    @if($subscriber->service_type)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                            {{ $subscriber->service_type }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-32">Connected:</dt>
                                <dd>{{ $subscriber->connection_date ? $subscriber->connection_date->format('M d, Y') : 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Other Accounts for this Customer -->
        @if($relatedAccounts->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-xl font-semibold mb-4">Other Accounts for Customer {{ $subscriber->customer }} ({{ $relatedAccounts->count() }})</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Agreement</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Service Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Equipment</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Devices</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($relatedAccounts as $related)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm font-mono">{{ $related->account }}</td>
                                        <td class="px-4 py-3 text-sm font-mono">{{ $related->agreement ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($related->service_type)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                    {{ $related->service_type }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $related->equipment_count }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($related->devices_count > 0)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    {{ $related->devices_count }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('subscribers.show', $related) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- Equipment grouped by Agreement -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold mb-4">Equipment ({{ $subscriber->equipment->count() }})</h3>

                @if($subscriber->equipment->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No equipment records found for this account.</p>
                @else
                    @php
                        $equipmentByAgreement = $subscriber->equipment->groupBy('agreement');
                    @endphp

                    @foreach($equipmentByAgreement as $agreement => $equipmentGroup)
                        <div class="mb-6 last:mb-0">
                            @if($equipmentByAgreement->count() > 1 || $agreement !== $subscriber->agreement)
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                    Agreement: <span class="font-mono">{{ $agreement ?: 'No Agreement' }}</span>
                                    <span class="text-gray-400 ml-2">({{ $equipmentGroup->count() }} items)</span>
                                </h4>
                            @endif

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Manufacturer</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Serial</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Start Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($equipmentGroup as $equipment)
                                            @php
                                                // Try to find matching device by serial number
                                                $matchingDevice = null;
                                                if ($equipment->serial) {
                                                    $matchingDevice = $subscriber->devices->first(function($d) use ($equipment) {
                                                        return stripos($d->serial_number, preg_replace('/[^a-fA-F0-9]/', '', $equipment->serial)) !== false;
                                                    });
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 text-sm">{{ $equipment->equip_item ?: '-' }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $equipment->equip_desc ?: ($matchingDevice ? $matchingDevice->product_class : '-') }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $equipment->manufacturer ?: ($matchingDevice ? $matchingDevice->manufacturer : '-') }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $equipment->model ?: ($matchingDevice ? ($matchingDevice->model_name ?: $matchingDevice->product_class) : '-') }}</td>
                                                <td class="px-4 py-3 text-sm font-mono">
                                                    @if($equipment->serial)
                                                        @php
                                                            $isValidMac = strlen(preg_replace('/[^a-fA-F0-9]/', '', $equipment->serial)) === 12;
                                                        @endphp
                                                        @if($matchingDevice)
                                                            {{-- TR-069 device link --}}
                                                            <a href="{{ route('device.show', $matchingDevice->id) }}"
                                                               class="inline-flex items-center bg-green-100 dark:bg-green-900 px-2 py-1 rounded text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                                                                {{ $equipment->serial }}
                                                                <svg class="w-3 h-3 ml-1.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                                </svg>
                                                            </a>
                                                        @elseif($subscriber->isCableInternet() && $isValidMac)
                                                            {{-- Cable modem link (no TR-069 match) --}}
                                                            @php
                                                                $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $equipment->serial));
                                                            @endphp
                                                            <a href="{{ $subscriber->getCablePortalUrl($equipment->serial) }}"
                                                               target="_blank"
                                                               class="inline-flex items-center bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors">
                                                                {{ $equipment->serial }}
                                                                <svg class="w-3 h-3 ml-1.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                                </svg>
                                                            </a>
                                                            <a href="https://stats.ez.clearcable.net/"
                                                               target="_blank"
                                                               class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                                                Cartograph
                                                                <svg class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                                </svg>
                                                            </a>
                                                        @else
                                                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $equipment->serial }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm">{{ $equipment->start_date ? \Carbon\Carbon::parse($equipment->start_date)->format('M d, Y') : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <!-- Connected Devices -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold mb-4">Connected TR-069 Devices ({{ $subscriber->devices->count() }})</h3>

                @if($subscriber->devices->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No TR-069 devices connected to this subscriber yet.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Devices are linked automatically by matching serial numbers from equipment records.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Serial Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Manufacturer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Inform</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($subscriber->devices as $device)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm font-mono">
                                            <a href="{{ route('device.show', $device->id) }}" class="inline-flex items-center bg-green-100 dark:bg-green-900 px-2 py-1 rounded text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                                                {{ $device->serial_number }}
                                                <svg class="w-3 h-3 ml-1.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $device->manufacturer }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $device->display_name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($device->online)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    Online
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                                    Offline
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('device.show', $device->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                View Device
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
