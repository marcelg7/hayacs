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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Inactive Devices Report</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $devices->total() }} devices inactive for {{ $days }}+ days</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Inactive for at least</label>
                    <select name="days" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 days</option>
                        <option value="60" {{ $days == 60 ? 'selected' : '' }}>60 days</option>
                        <option value="90" {{ $days == 90 ? 'selected' : '' }}>90 days</option>
                        <option value="180" {{ $days == 180 ? 'selected' : '' }}>180 days</option>
                        <option value="365" {{ $days == 365 ? 'selected' : '' }}>1 year</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                    Filter
                </button>
            </form>
        </div>

        <!-- Info Box -->
        <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Equipment Recovery Candidates</h3>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                        These devices have been inactive for an extended period. Consider:
                        cross-referencing with billing to verify service status,
                        sending equipment recovery notices,
                        or marking as lost/stolen if applicable.
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Seen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Days Inactive</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
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
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            @if($device->last_inform)
                                {{ $device->last_inform->format('M j, Y') }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($device->days_inactive)
                                @if($device->days_inactive >= 180)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        {{ $device->days_inactive }} days
                                    </span>
                                @elseif($device->days_inactive >= 90)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                        {{ $device->days_inactive }} days
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        {{ $device->days_inactive }} days
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($device->days_inactive >= 365)
                                <span class="text-red-600 dark:text-red-400 text-sm font-medium">Presumed Lost</span>
                            @elseif($device->days_inactive >= 90)
                                <span class="text-orange-600 dark:text-orange-400 text-sm font-medium">Recovery Candidate</span>
                            @else
                                <span class="text-yellow-600 dark:text-yellow-400 text-sm font-medium">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-lg font-medium">No inactive devices found!</p>
                            <p class="text-sm">All devices have been active within the selected timeframe.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $devices->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
