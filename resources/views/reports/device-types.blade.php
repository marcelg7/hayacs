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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Device Type Report</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Fleet composition by manufacturer and model</p>
            </div>
        </div>

        <!-- Manufacturer Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white">By Manufacturer</h3>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    @php $totalDevices = $byManufacturer->sum('count'); @endphp
                    @foreach($byManufacturer as $mfg)
                    @php
                        $percentage = $totalDevices > 0 ? ($mfg->count / $totalDevices) * 100 : 0;
                        $colors = [
                            'Calix' => 'bg-blue-600',
                            'Nokia' => 'bg-purple-600',
                            'Alcatel-Lucent' => 'bg-purple-600',
                            'SmartRG' => 'bg-green-600',
                            'Sagemcom' => 'bg-orange-600',
                            'CIG Shanghai' => 'bg-red-600',
                        ];
                        $color = $colors[$mfg->manufacturer] ?? 'bg-gray-600';
                    @endphp
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $mfg->manufacturer ?: 'Unknown' }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($mfg->count) }} devices ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="{{ $color }} h-3 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Device Types Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white">All Device Types</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Manufacturer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product Class</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Model Name</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Count</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">% of Fleet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($types as $type)
                    @php
                        $percentage = $totalDevices > 0 ? ($type->count / $totalDevices) * 100 : 0;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $type->manufacturer ?: 'Unknown' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $type->product_class ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $type->model_name ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white text-right font-semibold">{{ number_format($type->count) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400 w-12 text-right">{{ number_format($percentage, 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            No device types found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white text-right">{{ number_format($totalDevices) }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white text-right">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
