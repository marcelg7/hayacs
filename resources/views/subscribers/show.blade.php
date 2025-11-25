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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Subscriber Information</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-32">Customer ID:</dt>
                                <dd>{{ $subscriber->customer }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-32">Account:</dt>
                                <dd>{{ $subscriber->account }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-32">Agreement:</dt>
                                <dd>{{ $subscriber->agreement ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Service Information</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-40">Service Type:</dt>
                                <dd>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $subscriber->service_type ?? 'N/A' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 dark:text-gray-400 w-40">Connection Date:</dt>
                                <dd>{{ $subscriber->connection_date ? $subscriber->connection_date->format('M d, Y') : 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipment -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold mb-4">Equipment ({{ $subscriber->equipment->count() }})</h3>

                @if($subscriber->equipment->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No equipment records found.</p>
                @else
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
                                @foreach($subscriber->equipment as $equipment)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm">{{ $equipment->equip_item }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $equipment->equip_desc }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $equipment->manufacturer ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $equipment->model ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm font-mono">
                                            @if($equipment->serial)
                                                <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $equipment->serial }}</span>
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $equipment->start_date ? $equipment->start_date->format('M d, Y') : 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Connected Devices -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold mb-4">Connected TR-069 Devices ({{ $subscriber->devices->count() }})</h3>

                @if($subscriber->devices->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No devices connected to this subscriber yet.</p>
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
                                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $device->serial_number }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $device->manufacturer }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $device->product_class }}</td>
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
                                            <a href="{{ route('devices.show', $device) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
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
