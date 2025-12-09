@extends('layouts.app')

@section('content')
<div class="py-6" x-data="excessiveInformsReport()">
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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Excessive Device Informs</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $devices->count() }} devices with excessive inform frequency</p>
            </div>

            <!-- Bulk Set Inform Interval -->
            @if($devices->count() > 0)
            <div class="flex items-center gap-2">
                <select x-model="selectedInterval" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">Select interval...</option>
                    <option value="300">5 minutes (300s)</option>
                    <option value="600">10 minutes (600s)</option>
                    <option value="900">15 minutes (900s)</option>
                    <option value="1800">30 minutes (1800s)</option>
                    <option value="3600">1 hour (3600s)</option>
                    <option value="7200">2 hours (7200s)</option>
                    <option value="14400">4 hours (14400s)</option>
                    <option value="43200">12 hours (43200s)</option>
                    <option value="86400">24 hours (86400s)</option>
                </select>
                <button
                    @click="setAllInformIntervals()"
                    :disabled="!selectedInterval || settingIntervals"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-md transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" :class="{'animate-spin': settingIntervals}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span x-text="settingIntervals ? 'Queuing...' : 'Set All Intervals'"></span>
                </button>
            </div>
            @endif
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Period</label>
                    <select name="hours" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1" {{ $hours == 1 ? 'selected' : '' }}>Last 1 hour</option>
                        <option value="4" {{ $hours == 4 ? 'selected' : '' }}>Last 4 hours</option>
                        <option value="12" {{ $hours == 12 ? 'selected' : '' }}>Last 12 hours</option>
                        <option value="24" {{ $hours == 24 ? 'selected' : '' }}>Last 24 hours</option>
                        <option value="48" {{ $hours == 48 ? 'selected' : '' }}>Last 48 hours</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Threshold (sessions)</label>
                    <select name="threshold" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="20" {{ $threshold == 20 ? 'selected' : '' }}>20+ sessions</option>
                        <option value="50" {{ $threshold == 50 ? 'selected' : '' }}>50+ sessions</option>
                        <option value="100" {{ $threshold == 100 ? 'selected' : '' }}>100+ sessions</option>
                        <option value="200" {{ $threshold == 200 ? 'selected' : '' }}>200+ sessions</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                    Filter
                </button>
            </form>
        </div>

        <!-- Results Notification -->
        <div x-show="notification" x-transition class="mb-4 p-4 rounded-lg" :class="notificationType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'">
            <span x-text="notification"></span>
        </div>

        <!-- Warning Box -->
        @if($devices->count() > 0)
        <div class="bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200">Inform Storm Detected</h3>
                    <p class="text-sm text-orange-700 dark:text-orange-300 mt-1">
                        These devices are checking in more frequently than expected. Common causes:
                        incorrect PeriodicInformInterval setting, connection request loops,
                        VALUE CHANGE events firing repeatedly, or firmware bugs.
                        Use the <strong>Set All Intervals</strong> button above to queue tasks that will fix the inform interval on all affected devices.
                    </p>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions ({{ $hours }}h)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Per Hour</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Firmware</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Severity</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Task</th>
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
                        <td class="px-4 py-3">
                            <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($device->session_count) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium {{ $device->informs_per_hour > 10 ? 'text-red-600 dark:text-red-400' : 'text-orange-600 dark:text-orange-400' }}">
                                {{ $device->informs_per_hour }}/hr
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">
                            {{ $device->software_version ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($device->informs_per_hour > 20)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Critical
                                </span>
                            @elseif($device->informs_per_hour > 10)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    High
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    Moderate
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span x-show="taskStatus['{{ $device->id }}']" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Queued
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-lg font-medium">No excessive informs detected!</p>
                            <p class="text-sm">All devices are checking in at expected frequencies.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function excessiveInformsReport() {
    return {
        selectedInterval: '',
        settingIntervals: false,
        taskStatus: {},
        notification: '',
        notificationType: 'success',

        async setAllInformIntervals() {
            if (!this.selectedInterval) return;

            this.settingIntervals = true;
            const deviceIds = @json($devices->pluck('id'));

            try {
                const response = await fetch('{{ route("reports.bulk-set-inform-interval") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        device_ids: deviceIds,
                        interval: parseInt(this.selectedInterval)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Mark all devices as having tasks queued
                    deviceIds.forEach(id => {
                        this.taskStatus[id] = true;
                    });

                    this.notification = data.message;
                    this.notificationType = 'success';
                } else {
                    this.notification = data.message || 'Failed to queue tasks';
                    this.notificationType = 'error';
                }

                // Clear notification after 10 seconds
                setTimeout(() => {
                    this.notification = '';
                }, 10000);

            } catch (error) {
                this.notification = 'Error: ' + error.message;
                this.notificationType = 'error';
            }

            this.settingIntervals = false;
        }
    }
}
</script>
@endsection
