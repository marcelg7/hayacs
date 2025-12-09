@extends('layouts.app')

@section('content')
<div class="py-6" x-data="offlineDevicesReport()">
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
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Offline Devices Report</h1>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $devices->total() }} devices currently offline</p>
            </div>

            <div class="flex gap-2">
                <!-- Bulk Wake Button -->
                <button
                    @click="bulkWakeSelected()"
                    :disabled="selectedDevices.length === 0 || bulkWaking"
                    class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-md transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" :class="{'animate-spin': bulkWaking}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span x-text="bulkWaking ? 'Waking...' : `Wake Selected (${selectedDevices.length})`"></span>
                </button>

                <!-- Wake All on Page -->
                <button
                    @click="wakeAllOnPage()"
                    :disabled="bulkWaking"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-md transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Wake All on Page
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Offline for at least</label>
                    <select name="hours" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All offline</option>
                        <option value="1" {{ request('hours') == '1' ? 'selected' : '' }}>1 hour</option>
                        <option value="4" {{ request('hours') == '4' ? 'selected' : '' }}>4 hours</option>
                        <option value="12" {{ request('hours') == '12' ? 'selected' : '' }}>12 hours</option>
                        <option value="24" {{ request('hours') == '24' ? 'selected' : '' }}>24 hours</option>
                        <option value="48" {{ request('hours') == '48' ? 'selected' : '' }}>48 hours</option>
                        <option value="168" {{ request('hours') == '168' ? 'selected' : '' }}>1 week</option>
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

        <!-- Devices Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input type="checkbox" @change="toggleSelectAll($event)" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscriber</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Seen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time Offline</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($devices as $device)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" :class="wakeStatus['{{ $device->id }}']?.status">
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                value="{{ $device->id }}"
                                x-model="selectedDevices"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                            >
                        </td>
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
                                {{ $device->last_inform->format('M j, Y g:i A') }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($device->hours_offline)
                                @if($device->hours_offline >= 168)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        {{ floor($device->hours_offline / 24) }} days
                                    </span>
                                @elseif($device->hours_offline >= 24)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        {{ floor($device->hours_offline / 24) }} days
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                        {{ $device->hours_offline }} hours
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">
                            {{ $device->ip_address ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button
                                @click="wakeDevice({{ $device->id }}, '{{ $device->serial_number }}')"
                                :disabled="wakeStatus['{{ $device->id }}']?.waking"
                                class="inline-flex items-center px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 disabled:bg-gray-400 text-white text-sm font-medium rounded transition-colors"
                                :class="{'bg-green-500': wakeStatus['{{ $device->id }}']?.status === 'success', 'bg-red-500': wakeStatus['{{ $device->id }}']?.status === 'failed'}"
                            >
                                <svg class="w-4 h-4 mr-1" :class="{'animate-spin': wakeStatus['{{ $device->id }}']?.waking}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <span x-text="wakeStatus['{{ $device->id }}']?.message || 'Wake'"></span>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-lg font-medium">All devices are online!</p>
                            <p class="text-sm">No offline devices found matching your criteria.</p>
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

<script>
function offlineDevicesReport() {
    return {
        selectedDevices: [],
        wakeStatus: {},
        bulkWaking: false,
        notification: '',
        notificationType: 'success',

        toggleSelectAll(event) {
            if (event.target.checked) {
                this.selectedDevices = @json($devices->pluck('id')->map(fn($id) => (string)$id));
            } else {
                this.selectedDevices = [];
            }
        },

        async wakeDevice(deviceId, serial) {
            this.wakeStatus[deviceId] = { waking: true, message: 'Waking...' };

            try {
                const response = await fetch(`/reports/wake-device/${deviceId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                this.wakeStatus[deviceId] = {
                    waking: false,
                    status: data.success ? 'success' : 'failed',
                    message: data.success ? 'Sent!' : 'Failed'
                };

                // Reset status after 5 seconds
                setTimeout(() => {
                    this.wakeStatus[deviceId] = null;
                }, 5000);

            } catch (error) {
                this.wakeStatus[deviceId] = {
                    waking: false,
                    status: 'failed',
                    message: 'Error'
                };
            }
        },

        async bulkWakeSelected() {
            if (this.selectedDevices.length === 0) return;

            this.bulkWaking = true;

            try {
                const response = await fetch('/reports/bulk-wake', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ device_ids: this.selectedDevices })
                });

                const data = await response.json();

                this.notification = data.message;
                this.notificationType = data.results.failed === 0 ? 'success' : 'warning';

                // Clear notification after 10 seconds
                setTimeout(() => {
                    this.notification = '';
                }, 10000);

            } catch (error) {
                this.notification = 'Error sending wake requests';
                this.notificationType = 'error';
            }

            this.bulkWaking = false;
            this.selectedDevices = [];
        },

        async wakeAllOnPage() {
            this.selectedDevices = @json($devices->pluck('id')->map(fn($id) => (string)$id));
            await this.bulkWakeSelected();
        }
    }
}
</script>
@endsection
