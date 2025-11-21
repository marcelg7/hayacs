@extends('layouts.app')

@section('title', $device->id . ' - Device Details')

@section('content')
<!-- Task Manager Component -->
@include('components.task-manager', ['deviceId' => $device->id])

@php
    $theme = session('theme', 'standard');
    $themeConfig = config("themes.{$theme}");
    $colors = $themeConfig['colors'];
    $useColorful = $themeConfig['use_colorful_buttons'] ?? false;
@endphp
<div class="space-y-6" x-data="{
    activeTab: localStorage.getItem('deviceActiveTab_{{ $device->id }}') || 'dashboard',
    taskLoading: false,
    taskMessage: '',
    taskId: null,
    timerInterval: null,

    init() {
        // Watch for tab changes and save to localStorage
        this.$watch('activeTab', value => {
            localStorage.setItem('deviceActiveTab_{{ $device->id }}', value);
        });
    },

    startTaskTracking(message, taskId) {
        // NEW: Just trigger the task manager component to start polling
        // The task manager component handles all the UI and progress tracking
        window.dispatchEvent(new CustomEvent('task-started', {
            detail: { message, taskId }
        }));
    },

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }
}">
    <!-- Failed Tasks Alert removed - now shown as badge beside device ID -->

    <!-- Header -->
    <div>
        <div class="flex-1 min-w-0">
            @php
                // Get failed tasks in last 24 hours for indicator
                $failedTasks = $device->tasks()
                    ->where('status', 'failed')
                    ->where('updated_at', '>=', now()->subHours(24))
                    ->orderBy('updated_at', 'desc')
                    ->get();
                $failedCount = $failedTasks->count();
            @endphp

            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-{{ $colors['text'] }} sm:text-3xl inline-flex items-center gap-2">
                {{ $device->id }}

                @if($failedCount > 0)
                    <span x-data="{ showDetails: false }" class="relative inline-block">
                        <button @click="showDetails = !showDetails" @click.away="showDetails = false"
                                class="relative flex items-center justify-center w-7 h-7 rounded-full bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all"
                                title="{{$failedCount}} failed task(s) in last 24 hours">
                            <svg class="w-5 h-5 text-white font-bold" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-800 rounded-full">{{ $failedCount }}</span>
                        </button>

                        <!-- Failed Tasks Details Dropdown -->
                        <div x-show="showDetails"
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute left-0 mt-2 w-[500px] rounded-md shadow-lg bg-white dark:bg-{{ $colors['card'] }} ring-1 ring-black ring-opacity-5 z-50">
                            <div class="py-3 px-4 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Failed Tasks (Last 24 Hours)</h3>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                @foreach($failedTasks as $task)
                                    <div class="px-4 py-3 border-b border-gray-100 dark:border-{{ $colors['border'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                                    {{ ucfirst(str_replace('_', ' ', $task->task_type)) }}
                                                </p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                                    {{ $task->updated_at->format('M d, Y H:i:s') }} ({{ $task->updated_at->diffForHumans() }})
                                                </p>
                                                @if($task->result && isset($task->result['error']))
                                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                        <strong>Error:</strong> {{ $task->result['error'] }}
                                                    </p>
                                                @endif
                                                @if($task->parameters)
                                                    <details class="mt-2" @click.stop>
                                                        <summary class="text-xs text-blue-600 dark:text-blue-400 cursor-pointer hover:underline">What was being attempted</summary>
                                                        <div class="mt-1 pl-3 border-l-2 border-gray-200 dark:border-{{ $colors['border'] }}">
                                                            <p class="text-xs text-gray-700 dark:text-{{ $colors['text-muted'] }} font-mono bg-gray-50 dark:bg-{{ $colors['bg'] }} p-2 rounded">
                                                                {{ json_encode($task->parameters, JSON_PRETTY_PRINT) }}
                                                            </p>
                                                        </div>
                                                    </details>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </span>
                @endif
            </h2>
            <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    @if($device->online)
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Online</span>
                    @else
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Offline</span>
                    @endif
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    Last Inform: {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                </div>
            </div>
        </div>

        @php
            // Get external IP for Remote GUI button
            $externalIpParam = $device->parameters()
                ->where('name', 'LIKE', '%ExternalIPAddress%')
                ->where('name', 'LIKE', '%WANIPConnection%')
                ->first();
            $externalIp = $externalIpParam ? $externalIpParam->value : '';
        @endphp

        <div class="mt-4 flex flex-wrap gap-2" x-show="activeTab !== 'dashboard'" x-cloak>
            <!-- Connect Now -->
            <button @click="async () => {
                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/connection-request', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (response.ok) {
                        alert('✓ Connection request sent successfully!');
                    } else {
                        alert('Error: ' + (result.error || result.message || 'Failed to send connection request'));
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Connect Now
            </button>

            <!-- Query Device Info -->
            <div x-data="{ showModal: false, loading: false, deviceInfo: null }">
                <button @click="
                    loading = true;
                    fetch('/api/devices/{{ $device->id }}/query', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        deviceInfo = data;
                        showModal = true;
                    })
                    .catch(err => alert('Error: ' + err.message))
                    .finally(() => loading = false);
                "
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-secondary'] }}-600 hover:bg-{{ $colors['btn-secondary'] }}-700">
                    <svg x-show="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="loading ? 'Querying...' : 'Query Device Info'"></span>
                </button>

                <!-- Modal -->
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showModal = false"></div>

                        <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6">
                            <div>
                                <div class="mt-3 text-center sm:mt-5">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}" id="modal-title">Device Information</h3>
                                    <div class="mt-4 max-h-96 overflow-y-auto">
                                        <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 text-left">
                                            <template x-if="deviceInfo && deviceInfo.task">
                                                <div class="col-span-2 p-4 bg-blue-50 rounded-lg">
                                                    <dt class="text-sm font-medium text-blue-900">Task Created</dt>
                                                    <dd class="mt-1 text-sm text-blue-700">Task queued successfully. Device will process on next inform.</dd>
                                                </div>
                                            </template>
                                            <template x-if="deviceInfo && !deviceInfo.task">
                                                <template x-for="(value, key) in deviceInfo" :key="key">
                                                    <div class="border-t border-gray-200 pt-4">
                                                        <dt class="text-sm font-medium text-gray-500" x-text="key"></dt>
                                                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} break-all" x-text="typeof value === 'object' ? JSON.stringify(value, null, 2) : value"></dd>
                                                    </div>
                                                </template>
                                            </template>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-6">
                                <button type="button" @click="showModal = false" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-{{ $colors["btn-primary"] }}-600 text-base font-medium text-white hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reboot Device -->
            <form @submit.prevent="async (e) => {
                if (!confirm('Are you sure you want to reboot this device?')) return;

                taskLoading = true;
                taskMessage = 'Initiating Reboot...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/reboot', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Rebooting Device...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Error: ' + (result.error || result.message || 'Unknown error'));
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error initiating reboot: ' + error.message);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                    Reboot
                </button>
            </form>

            <!-- Factory Reset -->
            <form @submit.prevent="async (e) => {
                if (!confirm('⚠️ WARNING: This will erase ALL device settings and data!\n\nAre you absolutely sure you want to factory reset this device?')) return;

                taskLoading = true;
                taskMessage = 'Initiating Factory Reset...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/factory-reset', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Factory Resetting Device...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Error: ' + (result.error || result.message || 'Unknown error'));
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error initiating factory reset: ' + error.message);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-danger"] }}-600 hover:bg-{{ $colors["btn-danger"] }}-700">
                    Factory Reset
                </button>
            </form>

            <!-- Upgrade Firmware -->
            <form @submit.prevent="async (e) => {
                if (!confirm('Are you sure you want to upgrade the firmware on this device?')) return;

                taskLoading = true;
                taskMessage = 'Initiating Firmware Upgrade...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/firmware-upgrade', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Upgrading Firmware...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Error: ' + (result.error || result.message || 'Unknown error'));
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error initiating firmware upgrade: ' + error.message);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700">
                    Upgrade Firmware
                </button>
            </form>

            <!-- Ping Test -->
            <form @submit.prevent="async (e) => {
                taskLoading = true;
                taskMessage = 'Running Ping Test...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/ping-test', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Running Ping Test to 8.8.8.8...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Ping test started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error starting ping test: ' + error);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-info"] }}-600 hover:bg-{{ $colors["btn-info"] }}-700">
                    Ping Test
                </button>
            </form>

            <!-- Trace Route Test -->
            <form @submit.prevent="async (e) => {
                if ('{{ $device->manufacturer }}' === 'SmartRG') {
                    return; // Disabled for SmartRG
                }

                taskLoading = true;
                taskMessage = 'Running Trace Route...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/traceroute-test', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Running Trace Route to 8.8.8.8...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Trace route started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error starting trace route: ' + error);
                }
            }">
                @csrf
                <button type="submit"
                    @if($device->manufacturer === 'SmartRG')
                        disabled
                        title="Not supported for SmartRG devices"
                    @endif
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-warning"] }}-600 hover:bg-{{ $colors["btn-warning"] }}-700 {{ $device->manufacturer === 'SmartRG' ? 'opacity-50 cursor-not-allowed' : '' }}">
                    Trace Route
                    @if($device->manufacturer === 'SmartRG')
                        <svg class="w-4 h-4 ml-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 15.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    @endif
                </button>
            </form>

            <!-- TR-143 SpeedTest -->
            <form @submit.prevent="async (e) => {
                taskLoading = true;
                taskMessage = 'Starting SpeedTest...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/speedtest', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            test_type: 'both'  // Run both download and upload tests
                        })
                    });
                    const result = await response.json();
                    if (result.tasks && result.tasks.length > 0) {
                        startTaskTracking('Running TR-143 SpeedTest (Download & Upload)...', result.tasks[0].id);
                    } else {
                        taskLoading = false;
                        alert('SpeedTest started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error starting SpeedTest: ' + error);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    SpeedTest
                </button>
            </form>

            <!-- Refresh Troubleshooting -->
            <form @submit.prevent="async (e) => {
                // Show loading overlay immediately
                taskLoading = true;
                taskMessage = 'Refreshing Troubleshooting Info...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/refresh-troubleshooting', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Refreshing Troubleshooting Info...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Refresh started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error refreshing troubleshooting info: ' + error);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-info"] }}-600 hover:bg-{{ $colors["btn-info"] }}-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </form>

            <!-- Get Everything -->
            <form @submit.prevent="async (e) => {
                // Show loading overlay immediately
                taskLoading = true;
                taskMessage = 'Discovering All Parameters...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/get-all-parameters', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Discovering All Device Parameters...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Get Everything started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error getting all parameters: ' + error);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Get Everything
                </button>
            </form>

            <!-- Remote GUI -->
            @php
                // For SmartRG devices, find the MER WAN interface IP (192.168.x.x)
                $merIp = null;
                if ($device->manufacturer === 'SmartRG') {
                    $wanIpParam = $device->parameters()
                        ->where('name', 'LIKE', '%WANIPConnection%ExternalIPAddress')
                        ->where('value', 'LIKE', '192.168.%')
                        ->first();
                    $merIp = $wanIpParam ? $wanIpParam->value : null;
                }
            @endphp
            <button @click="async () => {
                const isSmartRG = '{{ $device->manufacturer }}' === 'SmartRG';
                const merIp = '{{ $merIp }}';

                if (isSmartRG && merIp) {
                    // SmartRG MER access - direct to 192.168.x.x IP
                    const url = 'http://' + merIp + '/';
                    window.open(url, '_blank');
                    return;
                }

                // Show loading overlay immediately
                taskLoading = true;
                taskMessage = 'Enabling Remote Access...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/remote-gui', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();

                    if (result.enable_task && result.enable_task.id) {
                        // Open the GUI immediately
                        const port = '8443'; // Default HTTPS port for Calix devices
                        const externalIp = result.external_ip || '{{ $externalIp }}';
                        if (externalIp) {
                            const url = 'https://' + externalIp + ':' + port + '/';
                            window.open(url, '_blank');

                            // Clear loading indicator after opening window
                            taskLoading = false;
                            taskMessage = '';
                        } else {
                            taskLoading = false;
                            alert('Could not determine external IP address');
                        }
                    } else {
                        taskLoading = false;
                        alert('Failed to enable remote access');
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error enabling remote access: ' + error);
                }
            }" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
                Remote GUI
                @if($device->manufacturer === 'SmartRG')
                    <span class="ml-1 text-xs text-gray-300">(MER)</span>
                @endif
            </button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Dashboard
            </button>
            <button @click="activeTab = 'info'"
                    :class="activeTab === 'info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Device Info
            </button>
            <button @click="activeTab = 'parameters'"
                    :class="activeTab === 'parameters' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Parameters ({{ $device->parameters()->count() }})
            </button>
            <button @click="activeTab = 'tasks'"
                    :class="activeTab === 'tasks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Tasks ({{ $device->tasks()->count() }})
            </button>
            <button @click="activeTab = 'sessions'"
                    :class="activeTab === 'sessions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Sessions
            </button>
            <button @click="activeTab = 'troubleshooting'"
                    :class="activeTab === 'troubleshooting' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Troubleshooting
            </button>
            <button @click="activeTab = 'wifi'"
                    :class="activeTab === 'wifi' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                WiFi Settings
            </button>
            <button @click="activeTab = 'backups'"
                    :class="activeTab === 'backups' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Config Backups
            </button>
            <button @click="activeTab = 'templates'"
                    :class="activeTab === 'templates' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Templates
            </button>
            <button @click="activeTab = 'ports'"
                    :class="activeTab === 'ports' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Port Forwarding
            </button>
            <button @click="{{ $device->manufacturer === 'SmartRG' ? '' : "activeTab = 'wifiscan'" }}"
                    :class="activeTab === 'wifiscan' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $device->manufacturer === 'SmartRG' ? 'opacity-50 cursor-not-allowed' : '' }}"
                    @if($device->manufacturer === 'SmartRG')
                        disabled
                        title="Not supported for SmartRG 505n, 515ac, 516ac"
                    @endif>
                <span class="flex items-center space-x-1">
                    <span>WiFi Scan</span>
                    @if($device->manufacturer === 'SmartRG')
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    @endif
                </span>
            </button>
            <button @click="activeTab = 'speedtest'"
                    :class="activeTab === 'speedtest' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Speed Test
            </button>
        </nav>
    </div>

    <!-- Dashboard Tab -->
    <div x-show="activeTab === 'dashboard'" x-cloak>
        <!-- Two-Column Layout: Device Info & Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Left Column: Device Information Summary -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Device Information</h3>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->manufacturer ?? '-' }}</dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Model</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                                @if($device->manufacturer === 'SmartRG')
                                    {{ $device->hardware_version ?? '-' }}
                                @else
                                    {{ $device->product_class ?? '-' }}
                                @endif
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">{{ $device->serial_number ?? '-' }}</dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Software Version</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">{{ $device->software_version ?? '-' }}</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Hardware Version</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                                @if($device->manufacturer === 'SmartRG')
                                    {{ $device->product_class ?? '-' }}
                                @else
                                    {{ $device->hardware_version ?? '-' }}
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Uptime</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                                @php
                                    $uptimeParam = $device->parameters()->where('name', 'LIKE', '%DeviceInfo.UpTime%')->first();
                                    if ($uptimeParam && is_numeric($uptimeParam->value)) {
                                        $seconds = (int) $uptimeParam->value;
                                        $days = floor($seconds / 86400);
                                        $hours = floor(($seconds % 86400) / 3600);
                                        $minutes = floor(($seconds % 3600) / 60);
                                        echo "{$days} Day(s), {$hours} Hour(s), {$minutes} Min(s)";
                                    } else {
                                        echo '-';
                                    }
                                @endphp
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Right Column: Quick Actions Grid -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Quick Actions</h3>
                </div>
                <div class="border-t border-gray-200 p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Query Device Info -->
                        <form @submit.prevent="async (e) => {
                            taskLoading = true;
                            taskMessage = 'Querying Device Info...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/query', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Querying Device Info...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Query started, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error querying device: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Query Device
                            </button>
                        </form>

                        <!-- Connect Now -->
                        <form @submit.prevent="async (e) => {
                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/connection-request', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    }
                                });
                                const result = await response.json();
                                if (response.ok) {
                                    alert('Connection request sent successfully!');
                                } else {
                                    alert('Error: ' + (result.error || result.message || 'Failed to send connection request'));
                                }
                            } catch (error) {
                                alert('Error: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700">
                                Connect Now
                            </button>
                        </form>

                        <!-- Reboot -->
                        <form @submit.prevent="async (e) => {
                            if (!confirm('Are you sure you want to reboot this device?')) return;

                            taskLoading = true;
                            taskMessage = 'Initiating Reboot...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/reboot', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Rebooting Device...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Error: ' + (result.error || result.message || 'Unknown error'));
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error initiating reboot: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                                Reboot
                            </button>
                        </form>

                        <!-- Ping Test -->
                        <form @submit.prevent="async (e) => {
                            taskLoading = true;
                            taskMessage = 'Running Ping Test...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/ping-test', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Running Ping Test to 8.8.8.8...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Ping test started, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error starting ping test: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-info"] }}-600 hover:bg-{{ $colors["btn-info"] }}-700">
                                Ping Test
                            </button>
                        </form>

                        <!-- Trace Route -->
                        <form @submit.prevent="async (e) => {
                            if ('{{ $device->manufacturer }}' === 'SmartRG') {
                                alert('Traceroute is not supported for SmartRG devices');
                                return;
                            }

                            taskLoading = true;
                            taskMessage = 'Running Trace Route...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/traceroute-test', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Running Trace Route to 8.8.8.8...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Trace route started, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error starting trace route: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit"
                                @if($device->manufacturer === 'SmartRG')
                                    disabled
                                    title="Not supported for SmartRG devices"
                                @endif
                                class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-warning"] }}-600 hover:bg-{{ $colors["btn-warning"] }}-700 {{ $device->manufacturer === 'SmartRG' ? 'opacity-50 cursor-not-allowed' : '' }}">
                                Trace Route
                                @if($device->manufacturer === 'SmartRG')
                                    <svg class="w-4 h-4 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                    </svg>
                                @endif
                            </button>
                        </form>

                        <!-- Firmware Upgrade -->
                        <form @submit.prevent="async (e) => {
                            if (!confirm('Are you sure you want to upgrade the firmware?')) return;

                            taskLoading = true;
                            taskMessage = 'Initiating Firmware Upgrade...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/firmware-upgrade', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Upgrading Firmware...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Error: ' + (result.error || result.message || 'Unknown error'));
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error initiating firmware upgrade: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700">
                                Upgrade Firmware
                            </button>
                        </form>

                        <!-- Factory Reset -->
                        <form @submit.prevent="async (e) => {
                            if (!confirm('⚠️ WARNING: This will erase ALL device settings!\n\nAre you sure?')) return;

                            taskLoading = true;
                            taskMessage = 'Initiating Factory Reset...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/factory-reset', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Factory Resetting Device...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Error: ' + (result.error || result.message || 'Unknown error'));
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error initiating factory reset: ' + error.message);
                            }
                        }">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-danger"] }}-600 hover:bg-{{ $colors["btn-danger"] }}-700">
                                Factory Reset
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- WAN & LAN Section (Side by Side) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- WAN/Internet Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-blue-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Internet (WAN)</h3>
                    <p class="mt-1 text-sm text-gray-600">WAN connection details</p>
                </div>
                <div class="border-t border-gray-200">
                    @php
                        // Determine data model
                        $dataModel = $device->getDataModel();
                        $isDevice2 = $dataModel === 'Device:2';

                        // Get exact parameter helper
                        $getExactParam = function($name) use ($device) {
                            $param = $device->parameters()->where('name', $name)->first();
                            return $param ? $param->value : '-';
                        };

                        // Dynamically discover WAN prefix
                        $wanIpParam = $device->parameters()
                            ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
                            ->first();

                        if ($wanIpParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanIpParam->name, $matches)) {
                            $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
                        } else {
                            $wanPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
                        }

                        $status = $isDevice2 ? $getExactParam("Device.IP.Interface.1.Status") : $getExactParam("{$wanPrefix}.ConnectionStatus");
                    @endphp
                    <dl>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Connection Status</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                                @if($status === 'Connected' || $status === 'Up')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $status }}</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">{{ $status }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">External IP Address</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Default Gateway</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DNS Servers</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono text-xs">
                                {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- LAN Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-green-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">LAN</h3>
                    <p class="mt-1 text-sm text-gray-600">Local network configuration</p>
                </div>
                <div class="border-t border-gray-200">
                    @php
                        $lanPrefix = $isDevice2 ? 'Device.IP.Interface.2' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                        $dhcpPrefix = $isDevice2 ? 'Device.DHCPv4.Server.Pool.1' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                        $dhcpEnabled = $isDevice2 ? $getExactParam("{$dhcpPrefix}.Enable") : $getExactParam("{$lanPrefix}.DHCPServerEnable");
                    @endphp
                    <dl>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">LAN IP Address</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Subnet Mask</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DHCP Server</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                                @if($dhcpEnabled === 'true' || $dhcpEnabled === '1')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Disabled</span>
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DHCP Range</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono text-xs">
                                {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                                -
                                {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- WiFi Section (Full Width) -->
        @php
            $wifiParams = $device->parameters()
                ->where(function($q) {
                    $q->where('name', 'LIKE', '%WiFi.Radio%')
                      ->orWhere('name', 'LIKE', '%WiFi.SSID%')
                      ->orWhere('name', 'LIKE', '%WLANConfiguration%');
                })
                ->get();

            // Organize by radio/SSID
            $radios = [];
            foreach ($wifiParams as $param) {
                if (preg_match('/Radio\.(\d+)/', $param->name, $matches) || preg_match('/WLANConfiguration\.(\d+)/', $param->name, $matches)) {
                    $radioNum = $matches[1];
                    if (!isset($radios[$radioNum])) {
                        $radios[$radioNum] = [];
                    }
                    $radios[$radioNum][$param->name] = $param->value;
                }
            }
        @endphp

        @if(count($radios) > 0)
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6 bg-purple-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">WiFi Networks</h3>
                <p class="mt-1 text-sm text-gray-600">Wireless network status</p>
            </div>
            <div class="border-t border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($radios as $radioNum => $radioData)
                        @php
                            $ssid = '';
                            $enabled = false;
                            $channel = '-';
                            $band = '-';

                            foreach ($radioData as $key => $value) {
                                if (str_contains($key, 'SSID') && !str_contains($key, 'Enable')) {
                                    $ssid = $value;
                                } elseif (str_contains($key, 'Enable')) {
                                    $enabled = ($value === 'true' || $value === '1');
                                } elseif (str_contains($key, 'Channel')) {
                                    $channel = $value;
                                } elseif (str_contains($key, 'OperatingFrequencyBand')) {
                                    $band = $value;
                                } elseif (str_contains($key, 'Channel') && is_numeric($value)) {
                                    $channel = $value;
                                    $band = (int)$value <= 14 ? '2.4GHz' : '5GHz';
                                }
                            }
                        @endphp

                        <button @click="activeTab = 'wifi'" class="w-full bg-gray-50 hover:bg-gray-100 rounded-lg p-4 border {{ $enabled ? 'border-green-200 hover:border-green-300' : 'border-gray-200 hover:border-gray-300' }} transition-colors text-left">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-900">{{ $ssid ?: "Radio $radioNum" }}</h4>
                                @if($enabled)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Disabled</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-600 space-y-1">
                                <div>Band: <span class="font-mono">{{ $band }}</span></div>
                                <div>Channel: <span class="font-mono">{{ $channel }}</span></div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <span class="text-xs text-blue-600 font-medium">Click to configure →</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Connected Devices Section -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-yellow-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Connected Devices</h3>
                <p class="mt-1 text-sm text-gray-600">Active devices on the network</p>
            </div>
            <div class="border-t border-gray-200">
                @php
                    // Get all host table entries
                    $dashboardHostParams = $device->parameters()
                        ->where(function($q) {
                            $q->where('name', 'LIKE', '%Hosts.Host.%')
                              ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
                        })
                        ->get();

                    // Get WiFi AssociatedDevice parameters for signal strength and rates
                    $dashboardWifiParams = $device->parameters()
                        ->where(function($q) {
                            $q->where('name', 'LIKE', '%AssociatedDevice.%')
                              ->orWhere('name', 'LIKE', '%WLANConfiguration.%AssociatedDevice%');
                        })
                        ->get();

                    // Organize by host number
                    $dashboardHosts = [];
                    foreach ($dashboardHostParams as $param) {
                        if (preg_match('/Host\.(\d+)\.(.+)/', $param->name, $matches)) {
                            $hostNum = $matches[1];
                            $field = $matches[2];
                            if (!isset($dashboardHosts[$hostNum])) {
                                $dashboardHosts[$hostNum] = [];
                            }
                            $dashboardHosts[$hostNum][$field] = $param->value;
                        }
                    }

                    // Organize WiFi associated devices by MAC address
                    $dashboardWifiDevices = [];
                    foreach ($dashboardWifiParams as $param) {
                        if (preg_match('/AssociatedDevice\.(\d+)\.(.+)/', $param->name, $matches)) {
                            $deviceNum = $matches[1];
                            $field = $matches[2];
                            if (!isset($dashboardWifiDevices[$deviceNum])) {
                                $dashboardWifiDevices[$deviceNum] = [];
                            }
                            $dashboardWifiDevices[$deviceNum][$field] = $param->value;

                            // Also capture which WLAN configuration this is from for band detection
                            if (preg_match('/WLANConfiguration\.(\d+)\.AssociatedDevice/', $param->name, $wlanMatches)) {
                                $dashboardWifiDevices[$deviceNum]['_wlan_config'] = $wlanMatches[1];
                            } elseif (preg_match('/WiFi\.AccessPoint\.(\d+)\.AssociatedDevice/', $param->name, $apMatches)) {
                                $dashboardWifiDevices[$deviceNum]['_access_point'] = $apMatches[1];
                            }
                        }
                    }

                    // Create a lookup table by MAC address for WiFi devices
                    $dashboardWifiByMac = [];
                    foreach ($dashboardWifiDevices as $wifiDevice) {
                        $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? $wifiDevice['MACAddress'] ?? null;
                        if ($mac) {
                            $dashboardWifiByMac[strtolower(str_replace([':', '-'], '', $mac))] = $wifiDevice;
                        }
                    }

                    // Filter out inactive hosts
                    $dashboardHosts = array_filter($dashboardHosts, function($host) {
                        return isset($host['Active']) && ($host['Active'] === 'true' || $host['Active'] === '1');
                    });
                @endphp

                @if(count($dashboardHosts) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interface</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($dashboardHosts as $dashHost)
                                @php
                                    // Look up WiFi data by MAC address
                                    $dashHostMac = $dashHost['MACAddress'] ?? $dashHost['PhysAddress'] ?? '';
                                    $dashNormalizedMac = strtolower(str_replace([':', '-'], '', $dashHostMac));
                                    $dashWifiData = $dashboardWifiByMac[$dashNormalizedMac] ?? null;

                                    // Detect device type
                                    $dashHostname = strtolower($dashHost['HostName'] ?? '');
                                    $dashInterface = strtolower($dashHost['InterfaceType'] ?? '');

                                    $dashIcon = '❓';
                                    if (str_contains($dashHostname, 'iphone') || str_contains($dashHostname, 'android') || str_contains($dashHostname, 'samsung')) {
                                        $dashIcon = '📱';
                                    } elseif (str_contains($dashHostname, 'ipad') || str_contains($dashHostname, 'tablet')) {
                                        $dashIcon = '📱';
                                    } elseif (str_contains($dashHostname, 'macbook') || str_contains($dashHostname, 'laptop')) {
                                        $dashIcon = '💻';
                                    } elseif (str_contains($dashHostname, 'desktop') || str_contains($dashHostname, 'pc-')) {
                                        $dashIcon = '🖥️';
                                    } elseif (str_contains($dashHostname, 'appletv') || str_contains($dashHostname, 'roku') || str_contains($dashHostname, 'chromecast')) {
                                        $dashIcon = '📺';
                                    } elseif (str_contains($dashInterface, 'ethernet') || str_contains($dashInterface, 'eth')) {
                                        $dashIcon = '🔌';
                                    } elseif ($dashWifiData) {
                                        $dashIcon = '📡';
                                    }

                                    // Get signal strength
                                    $dashSignalStrength = null;
                                    $dashSignalClass = '';
                                    $dashSignalIcon = '';

                                    // Check WiFi AssociatedDevice data first
                                    if ($dashWifiData) {
                                        $dashSignalStrength = $dashWifiData['SignalStrength'] ?? null;
                                    }

                                    // SmartRG stores signal in Host table as X_CLEARACCESS_COM_WlanRssi
                                    if ($dashSignalStrength === null && isset($dashHost['X_CLEARACCESS_COM_WlanRssi'])) {
                                        $rssi = (int)$dashHost['X_CLEARACCESS_COM_WlanRssi'];
                                        if ($rssi !== 0) { // 0 means not connected via WiFi
                                            $dashSignalStrength = $rssi;
                                        }
                                    }

                                    if ($dashSignalStrength !== null) {
                                        $signal = (int)$dashSignalStrength;
                                        if ($signal >= -50) {
                                            $dashSignalClass = 'text-green-600';
                                            $dashSignalIcon = '▂▃▄▅▆';
                                        } elseif ($signal >= -60) {
                                            $dashSignalClass = 'text-green-500';
                                            $dashSignalIcon = '▂▃▄▅';
                                        } elseif ($signal >= -70) {
                                            $dashSignalClass = 'text-yellow-500';
                                            $dashSignalIcon = '▂▃▄';
                                        } elseif ($signal >= -80) {
                                            $dashSignalClass = 'text-orange-500';
                                            $dashSignalIcon = '▂▃ ⚠️';
                                        } else {
                                            $dashSignalClass = 'text-red-500';
                                            $dashSignalIcon = '▂ ⚠️';
                                        }
                                    }

                                    // Get rates
                                    $dashDownRate = $dashWifiData['LastDataDownlinkRate'] ?? null;

                                    // SmartRG stores rate in Host table as X_CLEARACCESS_COM_WlanTxRate (in kbps)
                                    if ($dashDownRate === null && isset($dashHost['X_CLEARACCESS_COM_WlanTxRate'])) {
                                        $txRate = (int)$dashHost['X_CLEARACCESS_COM_WlanTxRate'];
                                        if ($txRate > 0) {
                                            $dashDownRate = $txRate; // Already in kbps
                                        }
                                    }

                                    // Get band for interface display
                                    $dashBand = null;
                                    if ($dashWifiData) {
                                        $wlanConfig = $dashWifiData['_wlan_config'] ?? null;
                                        if ($wlanConfig) {
                                            $bandParam = $device->parameters()
                                                ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.OperatingFrequencyBand")
                                                ->first();
                                            if ($bandParam) {
                                                $dashBand = str_contains($bandParam->value, '2.4') ? '2.4GHz' : '5GHz';
                                            } else {
                                                // Try channel-based detection
                                                $channelParam = $device->parameters()
                                                    ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.Channel")
                                                    ->first();
                                                if ($channelParam) {
                                                    $channel = (int)$channelParam->value;
                                                    $dashBand = ($channel >= 1 && $channel <= 14) ? '2.4GHz' : '5GHz';
                                                }
                                            }
                                        }
                                    }

                                    // Determine interface display
                                    $dashInterfaceType = $dashHost['InterfaceType'] ?? $dashHost['AddressSource'] ?? '-';
                                    if ($dashBand) {
                                        $dashInterfaceType = "WiFi ({$dashBand})";
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center">
                                            <span class="mr-2">{{ $dashIcon }}</span>
                                            <span class="text-gray-900">{{ $dashHost['HostName'] ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">{{ $dashHost['IPAddress'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $dashInterfaceType }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($dashSignalStrength !== null)
                                            <div class="flex items-center space-x-1">
                                                <span class="{{ $dashSignalClass }}">{{ $dashSignalIcon }}</span>
                                                <span class="{{ $dashSignalClass }} text-xs">{{ $dashSignalStrength }} dBm</span>
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($dashDownRate)
                                            <span class="text-gray-900 font-mono text-xs">
                                                {{ number_format($dashDownRate / 1000, 0) }} Mbps
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        No connected devices found.
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Tasks Section -->
        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Recent Tasks</h3>
            </div>
            <div class="border-t border-gray-200">
                @php
                    $recentTasks = $device->tasks()->orderBy('created_at', 'desc')->limit(5)->get();
                @endphp

                @if($recentTasks->count() > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recentTasks as $task)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ ucwords(str_replace('_', ' ', $task->task_type)) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($task->status === 'completed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                                        @elseif($task->status === 'failed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                        @elseif($task->status === 'cancelled')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Cancelled</span>
                                        @elseif($task->status === 'pending')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">{{ ucfirst($task->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $task->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        No tasks yet.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Device Info Tab -->
    <div x-show="activeTab === 'info'" x-cloak class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Device Information</h3>
        </div>
        <div class="border-t border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Device ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->id }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->manufacturer ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">OUI</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->oui ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Product Class</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->product_class ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->serial_number ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Software Version</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->software_version ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Hardware Version</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->hardware_version ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">{{ $device->ip_address ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Data Model</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $device->getDataModel() }}
                        </span>
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Connection Request URL</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 break-all">{{ $device->connection_request_url ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Parameters Tab -->
    <div x-show="activeTab === 'parameters'" x-cloak class="bg-white shadow rounded-lg overflow-hidden" x-data="{
        searchQuery: '',
        searchResults: null,
        searching: false,
        searchTimeout: null,

        async searchParameters() {
            if (this.searchQuery.length < 2) {
                this.searchResults = null;
                return;
            }

            this.searching = true;

            try {
                const response = await fetch('/api/devices/{{ $device->id }}/parameters?search=' + encodeURIComponent(this.searchQuery));
                const data = await response.json();
                this.searchResults = data;
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                this.searching = false;
            }
        },

        onSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.searchParameters();
            }, 300);
        }
    }">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Device Parameters</h3>
                    <p class="mt-1 text-sm text-gray-500">All parameters stored for this device</p>
                </div>
                <div>
                    <a :href="`/api/devices/{{ $device->id }}/parameters/export?format=csv${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span x-text="searchQuery ? 'Export Filtered CSV' : 'Export CSV'"></span>
                    </a>
                </div>
            </div>

            <!-- Smart Search Box -->
            <div class="mt-4">
                <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input="onSearchInput()"
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Search parameters by name or value... (e.g., WiFi, IP, Serial)"
                    >
                </div>
                <p class="mt-1 text-xs text-gray-500" x-show="!searching && searchResults">
                    Found <span x-text="searchResults?.data?.length || 0"></span> matching parameter<span x-text="(searchResults?.data?.length !== 1) ? 's' : ''"></span>
                </p>
                <p class="mt-1 text-xs text-indigo-600" x-show="searching">
                    <svg class="inline h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </p>
            </div>
        </div>

        <!-- Search Results -->
        <div class="overflow-x-auto" x-show="searchResults" x-cloak>
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parameter Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="param in searchResults?.data || []" :key="param.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900" x-text="param.name"></td>
                        <td class="px-6 py-4 text-sm text-gray-900 break-all" x-text="param.value"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="param.type || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="param.last_updated_human || '-'"></td>
                    </tr>
                </template>
                <template x-if="searchResults?.data?.length === 0">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No matching parameters found.</td>
                    </tr>
                </template>
            </tbody>
            </table>
        </div>

        <!-- Default Parameters Table (when not searching) -->
        <div class="overflow-x-auto" x-show="!searchResults">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parameter Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($parameters as $param)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $param->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900 break-all">{{ $param->value }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $param->type ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $param->last_updated ? $param->last_updated->diffForHumans() : '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No parameters found.</td>
                </tr>
                @endforelse
            </tbody>
            </table>
        </div>

        @if($parameters->hasPages())
        <div class="px-4 py-3 border-t border-gray-200" x-show="!searchResults">
            {{ $parameters->links() }}
        </div>
        @endif
    </div>

    <!-- Tasks Tab -->
    <div x-show="activeTab === 'tasks'" x-cloak class="bg-white shadow rounded-lg overflow-hidden" x-data="{
        expandedTask: null
    }">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Device Tasks</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($tasks as $task)
                <tr class="hover:bg-gray-50 {{ ($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result ? 'cursor-pointer' : '' }}"
                    @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                    @click="expandedTask = expandedTask === {{ $task->id }} ? null : {{ $task->id }}"
                    @endif>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ $task->id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ str_replace('_', ' ', ucwords($task->task_type, '_')) }}
                        @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                            <span class="ml-2 text-blue-600">▼</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($task->status === 'pending')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                        @elseif($task->status === 'sent')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Sent</span>
                        @elseif($task->status === 'completed')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                        @elseif($task->status === 'cancelled')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Cancelled</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $task->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $task->completed_at ? $task->completed_at->format('Y-m-d H:i:s') : '-' }}</td>
                </tr>
                @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                <tr x-show="expandedTask === {{ $task->id }}" x-cloak class="bg-gray-50">
                    <td colspan="5" class="px-6 py-4">
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <h4 class="font-semibold text-gray-900 mb-3">Diagnostic Results</h4>
                            @if($task->task_type === 'ping_diagnostics')
                                @php
                                    $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
                                    $firstKey = array_key_first($results ?? []);
                                    $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.IPPingDiagnostics' : 'InternetGatewayDevice.IPPingDiagnostics';

                                    // Get timing values
                                    $successCount = (int)($results["{$prefix}.SuccessCount"]['value'] ?? 0);
                                    $avgTime = (int)($results["{$prefix}.AverageResponseTime"]['value'] ?? 0);
                                    $minTime = (int)($results["{$prefix}.MinimumResponseTime"]['value'] ?? 0);
                                    $maxTime = (int)($results["{$prefix}.MaximumResponseTime"]['value'] ?? 0);

                                    // Detect invalid timing data (firmware bug - returns garbage values)
                                    // Invalid if: values >= 4000000ms (near uint32 max) OR all zeros when pings succeeded
                                    $invalidTiming = ($minTime >= 4000000 || $maxTime >= 4000000 || $avgTime >= 4000000) ||
                                                    ($successCount > 0 && $minTime == 0 && $maxTime == 0 && $avgTime == 0);
                                @endphp
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Success Count:</span>
                                        <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.SuccessCount"]['value'] ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Failure Count:</span>
                                        <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.FailureCount"]['value'] ?? 'N/A' }}</span>
                                    </div>
                                    @if($invalidTiming)
                                        <div class="col-span-2">
                                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                                <div class="flex items-start">
                                                    <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                    </svg>
                                                    <div>
                                                        <p class="text-sm font-medium text-yellow-800">Invalid Timing Data</p>
                                                        <p class="text-xs text-yellow-700 mt-1">Device firmware returned invalid response times, but {{ $successCount }} ping(s) succeeded. This is a known firmware bug on some devices.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">Average Response Time:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.AverageResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">Min Response Time:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.MinimumResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">Max Response Time:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.MaximumResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">State:</span>
                                        <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.DiagnosticsState"]['value'] ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            @else
                                @php
                                    $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
                                    $firstKey = array_key_first($results ?? []);
                                    $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.TraceRouteDiagnostics' : 'InternetGatewayDevice.TraceRouteDiagnostics';

                                    // Extract hop data
                                    $hops = [];
                                    foreach ($results as $key => $data) {
                                        if (preg_match('/.RouteHops\.(\d+)\.(.+)/', $key, $matches)) {
                                            $hopNum = (int)$matches[1];
                                            $field = $matches[2];
                                            if (!isset($hops[$hopNum])) {
                                                $hops[$hopNum] = ['number' => $hopNum];
                                            }
                                            $hops[$hopNum][$field] = $data['value'] ?? '';
                                        }
                                    }
                                    ksort($hops);
                                @endphp
                                <div class="space-y-4">
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">Response Time:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.ResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">Number of Hops:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.RouteHopsNumberOfEntries"]['value'] ?? 'N/A' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">State:</span>
                                            <span class="ml-2 text-sm text-gray-900">{{ $results["{$prefix}.DiagnosticsState"]['value'] ?? 'N/A' }}</span>
                                        </div>
                                    </div>

                                    @if(count($hops) > 0)
                                    <div>
                                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Route Hops</h5>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hop</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Host</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">RTT</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    @foreach($hops as $hop)
                                                    <tr>
                                                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">{{ $hop['number'] }}</td>
                                                        <td class="px-3 py-2 text-gray-900">{{ $hop['HopHost'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">{{ $hop['HopHostAddress'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">{{ $hop['HopRTTimes'] ?? '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No tasks found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Sessions Tab -->
    <div x-show="activeTab === 'sessions'" x-cloak class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">CWMP Sessions</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ended</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Events</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($sessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ $session->id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->started_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->ended_at ? $session->ended_at->format('Y-m-d H:i:s') : 'In Progress' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->messages_exchanged }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        @if($session->inform_events)
                            @foreach($session->inform_events as $event)
                                <span class="inline-block bg-gray-100 rounded px-2 py-1 text-xs mr-1 mb-1">{{ $event['code'] ?? 'Unknown' }}</span>
                            @endforeach
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No sessions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Troubleshooting Tab -->
    <div x-show="activeTab === 'troubleshooting'" x-cloak class="space-y-6">
        <!-- Refresh Button -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-blue-900">Troubleshooting Information</h3>
                <p class="mt-1 text-sm text-blue-700">Click refresh to fetch the latest WAN, LAN, WiFi, and connected device information from the device.</p>
            </div>
            <form @submit.prevent="async (e) => {
                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/refresh-troubleshooting', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Refreshing Troubleshooting Info...', result.task.id);
                    } else {
                        alert('Refresh started, but no task ID returned');
                    }
                } catch (error) {
                    alert('Error refreshing troubleshooting info: ' + error);
                }
            }">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh Troubleshooting Info
                </button>
            </form>
        </div>

        @php
            // Helper function to get parameter value
            $getParam = function($pattern) use ($device) {
                $param = $device->parameters()->where('name', 'LIKE', "%{$pattern}%")->first();
                return $param ? $param->value : '-';
            };

            // Helper to get exact parameter
            $getExactParam = function($name) use ($device) {
                $param = $device->parameters()->where('name', $name)->first();
                return $param ? $param->value : '-';
            };

            // Determine data model
            $dataModel = $device->getDataModel();
            $isDevice2 = $dataModel === 'Device:2';
        @endphp

        <!-- 1. WAN Information -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-blue-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">WAN Information</h3>
                <p class="mt-1 text-sm text-gray-600">Internet connection details</p>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    @if($isDevice2)
                        @php
                            $wanPrefix = 'Device.IP.Interface.1';
                            $pppPrefix = 'Device.PPP.Interface.1';
                        @endphp
                    @else
                        @php
                            // Dynamically discover WAN prefix by finding which instance actually has data
                            // This handles manufacturer-specific instance numbering (e.g., Calix uses WANDevice.3, WANIPConnection.14)
                            $wanIpParam = $device->parameters()
                                ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
                                ->first();

                            if ($wanIpParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanIpParam->name, $matches)) {
                                // Use the actual instance numbers found in the database
                                $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
                            } else {
                                // Fallback to standard instance numbers if no data found yet
                                $wanPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
                            }

                            // Same for PPP
                            $wanPppParam = $device->parameters()
                                ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANPPPConnection.%.ExternalIPAddress')
                                ->first();

                            if ($wanPppParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANPPPConnection\.(\d+)\./', $wanPppParam->name, $matches)) {
                                $pppPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANPPPConnection.{$matches[3]}";
                            } else {
                                $pppPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
                            }
                        @endphp
                    @endif

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Connection Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                            @php
                                $status = $isDevice2 ? $getExactParam("{$wanPrefix}.Status") : $getExactParam("{$wanPrefix}.ConnectionStatus");
                            @endphp
                            @if($status === 'Connected' || $status === 'Up')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $status }}</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">{{ $status }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">External IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Default Gateway</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DNS Servers</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">MAC Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.MACAddress") : $getExactParam("{$wanPrefix}.MACAddress") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Uptime</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                            @php
                                $uptime = $isDevice2 ? $getExactParam("{$wanPrefix}.Uptime") : $getExactParam("{$wanPrefix}.Uptime");
                                if ($uptime !== '-' && is_numeric($uptime)) {
                                    $days = floor($uptime / 86400);
                                    $hours = floor(($uptime % 86400) / 3600);
                                    $minutes = floor(($uptime % 3600) / 60);
                                    $uptime = "{$days}d {$hours}h {$minutes}m";
                                }
                            @endphp
                            {{ $uptime }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 2. LAN Information -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-green-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">LAN Information</h3>
                <p class="mt-1 text-sm text-gray-600">Local network configuration</p>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    @php
                        $lanPrefix = $isDevice2 ? 'Device.IP.Interface.2' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                        $dhcpPrefix = $isDevice2 ? 'Device.DHCPv4.Server.Pool.1' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                    @endphp

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">LAN IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Subnet Mask</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DHCP Server</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                            @php
                                $dhcpEnabled = $isDevice2 ? $getExactParam("{$dhcpPrefix}.Enable") : $getExactParam("{$lanPrefix}.DHCPServerEnable");
                            @endphp
                            @if($dhcpEnabled === 'true' || $dhcpEnabled === '1')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Disabled</span>
                            @endif
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DHCP Start Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DHCP End Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 3. WiFi Radio Status -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-purple-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">WiFi Radio Status</h3>
                <p class="mt-1 text-sm text-gray-600">Wireless radio configuration and status</p>
            </div>
            <div class="border-t border-gray-200 space-y-6 p-6">
                @php
                    // Get all WiFi-related parameters
                    $wifiParams = $device->parameters()
                        ->where(function($q) {
                            $q->where('name', 'LIKE', '%WiFi.Radio.%')
                              ->orWhere('name', 'LIKE', '%WiFi.SSID.%')
                              ->orWhere('name', 'LIKE', '%WLANConfiguration%');
                        })
                        ->get();

                    // Organize by radio (1 = 2.4GHz, 2 = 5GHz typically)
                    $radios = [];
                    foreach ($wifiParams as $param) {
                        if (preg_match('/Radio\.(\d+)/', $param->name, $matches)) {
                            $radioNum = $matches[1];
                            if (!isset($radios[$radioNum])) {
                                $radios[$radioNum] = [];
                            }
                            $radios[$radioNum][$param->name] = $param->value;
                        } elseif (preg_match('/WLANConfiguration\.(\d+)/', $param->name, $matches)) {
                            $radioNum = $matches[1];
                            if (!isset($radios[$radioNum])) {
                                $radios[$radioNum] = [];
                            }
                            $radios[$radioNum][$param->name] = $param->value;
                        }
                    }
                @endphp

                @forelse($radios as $radioNum => $radioData)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">
                            Radio {{ $radioNum }}
                            @php
                                $freq = null;
                                foreach ($radioData as $key => $value) {
                                    if (str_contains($key, 'OperatingFrequencyBand')) {
                                        $freq = $value;
                                        break;
                                    } elseif (str_contains($key, 'Channel') && is_numeric($value)) {
                                        $freq = (int)$value <= 14 ? '2.4GHz' : '5GHz';
                                        break;
                                    }
                                }
                            @endphp
                            @if($freq)
                                <span class="ml-2 text-sm font-normal text-gray-600">({{ $freq }})</span>
                            @endif
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($radioData as $key => $value)
                                @php
                                    $label = '';
                                    $showParam = false;

                                    if (str_contains($key, '.Enable')) {
                                        $label = 'Status';
                                        $showParam = true;
                                    } elseif (str_contains($key, '.SSID') && !str_contains($key, 'BSSID')) {
                                        $label = 'SSID';
                                        $showParam = true;
                                    } elseif (str_contains($key, '.Channel')) {
                                        $label = 'Channel';
                                        $showParam = true;
                                    } elseif (str_contains($key, 'OperatingFrequencyBand')) {
                                        $label = 'Frequency Band';
                                        $showParam = true;
                                    } elseif (str_contains($key, 'TransmitPower')) {
                                        $label = 'Transmit Power';
                                        $showParam = true;
                                    } elseif (str_contains($key, 'Standard')) {
                                        $label = 'Standard';
                                        $showParam = true;
                                    }
                                @endphp

                                @if($showParam)
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">{{ $label }}:</span>
                                        <span class="ml-2 text-sm text-gray-900">
                                            @if($label === 'Status')
                                                @if($value === 'true' || $value === '1')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Disabled</span>
                                                @endif
                                            @else
                                                {{ $value }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No WiFi radio information available. Click "Query Device Info" to fetch WiFi parameters.</p>
                @endforelse
            </div>
        </div>

        <!-- 4. Connected Devices -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-yellow-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Connected Devices</h3>
                <p class="mt-1 text-sm text-gray-600">Devices connected to this gateway</p>
            </div>
            <div class="border-t border-gray-200">
                @php
                    // Get all host table entries
                    $hostParams = $device->parameters()
                        ->where(function($q) {
                            $q->where('name', 'LIKE', '%Hosts.Host.%')
                              ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
                        })
                        ->get();

                    // Get WiFi AssociatedDevice parameters for signal strength and rates
                    $wifiParams = $device->parameters()
                        ->where(function($q) {
                            $q->where('name', 'LIKE', '%AssociatedDevice.%')
                              ->orWhere('name', 'LIKE', '%WLANConfiguration.%AssociatedDevice%');
                        })
                        ->get();

                    // Organize by host number
                    $hosts = [];
                    foreach ($hostParams as $param) {
                        if (preg_match('/Host\.(\d+)\.(.+)/', $param->name, $matches)) {
                            $hostNum = $matches[1];
                            $field = $matches[2];
                            if (!isset($hosts[$hostNum])) {
                                $hosts[$hostNum] = [];
                            }
                            $hosts[$hostNum][$field] = $param->value;
                        }
                    }

                    // Organize WiFi associated devices by MAC address
                    $wifiDevices = [];
                    foreach ($wifiParams as $param) {
                        if (preg_match('/AssociatedDevice\.(\d+)\.(.+)/', $param->name, $matches)) {
                            $deviceNum = $matches[1];
                            $field = $matches[2];
                            if (!isset($wifiDevices[$deviceNum])) {
                                $wifiDevices[$deviceNum] = [];
                            }
                            $wifiDevices[$deviceNum][$field] = $param->value;

                            // Also capture which WLAN configuration this is from for band detection
                            if (preg_match('/WLANConfiguration\.(\d+)\.AssociatedDevice/', $param->name, $wlanMatches)) {
                                $wifiDevices[$deviceNum]['_wlan_config'] = $wlanMatches[1];
                            } elseif (preg_match('/WiFi\.AccessPoint\.(\d+)\.AssociatedDevice/', $param->name, $apMatches)) {
                                $wifiDevices[$deviceNum]['_access_point'] = $apMatches[1];
                            }
                        }
                    }

                    // Create a lookup table by MAC address for WiFi devices
                    $wifiByMac = [];
                    foreach ($wifiDevices as $wifiDevice) {
                        $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? $wifiDevice['MACAddress'] ?? null;
                        if ($mac) {
                            $wifiByMac[strtolower(str_replace([':', '-'], '', $mac))] = $wifiDevice;
                        }
                    }

                    // Helper function to detect device type based on hostname and interface
                    $detectDeviceType = function($host, $wifiData) {
                        $hostname = strtolower($host['HostName'] ?? '');
                        $interface = strtolower($host['InterfaceType'] ?? '');

                        // Check for common device type patterns
                        if (str_contains($hostname, 'iphone') || str_contains($hostname, 'android') || str_contains($hostname, 'samsung') || str_contains($hostname, 'pixel')) {
                            return ['type' => 'Mobile', 'icon' => '📱'];
                        } elseif (str_contains($hostname, 'ipad') || str_contains($hostname, 'tablet')) {
                            return ['type' => 'Tablet', 'icon' => '📱'];
                        } elseif (str_contains($hostname, 'macbook') || str_contains($hostname, 'laptop') || str_contains($hostname, 'thinkpad')) {
                            return ['type' => 'Laptop', 'icon' => '💻'];
                        } elseif (str_contains($hostname, 'desktop') || str_contains($hostname, 'pc-')) {
                            return ['type' => 'Desktop', 'icon' => '🖥️'];
                        } elseif (str_contains($hostname, 'appletv') || str_contains($hostname, 'roku') || str_contains($hostname, 'chromecast') || str_contains($hostname, 'firetv')) {
                            return ['type' => 'Media', 'icon' => '📺'];
                        } elseif (str_contains($hostname, 'printer') || str_contains($hostname, 'canon') || str_contains($hostname, 'hp-')) {
                            return ['type' => 'Printer', 'icon' => '🖨️'];
                        } elseif (str_contains($hostname, 'nest') || str_contains($hostname, 'thermostat') || str_contains($hostname, 'camera')) {
                            return ['type' => 'IoT', 'icon' => '🏠'];
                        } elseif (str_contains($interface, 'ethernet') || str_contains($interface, 'eth')) {
                            return ['type' => 'Wired', 'icon' => '🔌'];
                        } elseif ($wifiData) {
                            return ['type' => 'WiFi Device', 'icon' => '📡'];
                        }

                        return ['type' => 'Unknown', 'icon' => '❓'];
                    };

                    // Helper function to get WiFi band from WLAN config or frequency
                    $getWifiBand = function($wifiData) use ($device, $isDevice2) {
                        if (!$wifiData) return null;

                        // Try to get operating frequency band from the radio
                        $wlanConfig = $wifiData['_wlan_config'] ?? null;
                        $accessPoint = $wifiData['_access_point'] ?? null;

                        if ($wlanConfig) {
                            $bandParam = $device->parameters()
                                ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.OperatingFrequencyBand")
                                ->first();
                            if ($bandParam) {
                                return str_contains($bandParam->value, '2.4') ? '2.4GHz' : '5GHz';
                            }

                            // Try channel-based detection
                            $channelParam = $device->parameters()
                                ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.Channel")
                                ->first();
                            if ($channelParam) {
                                $channel = (int)$channelParam->value;
                                return ($channel >= 1 && $channel <= 14) ? '2.4GHz' : '5GHz';
                            }
                        }

                        if ($accessPoint && $isDevice2) {
                            // For Device:2 model, try to get frequency from radio
                            $radioParam = $device->parameters()
                                ->where('name', 'LIKE', "Device.WiFi.Radio.%.OperatingFrequencyBand")
                                ->first();
                            if ($radioParam) {
                                return str_contains($radioParam->value, '2.4') ? '2.4GHz' : '5GHz';
                            }
                        }

                        return null;
                    };

                    // Filter out inactive hosts
                    $hosts = array_filter($hosts, function($host) {
                        return isset($host['Active']) && ($host['Active'] === 'true' || $host['Active'] === '1');
                    });
                @endphp

                @if(count($hosts) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interface</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Down/Up Rate</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($hosts as $host)
                                @php
                                    // Look up WiFi data by MAC address
                                    $hostMac = $host['MACAddress'] ?? $host['PhysAddress'] ?? '';
                                    $normalizedMac = strtolower(str_replace([':', '-'], '', $hostMac));
                                    $wifiData = $wifiByMac[$normalizedMac] ?? null;

                                    // Get signal strength
                                    $signalStrength = null;
                                    $signalClass = '';
                                    $signalIcon = '';

                                    // Check WiFi AssociatedDevice data first
                                    if ($wifiData) {
                                        $signalStrength = $wifiData['SignalStrength'] ?? null;
                                    }

                                    // SmartRG stores signal in Host table as X_CLEARACCESS_COM_WlanRssi
                                    if ($signalStrength === null && isset($host['X_CLEARACCESS_COM_WlanRssi'])) {
                                        $rssi = (int)$host['X_CLEARACCESS_COM_WlanRssi'];
                                        if ($rssi !== 0) { // 0 means not connected via WiFi
                                            $signalStrength = $rssi;
                                        }
                                    }

                                    if ($signalStrength !== null) {
                                        $signal = (int)$signalStrength;
                                        if ($signal >= -50) {
                                            $signalClass = 'text-green-600';
                                            $signalIcon = '▂▃▄▅▆';
                                        } elseif ($signal >= -60) {
                                            $signalClass = 'text-green-500';
                                            $signalIcon = '▂▃▄▅';
                                        } elseif ($signal >= -70) {
                                            $signalClass = 'text-yellow-500';
                                            $signalIcon = '▂▃▄';
                                        } elseif ($signal >= -80) {
                                            $signalClass = 'text-orange-500';
                                            $signalIcon = '▂▃ ⚠️';
                                        } else {
                                            $signalClass = 'text-red-500';
                                            $signalIcon = '▂ ⚠️';
                                        }
                                    }

                                    // Get rates
                                    $downRate = $wifiData['LastDataDownlinkRate'] ?? null;
                                    $upRate = $wifiData['LastDataUplinkRate'] ?? null;

                                    // SmartRG stores rates in Host table (in kbps)
                                    if ($downRate === null && isset($host['X_CLEARACCESS_COM_WlanTxRate'])) {
                                        $txRate = (int)$host['X_CLEARACCESS_COM_WlanTxRate'];
                                        if ($txRate > 0) {
                                            $downRate = $txRate; // Already in kbps
                                        }
                                    }
                                    if ($upRate === null && isset($host['X_CLEARACCESS_COM_WlanRxRate'])) {
                                        $rxRate = (int)$host['X_CLEARACCESS_COM_WlanRxRate'];
                                        if ($rxRate > 0) {
                                            $upRate = $rxRate; // Already in kbps
                                        }
                                    }

                                    // Get band
                                    $band = $getWifiBand($wifiData);

                                    // Detect device type
                                    $deviceTypeInfo = $detectDeviceType($host, $wifiData);

                                    // Determine interface display
                                    $interfaceType = $host['InterfaceType'] ?? $host['AddressSource'] ?? '-';
                                    if ($band) {
                                        $interfaceType = "WiFi ({$band})";
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center">
                                            <span class="mr-2">{{ $deviceTypeInfo['icon'] }}</span>
                                            <span class="text-gray-900">{{ $host['HostName'] ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">{{ $host['IPAddress'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">{{ $hostMac }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $deviceTypeInfo['type'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $interfaceType }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($signalStrength !== null)
                                            <div class="flex items-center space-x-2">
                                                <span class="{{ $signalClass }} font-mono">{{ $signalStrength }} dBm</span>
                                                <span class="{{ $signalClass }}">{{ $signalIcon }}</span>
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($downRate || $upRate)
                                            <div class="text-gray-900 font-mono text-xs">
                                                @if($downRate)
                                                    <div class="flex items-center">
                                                        <span class="text-gray-500 mr-1">↓</span>
                                                        <span>{{ number_format($downRate / 1000, 1) }} Mbps</span>
                                                    </div>
                                                @endif
                                                @if($upRate)
                                                    <div class="flex items-center">
                                                        <span class="text-gray-500 mr-1">↑</span>
                                                        <span>{{ number_format($upRate / 1000, 1) }} Mbps</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-{{ $colors["text-muted"] }}">No connected devices found. Click "Query Device Info" to fetch host table information.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 5. ACS Event Log -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-red-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">ACS Event Log</h3>
                <p class="mt-1 text-sm text-gray-600">Recent CWMP session events and activity</p>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sessions->take(20) as $session)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $session->started_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($session->inform_events)
                                    @foreach($session->inform_events as $event)
                                        <span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 text-xs font-semibold mr-1 mb-1">
                                            {{ $event['code'] ?? 'Unknown' }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                @if($session->ended_at)
                                    Duration: {{ $session->started_at->diffInSeconds($session->ended_at) }}s
                                @else
                                    <span class="text-yellow-600">In Progress</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->messages_exchanged }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No ACS events found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- WiFi Settings Tab -->
    <div x-show="activeTab === 'wifi'" x-cloak>
        @php
            // Get all WLAN configuration parameters
            $wlanConfigs = [];
            $wlanParams = $device->parameters()
                ->where('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%')
                ->whereNotLike('name', '%AssociatedDevice%')
                ->whereNotLike('name', '%Stats%')
                ->whereNotLike('name', '%WPS%')
                ->where(function ($query) {
                    // Include PreSharedKey.1 only for X_000631_KeyPassphrase, exclude other PreSharedKey params
                    $query->where('name', 'NOT LIKE', '%PreSharedKey.1%')
                        ->orWhere('name', 'LIKE', '%PreSharedKey.1.X_000631_KeyPassphrase');
                })
                ->get();

            foreach ($wlanParams as $param) {
                if (preg_match('/WLANConfiguration\.(\d+)\.(.+)/', $param->name, $matches)) {
                    $instance = (int) $matches[1];
                    $field = $matches[2];

                    if (!isset($wlanConfigs[$instance])) {
                        $wlanConfigs[$instance] = ['instance' => $instance];
                    }

                    // Normalize PreSharedKey.1.X_000631_KeyPassphrase to just X_000631_KeyPassphrase
                    if ($field === 'PreSharedKey.1.X_000631_KeyPassphrase') {
                        $field = 'X_000631_KeyPassphrase';
                    }

                    $wlanConfigs[$instance][$field] = $param->value;
                }
            }

            // Sort by instance number
            ksort($wlanConfigs);

            // Organize into 2.4GHz and 5GHz groups
            $wifi24Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] <= 8);
            $wifi5Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] >= 9);
        @endphp

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 class="text-sm font-medium text-blue-900">WiFi Configuration</h3>
                <p class="mt-1 text-sm text-blue-700">Manage wireless network settings for all SSIDs. Changes will be applied immediately via TR-069. Security type is set to WPA2-PSK with AES encryption.</p>
            </div>
        </div>

        <!-- 2.4GHz WiFi Networks -->
        @if(count($wifi24Ghz) > 0)
        @php
            // Get radio enabled status from first instance (all instances on same radio share this)
            $radio24GhzEnabled = collect($wifi24Ghz)->first()['RadioEnabled'] ?? '0';
        @endphp
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6" x-data="{ radio24GhzEnabled: {{ $radio24GhzEnabled === '1' ? 'true' : 'false' }} }">
            <div class="px-4 py-5 sm:px-6 bg-green-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">2.4GHz Networks</h3>
                        <p class="mt-1 text-sm text-gray-600">Wireless SSIDs on 2.4GHz band (instances 1-8)</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-gray-700">2.4GHz Radio:</span>
                        <button @click="async () => {
                            radio24GhzEnabled = !radio24GhzEnabled;
                            const message = (radio24GhzEnabled ? 'Enabling' : 'Disabling') + ' 2.4GHz Radio...';

                            // Show loading overlay immediately
                            taskLoading = true;
                            taskMessage = message;

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/wifi-radio', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        band: '2.4GHz',
                                        enabled: radio24GhzEnabled
                                    })
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking(message, result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Radio toggled, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error: ' + error);
                                radio24GhzEnabled = !radio24GhzEnabled;
                            }
                        }
                        " :class="radio24GhzEnabled ? 'bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700' : 'bg-{{ $colors["btn-secondary"] }}-400 hover:bg-{{ $colors["btn-secondary"] }}-500'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <span :class="radio24GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                        <span class="text-sm font-medium" :class="radio24GhzEnabled ? 'text-green-600' : 'text-gray-500'" x-text="radio24GhzEnabled ? 'Enabled' : 'Disabled'"></span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($wifi24Ghz as $config)
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex flex-col">
                        <form @submit.prevent="async (e) => {
                            const formData = new FormData(e.target);
                            const data = {};

                            // Get all form elements to properly detect checkboxes
                            const checkboxNames = new Set();
                            for (const element of e.target.elements) {
                                if (element.type === 'checkbox' && element.name) {
                                    checkboxNames.add(element.name);
                                }
                            }

                            // Process FormData entries
                            // Note: When checkbox is checked, FormData includes BOTH hidden (0) and checkbox (1) values
                            // We want the last value for each key
                            for (let [key, value] of formData.entries()) {
                                if (key !== '_token') {
                                    // Convert checkbox values to boolean
                                    if (checkboxNames.has(key)) {
                                        data[key] = value === '1';
                                    } else if (value === '') {
                                        // Don't send empty string values
                                        data[key] = undefined;
                                    } else {
                                        data[key] = value;
                                    }
                                }
                            }

                            // Show loading overlay immediately
                            taskLoading = true;
                            taskMessage = 'Updating WiFi Configuration...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/wifi-config', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify(data)
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Updating WiFi Configuration...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Configuration updated, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error updating configuration: ' + error);
                            }
                        }" class="space-y-3 h-full" x-data="{
                            autoChannel: {{ ($config['AutoChannelEnable'] ?? '0') === '1' ? 'true' : 'false' }},
                            autoChannelBandwidth: {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === 'Auto' || empty($config['X_000631_OperatingChannelBandwidth'] ?? '') ? 'true' : 'false' }}
                        }">
                            @csrf
                            <input type="hidden" name="instance" value="{{ $config['instance'] }}">
                            <input type="hidden" name="security_type" value="wpa2">

                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-gray-900">SSID {{ $config['instance'] }}</h4>
                                <label class="flex items-center">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" {{ ($config['Enable'] ?? '0') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Enabled</span>
                                </label>
                            </div>

                            <div class="space-y-3 flex-1">
                                <!-- SSID Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SSID Name</label>
                                    <input type="text" name="ssid" value="{{ $config['SSID'] ?? '' }}" maxlength="32"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        WiFi Password (WPA2-AES)
                                        @if(!empty($config['X_000631_KeyPassphrase']))
                                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">✓ Password Set</span>
                                        @else
                                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">⚠ No Password</span>
                                        @endif
                                    </label>
                                    <input type="text" name="password" value="{{ $config['X_000631_KeyPassphrase'] ?? '' }}"
                                           minlength="8" maxlength="63" placeholder="Enter new password (min 8 characters)"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="mt-1 text-xs text-gray-500">
                                        @if(!empty($config['X_000631_KeyPassphrase']) && $config['X_000631_KeyPassphrase'] === '********')
                                            <span class="text-green-600 font-medium">Current password: ********</span> (masked for security) - Leave blank to keep, or enter new password to change
                                        @elseif(!empty($config['X_000631_KeyPassphrase']))
                                            Current password shown above - Leave blank to keep, or enter new password to change
                                        @else
                                            No password currently set - Enter a password (min 8 characters)
                                        @endif
                                    </p>
                                </div>

                                <!-- Auto Channel -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="auto_channel" value="0">
                                        <input type="checkbox" name="auto_channel" value="1"
                                               x-model="autoChannel"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Auto Channel</span>
                                    </label>
                                    <div x-show="!autoChannel" class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700">Manual Channel</label>
                                        <input type="number" name="channel" value="{{ $config['Channel'] ?? '11' }}" min="1" max="11"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>

                                <!-- Channel Bandwidth -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="auto_channel_bandwidth" value="0">
                                        <input type="checkbox" name="auto_channel_bandwidth" value="1"
                                               x-model="autoChannelBandwidth"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Auto Channel Bandwidth</span>
                                    </label>
                                    <div x-show="!autoChannelBandwidth" class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700">Channel Bandwidth</label>
                                        <select name="channel_bandwidth" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="20MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '20MHz') === '20MHz' ? 'selected' : '' }}>20 MHz</option>
                                            <option value="40MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === '40MHz' ? 'selected' : '' }}>40 MHz</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- SSID Broadcast -->
                                <div class="flex items-center">
                                    <input type="hidden" name="ssid_broadcast" value="0">
                                    <input type="checkbox" name="ssid_broadcast" value="1" {{ ($config['SSIDAdvertisementEnabled'] ?? '1') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Broadcast SSID</span>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 space-y-2 border-t border-gray-200">
                                <div class="text-xs text-gray-600 text-center">
                                    <span class="font-medium">Status:</span> {{ $config['Status'] ?? 'Unknown' }}<br>
                                    <span class="font-medium">Standard:</span> {{ $config['Standard'] ?? '-' }}
                                </div>
                                <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- 5GHz WiFi Networks -->
        @if(count($wifi5Ghz) > 0)
        @php
            // Get radio enabled status from first instance (all instances on same radio share this)
            $radio5GhzEnabled = collect($wifi5Ghz)->first()['RadioEnabled'] ?? '0';
        @endphp
        <div class="bg-white shadow overflow-hidden sm:rounded-lg" x-data="{ radio5GhzEnabled: {{ $radio5GhzEnabled === '1' ? 'true' : 'false' }} }">
            <div class="px-4 py-5 sm:px-6 bg-purple-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">5GHz Networks</h3>
                        <p class="mt-1 text-sm text-gray-600">Wireless SSIDs on 5GHz band (instances 9-16)</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-gray-700">5GHz Radio:</span>
                        <button @click="async () => {
                            radio5GhzEnabled = !radio5GhzEnabled;
                            const message = (radio5GhzEnabled ? 'Enabling' : 'Disabling') + ' 5GHz Radio...';

                            // Show loading overlay immediately
                            taskLoading = true;
                            taskMessage = message;

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/wifi-radio', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        band: '5GHz',
                                        enabled: radio5GhzEnabled
                                    })
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking(message, result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Radio toggled, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error: ' + error);
                                radio5GhzEnabled = !radio5GhzEnabled;
                            }
                        }
                        " :class="radio5GhzEnabled ? 'bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700' : 'bg-{{ $colors["btn-secondary"] }}-400 hover:bg-{{ $colors["btn-secondary"] }}-500'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <span :class="radio5GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                        <span class="text-sm font-medium" :class="radio5GhzEnabled ? 'text-green-600' : 'text-gray-500'" x-text="radio5GhzEnabled ? 'Enabled' : 'Disabled'"></span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($wifi5Ghz as $config)
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex flex-col">
                        <form @submit.prevent="async (e) => {
                            const formData = new FormData(e.target);
                            const data = {};

                            // Get all form elements to properly detect checkboxes
                            const checkboxNames = new Set();
                            for (const element of e.target.elements) {
                                if (element.type === 'checkbox' && element.name) {
                                    checkboxNames.add(element.name);
                                }
                            }

                            // Process FormData entries
                            // Note: When checkbox is checked, FormData includes BOTH hidden (0) and checkbox (1) values
                            // We want the last value for each key
                            for (let [key, value] of formData.entries()) {
                                if (key !== '_token') {
                                    // Convert checkbox values to boolean
                                    if (checkboxNames.has(key)) {
                                        data[key] = value === '1';
                                    } else if (value === '') {
                                        // Don't send empty string values
                                        data[key] = undefined;
                                    } else {
                                        data[key] = value;
                                    }
                                }
                            }

                            // Show loading overlay immediately
                            taskLoading = true;
                            taskMessage = 'Updating WiFi Configuration...';

                            try {
                                const response = await fetch('/api/devices/{{ $device->id }}/wifi-config', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify(data)
                                });
                                const result = await response.json();
                                if (result.task && result.task.id) {
                                    startTaskTracking('Updating WiFi Configuration...', result.task.id);
                                } else {
                                    taskLoading = false;
                                    alert('Configuration updated, but no task ID returned');
                                }
                            } catch (error) {
                                taskLoading = false;
                                alert('Error updating configuration: ' + error);
                            }
                        }" class="space-y-3 h-full" x-data="{
                            autoChannel: {{ ($config['AutoChannelEnable'] ?? '0') === '1' ? 'true' : 'false' }},
                            autoChannelBandwidth: {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === 'Auto' || empty($config['X_000631_OperatingChannelBandwidth'] ?? '') ? 'true' : 'false' }}
                        }">
                            @csrf
                            <input type="hidden" name="instance" value="{{ $config['instance'] }}">
                            <input type="hidden" name="security_type" value="wpa2">

                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-gray-900">SSID {{ $config['instance'] }}</h4>
                                <label class="flex items-center">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" {{ ($config['Enable'] ?? '0') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Enabled</span>
                                </label>
                            </div>

                            <div class="space-y-3 flex-1">
                                <!-- SSID Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SSID Name</label>
                                    <input type="text" name="ssid" value="{{ $config['SSID'] ?? '' }}" maxlength="32"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        WiFi Password (WPA2-AES)
                                        @if(!empty($config['X_000631_KeyPassphrase']))
                                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">✓ Password Set</span>
                                        @else
                                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">⚠ No Password</span>
                                        @endif
                                    </label>
                                    <input type="text" name="password" value="{{ $config['X_000631_KeyPassphrase'] ?? '' }}"
                                           minlength="8" maxlength="63" placeholder="Enter new password (min 8 characters)"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="mt-1 text-xs text-gray-500">
                                        @if(!empty($config['X_000631_KeyPassphrase']) && $config['X_000631_KeyPassphrase'] === '********')
                                            <span class="text-green-600 font-medium">Current password: ********</span> (masked for security) - Leave blank to keep, or enter new password to change
                                        @elseif(!empty($config['X_000631_KeyPassphrase']))
                                            Current password shown above - Leave blank to keep, or enter new password to change
                                        @else
                                            No password currently set - Enter a password (min 8 characters)
                                        @endif
                                    </p>
                                </div>

                                <!-- Auto Channel -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="auto_channel" value="0">
                                        <input type="checkbox" name="auto_channel" value="1"
                                               x-model="autoChannel"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Auto Channel</span>
                                    </label>
                                    <div x-show="!autoChannel" class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700">Manual Channel</label>
                                        <select name="channel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="36" {{ ($config['Channel'] ?? '') === '36' ? 'selected' : '' }}>36</option>
                                            <option value="40" {{ ($config['Channel'] ?? '') === '40' ? 'selected' : '' }}>40</option>
                                            <option value="44" {{ ($config['Channel'] ?? '') === '44' ? 'selected' : '' }}>44</option>
                                            <option value="48" {{ ($config['Channel'] ?? '') === '48' ? 'selected' : '' }}>48</option>
                                            <option value="149" {{ ($config['Channel'] ?? '') === '149' ? 'selected' : '' }}>149</option>
                                            <option value="153" {{ ($config['Channel'] ?? '') === '153' ? 'selected' : '' }}>153</option>
                                            <option value="157" {{ ($config['Channel'] ?? '') === '157' ? 'selected' : '' }}>157</option>
                                            <option value="161" {{ ($config['Channel'] ?? '161') === '161' ? 'selected' : '' }}>161</option>
                                            <option value="165" {{ ($config['Channel'] ?? '') === '165' ? 'selected' : '' }}>165</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Channel Bandwidth -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="auto_channel_bandwidth" value="0">
                                        <input type="checkbox" name="auto_channel_bandwidth" value="1"
                                               x-model="autoChannelBandwidth"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700">Auto Channel Bandwidth</span>
                                    </label>
                                    <div x-show="!autoChannelBandwidth" class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700">Channel Bandwidth</label>
                                        <select name="channel_bandwidth" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="20MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === '20MHz' ? 'selected' : '' }}>20 MHz</option>
                                            <option value="40MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === '40MHz' ? 'selected' : '' }}>40 MHz</option>
                                            <option value="80MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '80MHz') === '80MHz' ? 'selected' : '' }}>80 MHz</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- SSID Broadcast -->
                                <div class="flex items-center">
                                    <input type="hidden" name="ssid_broadcast" value="0">
                                    <input type="checkbox" name="ssid_broadcast" value="1" {{ ($config['SSIDAdvertisementEnabled'] ?? '1') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Broadcast SSID</span>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 space-y-2 border-t border-gray-200">
                                <div class="text-xs text-gray-600 text-center">
                                    <span class="font-medium">Status:</span> {{ $config['Status'] ?? 'Unknown' }}<br>
                                    <span class="font-medium">Standard:</span> {{ $config['Standard'] ?? '-' }}
                                </div>
                                <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
                </div>
            </div>
        </div>
        @endif

        @if(count($wlanConfigs) === 0)
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No WiFi Configuration Found</h3>
                <p class="mt-1 text-sm text-gray-500">Click "Query Device Info" to fetch WiFi parameters from the device.</p>
            </div>
        </div>
        @endif
    </div>

    <!-- Config Backups Tab -->
    <div x-show="activeTab === 'backups'" x-cloak x-data="{
        backups: [],
        loading: true,
        selectedBackup: null,
        showRestoreConfirm: false,
        selectedForComparison: [],
        showComparisonModal: false,
        comparisonData: null,
        showImportModal: false,
        importFile: null,
        importName: '',
        showEditMetadataModal: false,
        editingBackup: null,
        editTags: [],
        editNotes: '',
        newTag: '',
        filterTags: [],
        filterStarred: null,
        filterDateFrom: '',
        filterDateTo: '',
        showSelectiveRestoreModal: false,
        selectiveRestoreBackup: null,
        selectedParameters: [],
        parameterSearchQuery: '',

        // Categorize backups by retention type
        get initialBackups() {
            return this.backups.filter(b =>
                b.description && b.description.includes('first TR-069 connection')
            );
        },

        get userBackups() {
            return this.backups.filter(b =>
                !b.is_auto &&
                (!b.description || !b.description.includes('first TR-069 connection'))
            );
        },

        get autoBackups() {
            return this.backups.filter(b =>
                b.is_auto &&
                (!b.description || !b.description.includes('first TR-069 connection'))
            );
        },

        async loadBackups() {
            this.loading = true;
            try {
                const response = await fetch('/api/devices/{{ $device->id }}/backups');
                const data = await response.json();
                this.backups = data.backups;
            } catch (error) {
                console.error('Error loading backups:', error);
                alert('Error loading backups: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        async createBackup() {
            if (this.loading) return;

            const name = prompt('Enter a name for this backup (optional):');
            if (name === null) return; // User canceled

            this.loading = true;
            try {
                const response = await fetch('/api/devices/{{ $device->id }}/backups', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: name || undefined,
                        description: 'Manual backup created via UI'
                    })
                });

                const data = await response.json();
                if (response.ok) {
                    alert(data.message);
                    await this.loadBackups();
                } else {
                    alert('Error creating backup: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error creating backup:', error);
                alert('Error creating backup: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        confirmRestore(backup) {
            this.selectedBackup = backup;
            this.showRestoreConfirm = true;
        },

        async restoreBackup() {
            if (!this.selectedBackup) return;

            this.showRestoreConfirm = false;
            taskLoading = true;
            taskMessage = 'Restoring Configuration...';

            try {
                const response = await fetch(`/api/devices/{{ $device->id }}/backups/${this.selectedBackup.id}/restore`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();
                if (response.ok && data.task) {
                    startTaskTracking('Restoring Configuration...', data.task.id);
                } else {
                    taskLoading = false;
                    alert('Error restoring backup: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                taskLoading = false;
                console.error('Error restoring backup:', error);
                alert('Error restoring backup: ' + error.message);
            }
        },

        openSelectiveRestore(backup) {
            this.selectiveRestoreBackup = backup;
            this.selectedParameters = [];
            this.parameterSearchQuery = '';
            this.showSelectiveRestoreModal = true;
        },

        get filteredBackupParameters() {
            if (!this.selectiveRestoreBackup || !this.selectiveRestoreBackup.backup_data) return [];

            const query = this.parameterSearchQuery.toLowerCase();
            return Object.entries(this.selectiveRestoreBackup.backup_data)
                .filter(([name, data]) => {
                    // Filter by search query
                    if (query && !name.toLowerCase().includes(query)) {
                        return false;
                    }
                    // Only show writable parameters
                    return data.writable ?? false;
                })
                .map(([name, data]) => ({ name, ...data }))
                .sort((a, b) => a.name.localeCompare(b.name));
        },

        toggleParameter(paramName) {
            const index = this.selectedParameters.indexOf(paramName);
            if (index > -1) {
                this.selectedParameters.splice(index, 1);
            } else {
                this.selectedParameters.push(paramName);
            }
        },

        selectAllParameters() {
            this.selectedParameters = this.filteredBackupParameters.map(p => p.name);
        },

        deselectAllParameters() {
            this.selectedParameters = [];
        },

        async executeSelectiveRestore() {
            if (!this.selectiveRestoreBackup || this.selectedParameters.length === 0) return;

            this.showSelectiveRestoreModal = false;
            taskLoading = true;
            taskMessage = 'Restoring Selected Parameters...';

            try {
                const response = await fetch(`/api/devices/{{ $device->id }}/backups/${this.selectiveRestoreBackup.id}/restore`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        parameters: this.selectedParameters,
                        create_backup: true
                    })
                });

                const data = await response.json();
                if (response.ok && data.task) {
                    startTaskTracking('Restoring Selected Parameters...', data.task.id);
                } else {
                    taskLoading = false;
                    alert('Error restoring backup: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                taskLoading = false;
                console.error('Error restoring backup:', error);
                alert('Error restoring backup: ' + error.message);
            }
        },

        toggleCompareSelection(backup) {
            const index = this.selectedForComparison.findIndex(b => b.id === backup.id);
            if (index > -1) {
                this.selectedForComparison.splice(index, 1);
            } else {
                if (this.selectedForComparison.length < 2) {
                    this.selectedForComparison.push(backup);
                } else {
                    alert('You can only compare 2 backups at a time');
                }
            }
        },

        isSelectedForComparison(backup) {
            return this.selectedForComparison.some(b => b.id === backup.id);
        },

        async compareBackups() {
            if (this.selectedForComparison.length !== 2) return;

            const backup1Id = this.selectedForComparison[0].id;
            const backup2Id = this.selectedForComparison[1].id;

            try {
                const response = await fetch(`/api/devices/{{ $device->id }}/backups/${backup1Id}/compare/${backup2Id}`);
                const data = await response.json();
                if (response.ok) {
                    this.comparisonData = data;
                    this.showComparisonModal = true;
                } else {
                    alert('Error comparing backups: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error comparing backups:', error);
                alert('Error comparing backups: ' + error.message);
            }
        },

        clearCompareSelection() {
            this.selectedForComparison = [];
        },

        closeComparisonModal() {
            this.showComparisonModal = false;
            this.comparisonData = null;
        },

        openImportModal() {
            this.showImportModal = true;
            this.importFile = null;
            this.importName = '';
        },

        closeImportModal() {
            this.showImportModal = false;
            this.importFile = null;
            this.importName = '';
        },

        async importBackup() {
            if (!this.importFile) {
                alert('Please select a backup file to import');
                return;
            }

            this.loading = true;

            try {
                const formData = new FormData();
                formData.append('backup_file', this.importFile);
                if (this.importName) {
                    formData.append('name', this.importName);
                }

                const response = await fetch('/api/devices/{{ $device->id }}/backups/import', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                });

                const data = await response.json();
                if (response.ok) {
                    alert(data.message);
                    this.closeImportModal();
                    await this.loadBackups();
                } else {
                    alert('Error importing backup: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error importing backup:', error);
                alert('Error importing backup: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        downloadBackup(backupId) {
            window.location.href = `/api/devices/{{ $device->id }}/backups/${backupId}/download`;
        },

        async toggleStar(backup) {
            try {
                const response = await fetch(`/api/devices/{{ $device->id }}/backups/${backup.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        is_starred: !backup.is_starred
                    })
                });

                if (response.ok) {
                    backup.is_starred = !backup.is_starred;
                } else {
                    alert('Error updating star status');
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        },

        openEditMetadata(backup) {
            this.editingBackup = backup;
            this.editTags = [...(backup.tags || [])];
            this.editNotes = backup.notes || '';
            this.newTag = '';
            this.showEditMetadataModal = true;
        },

        closeEditMetadataModal() {
            this.showEditMetadataModal = false;
            this.editingBackup = null;
            this.editTags = [];
            this.editNotes = '';
            this.newTag = '';
        },

        addTag() {
            const tag = this.newTag.trim();
            if (tag && !this.editTags.includes(tag)) {
                this.editTags.push(tag);
                this.newTag = '';
            }
        },

        removeEditTag(tag) {
            this.editTags = this.editTags.filter(t => t !== tag);
        },

        async saveMetadata() {
            if (!this.editingBackup) return;

            try {
                const response = await fetch(`/api/devices/{{ $device->id }}/backups/${this.editingBackup.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        tags: this.editTags,
                        notes: this.editNotes
                    })
                });

                const data = await response.json();
                if (response.ok) {
                    this.editingBackup.tags = this.editTags;
                    this.editingBackup.notes = this.editNotes;
                    this.closeEditMetadataModal();
                } else {
                    alert('Error saving metadata: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error saving metadata:', error);
                alert('Error saving metadata: ' + error.message);
            }
        },

        async applyFilters() {
            this.loading = true;
            try {
                const params = new URLSearchParams();

                if (this.filterTags.length > 0) {
                    this.filterTags.forEach(tag => params.append('tags[]', tag));
                }

                if (this.filterStarred !== null) {
                    params.append('starred', this.filterStarred);
                }

                if (this.filterDateFrom) {
                    params.append('date_from', this.filterDateFrom);
                }

                if (this.filterDateTo) {
                    params.append('date_to', this.filterDateTo);
                }

                const queryString = params.toString();
                const url = `/api/devices/{{ $device->id }}/backups${queryString ? '?' + queryString : ''}`;

                const response = await fetch(url);
                const data = await response.json();
                this.backups = data.backups;
            } catch (error) {
                console.error('Error loading filtered backups:', error);
                alert('Error loading backups: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filterTags = [];
            this.filterStarred = null;
            this.filterDateFrom = '';
            this.filterDateTo = '';
            this.loadBackups();
        },

        toggleFilterTag(tag) {
            const index = this.filterTags.indexOf(tag);
            if (index > -1) {
                this.filterTags.splice(index, 1);
            } else {
                this.filterTags.push(tag);
            }
            this.applyFilters();
        },

        get allUniqueTags() {
            const tags = new Set();
            this.backups.forEach(backup => {
                if (backup.tags) {
                    backup.tags.forEach(tag => tags.add(tag));
                }
            });
            return Array.from(tags).sort();
        },

        init() {
            this.loadBackups();
        }
    }">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-gray-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Configuration Backups</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Backup and restore device configuration</p>
                </div>
                <div class="flex space-x-3">
                    <button x-show="selectedForComparison.length > 0" @click="clearCompareSelection()"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Selection
                    </button>
                    <button x-show="selectedForComparison.length === 2" @click="compareBackups()"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Compare Selected (2)
                    </button>
                    <button @click="openImportModal()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Import Backup
                    </button>
                    <button @click="createBackup()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Backup
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="px-4 py-3 bg-gray-100 border-t border-gray-200 flex flex-wrap items-center gap-3">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Filters:</span>
                </div>

                <!-- Starred Filter -->
                <button @click="filterStarred = filterStarred === true ? null : true; applyFilters()"
                        :class="filterStarred === true ? 'bg-yellow-100 text-yellow-800 border-yellow-300' : 'bg-white text-gray-700 border-gray-300'"
                        class="inline-flex items-center px-3 py-1.5 border rounded-md text-sm">
                    <svg class="w-4 h-4 mr-1.5" :class="filterStarred === true ? 'fill-yellow-500' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                    Starred Only
                </button>

                <!-- Tag Filters -->
                <template x-if="allUniqueTags.length > 0">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Tags:</span>
                        <template x-for="tag in allUniqueTags" :key="tag">
                            <button @click="toggleFilterTag(tag)"
                                    :class="filterTags.includes(tag) ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                                    class="inline-flex items-center px-2.5 py-1 border rounded-full text-xs font-medium"
                                    x-text="tag">
                            </button>
                        </template>
                    </div>
                </template>

                <!-- Date Range Filter -->
                <div class="flex items-center space-x-2">
                    <input type="date" x-model="filterDateFrom" @change="applyFilters()"
                           class="block w-36 px-2 py-1.5 text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           placeholder="From">
                    <span class="text-gray-500">to</span>
                    <input type="date" x-model="filterDateTo" @change="applyFilters()"
                           class="block w-36 px-2 py-1.5 text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           placeholder="To">
                </div>

                <!-- Clear Filters -->
                <button x-show="filterTags.length > 0 || filterStarred !== null || filterDateFrom || filterDateTo"
                        @click="clearFilters()"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Clear All
                </button>
            </div>

            <div class="border-t border-gray-200">
                <!-- Loading State -->
                <div x-show="loading" class="px-6 py-12 text-center">
                    <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Loading backups...</p>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && backups.length === 0" class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Backups Found</h3>
                    <p class="mt-1 text-sm text-gray-500">Create your first backup to preserve the current device configuration.</p>
                </div>

                <!-- Backups List - Grouped by Retention Type -->
                <div x-show="!loading && backups.length > 0">
                    <!-- Initial Backups (Protected) -->
                    <div x-show="initialBackups.length > 0" class="border-b border-gray-200">
                        <div class="px-6 py-3 bg-green-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    <h4 class="text-sm font-semibold text-green-900">Initial Backup</h4>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Protected - Never Deleted
                                    </span>
                                </div>
                                <span class="text-xs text-green-700" x-text="initialBackups.length + ' backup' + (initialBackups.length !== 1 ? 's' : '')"></span>
                            </div>
                        </div>
                        <template x-for="backup in initialBackups" :key="backup.id">
                            <div class="px-6 py-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <input type="checkbox" @click="toggleCompareSelection(backup)" :checked="isSelectedForComparison(backup)"
                                           class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded mr-4 flex-shrink-0">
                                    <div class="flex-1">
                                        <h5 class="text-sm font-medium text-gray-900" x-text="backup.name"></h5>
                                        <p class="mt-1 text-sm text-gray-500" x-text="backup.description"></p>
                                        <div class="mt-2 flex items-center text-xs text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span x-text="backup.created_at"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                <span x-text="backup.parameter_count + ' parameters'"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span x-text="backup.size"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <button @click="downloadBackup(backup.id)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                            Download
                                        </button>
                                        <button @click="openSelectiveRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 bg-white hover:bg-purple-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                            </svg>
                                            Selective
                                        </button>
                                        <button @click="confirmRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Restore All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- User Created Backups (90 Day Retention) -->
                    <div x-show="userBackups.length > 0" class="border-b border-gray-200">
                        <div class="px-6 py-3 bg-blue-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <h4 class="text-sm font-semibold text-blue-900">User Created Backups</h4>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        90 Day Retention
                                    </span>
                                </div>
                                <span class="text-xs text-blue-700" x-text="userBackups.length + ' backup' + (userBackups.length !== 1 ? 's' : '')"></span>
                            </div>
                        </div>
                        <template x-for="backup in userBackups" :key="backup.id">
                            <div class="px-6 py-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <input type="checkbox" @click="toggleCompareSelection(backup)" :checked="isSelectedForComparison(backup)"
                                           class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded mr-4 flex-shrink-0">
                                    <div class="flex-1">
                                        <h5 class="text-sm font-medium text-gray-900" x-text="backup.name"></h5>
                                        <p x-show="backup.description" class="mt-1 text-sm text-gray-500" x-text="backup.description"></p>
                                        <div class="mt-2 flex items-center text-xs text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span x-text="backup.created_at"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                <span x-text="backup.parameter_count + ' parameters'"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span x-text="backup.size"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <button @click="downloadBackup(backup.id)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                            Download
                                        </button>
                                        <button @click="openSelectiveRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 bg-white hover:bg-purple-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                            </svg>
                                            Selective
                                        </button>
                                        <button @click="confirmRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Restore All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Automated Backups (7 Day Retention) -->
                    <div x-show="autoBackups.length > 0">
                        <div class="px-6 py-3 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <h4 class="text-sm font-semibold text-gray-900">Automated Daily Backups</h4>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        7 Day Retention
                                    </span>
                                </div>
                                <span class="text-xs text-gray-700" x-text="autoBackups.length + ' backup' + (autoBackups.length !== 1 ? 's' : '')"></span>
                            </div>
                        </div>
                        <template x-for="backup in autoBackups" :key="backup.id">
                            <div class="px-6 py-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <input type="checkbox" @click="toggleCompareSelection(backup)" :checked="isSelectedForComparison(backup)"
                                           class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded mr-4 flex-shrink-0">
                                    <div class="flex-1">
                                        <h5 class="text-sm font-medium text-gray-900" x-text="backup.name"></h5>
                                        <p x-show="backup.description" class="mt-1 text-sm text-gray-500" x-text="backup.description"></p>
                                        <div class="mt-2 flex items-center text-xs text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span x-text="backup.created_at"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                <span x-text="backup.parameter_count + ' parameters'"></span>
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span x-text="backup.size"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <button @click="downloadBackup(backup.id)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                            Download
                                        </button>
                                        <button @click="openSelectiveRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 bg-white hover:bg-purple-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                            </svg>
                                            Selective
                                        </button>
                                        <button @click="confirmRestore(backup)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Restore All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Backup Modal -->
        <div x-show="showImportModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Import Backup</h3>
                    <button @click="closeImportModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Upload a previously exported backup file to restore it to this device.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Backup File (JSON)
                        </label>
                        <input type="file" accept=".json,application/json"
                               @change="importFile = $event.target.files[0]"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Backup Name (Optional)
                        </label>
                        <input type="text" x-model="importName" placeholder="Leave empty to use original name"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                </div>
                <div class="mt-6 flex space-x-3">
                    <button @click="closeImportModal()"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="importBackup()" :disabled="!importFile || loading"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Import
                    </button>
                </div>
            </div>
        </div>

        <!-- Selective Restore Modal -->
        <div x-show="showSelectiveRestoreModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-purple-50 border-b border-purple-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-purple-900">Selective Restore</h3>
                            <p class="text-sm text-purple-700 mt-1" x-show="selectiveRestoreBackup">
                                Choose specific parameters to restore from: <span x-text="selectiveRestoreBackup?.name"></span>
                            </p>
                        </div>
                        <button @click="showSelectiveRestoreModal = false" class="text-purple-400 hover:text-purple-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Search and Controls -->
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <!-- Search Box -->
                        <div class="flex-1">
                            <input type="text" x-model="parameterSearchQuery" placeholder="Search parameters..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm">
                        </div>
                        <!-- Select/Deselect All -->
                        <button @click="selectAllParameters()"
                                class="px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 rounded-md hover:bg-purple-200">
                            Select All
                        </button>
                        <button @click="deselectAllParameters()"
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Deselect All
                        </button>
                        <!-- Counter -->
                        <div class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md">
                            <span x-text="selectedParameters.length"></span> / <span x-text="filteredBackupParameters.length"></span> selected
                        </div>
                    </div>
                </div>

                <!-- Parameters List -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <template x-if="filteredBackupParameters.length === 0">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No writable parameters found</h3>
                            <p class="mt-1 text-sm text-gray-500">Try adjusting your search query</p>
                        </div>
                    </template>

                    <div class="space-y-2">
                        <template x-for="param in filteredBackupParameters" :key="param.name">
                            <label class="flex items-start p-3 bg-white border border-gray-200 rounded-md hover:bg-purple-50 hover:border-purple-300 cursor-pointer transition-colors">
                                <input type="checkbox"
                                       :checked="selectedParameters.includes(param.name)"
                                       @change="toggleParameter(param.name)"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded mt-1">
                                <div class="ml-3 flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-mono text-gray-900 break-all" x-text="param.name"></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
                                              x-text="param.type || 'string'"></span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600 font-mono">
                                        Value: <span x-text="param.value || '(empty)'"></span>
                                    </div>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <svg class="inline h-5 w-5 text-blue-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        A safety backup will be created automatically before restore
                    </div>
                    <div class="flex gap-3">
                        <button @click="showSelectiveRestoreModal = false"
                                class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button @click="executeSelectiveRestore()"
                                :disabled="selectedParameters.length === 0"
                                class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                            Restore <span x-text="selectedParameters.length"></span> Parameter<span x-show="selectedParameters.length !== 1">s</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Metadata Modal -->
        <div x-show="showEditMetadataModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-blue-50 border-b border-blue-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-blue-900">Edit Backup Metadata</h3>
                    <button @click="closeEditMetadataModal()" class="text-blue-400 hover:text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div x-show="editingBackup" class="space-y-6">
                        <!-- Backup Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-900" x-text="editingBackup?.name"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="editingBackup?.created_at"></div>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tags
                            </label>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <template x-for="tag in editTags" :key="tag">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <span x-text="tag"></span>
                                        <button @click="removeEditTag(tag)" class="ml-2 hover:text-blue-900">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                                <span x-show="editTags.length === 0" class="text-sm text-gray-400 italic">No tags</span>
                            </div>
                            <div class="flex space-x-2">
                                <input type="text" x-model="newTag" @keyup.enter="addTag()"
                                       placeholder="Add a tag..."
                                       class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <button @click="addTag()" :disabled="!newTag.trim()"
                                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Add
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Press Enter or click Add to create a new tag</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Notes
                            </label>
                            <textarea x-model="editNotes" rows="6"
                                      placeholder="Add notes about this backup..."
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
                            <p class="mt-2 text-xs text-gray-500">Add any relevant notes or comments about this backup</p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <button @click="closeEditMetadataModal()"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="saveMetadata()"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Restore Confirmation Modal -->
        <div x-show="showRestoreConfirm" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h3 class="ml-4 text-lg font-medium text-gray-900">Restore Configuration?</h3>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    This will restore the device configuration to the state saved in this backup. All writable parameters will be updated.
                </p>
                <p x-show="selectedBackup" class="text-sm font-medium text-gray-900 mb-4">
                    Backup: <span x-text="selectedBackup?.name"></span>
                </p>
                <div class="flex space-x-3">
                    <button @click="showRestoreConfirm = false"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="restoreBackup()"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                        Restore
                    </button>
                </div>
            </div>
        </div>

        <!-- Comparison Modal -->
        <div x-show="showComparisonModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-purple-50 border-b border-purple-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-purple-900">Backup Comparison</h3>
                        <p class="mt-1 text-sm text-purple-700">Comparing configuration differences between two backups</p>
                    </div>
                    <button @click="closeComparisonModal()" class="text-purple-400 hover:text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Backup Info Header -->
                <div x-show="comparisonData" class="px-6 py-4 bg-gray-50 border-b border-gray-200 grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <div class="text-xs font-semibold text-blue-900 uppercase tracking-wide mb-1">Backup 1 (Older)</div>
                        <div class="text-sm font-medium text-gray-900" x-text="comparisonData?.backup1.name"></div>
                        <div class="text-xs text-gray-600 mt-1" x-text="comparisonData?.backup1.created_at"></div>
                        <div class="text-xs text-gray-500 mt-1" x-text="comparisonData?.backup1.parameter_count + ' parameters'"></div>
                    </div>
                    <div class="bg-green-50 p-3 rounded-lg">
                        <div class="text-xs font-semibold text-green-900 uppercase tracking-wide mb-1">Backup 2 (Newer)</div>
                        <div class="text-sm font-medium text-gray-900" x-text="comparisonData?.backup2.name"></div>
                        <div class="text-xs text-gray-600 mt-1" x-text="comparisonData?.backup2.created_at"></div>
                        <div class="text-xs text-gray-500 mt-1" x-text="comparisonData?.backup2.parameter_count + ' parameters'"></div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div x-show="comparisonData" class="px-6 py-3 bg-white border-b border-gray-200">
                    <div class="flex items-center space-x-6 text-sm">
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <span x-text="comparisonData?.summary.added_count"></span> Added
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                <span x-text="comparisonData?.summary.removed_count"></span> Removed
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <span x-text="comparisonData?.summary.modified_count"></span> Modified
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <span x-text="comparisonData?.summary.unchanged_count"></span> Unchanged
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Changes Content (Scrollable) -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <template x-if="comparisonData">
                        <div class="space-y-6">
                            <!-- Modified Parameters -->
                            <div x-show="Object.keys(comparisonData.comparison.modified).length > 0">
                                <h4 class="text-sm font-semibold text-yellow-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Modified Parameters (<span x-text="Object.keys(comparisonData.comparison.modified).length"></span>)
                                </h4>
                                <div class="space-y-2">
                                    <template x-for="[name, param] in Object.entries(comparisonData.comparison.modified)" :key="name">
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                            <div class="text-xs font-mono text-gray-700 mb-2" x-text="name"></div>
                                            <div class="grid grid-cols-2 gap-3 text-sm">
                                                <div class="bg-red-50 border border-red-200 rounded px-3 py-2">
                                                    <div class="text-xs font-semibold text-red-900 uppercase mb-1">Old Value</div>
                                                    <div class="text-xs font-mono text-gray-800 break-all" x-text="param.old_value"></div>
                                                </div>
                                                <div class="bg-green-50 border border-green-200 rounded px-3 py-2">
                                                    <div class="text-xs font-semibold text-green-900 uppercase mb-1">New Value</div>
                                                    <div class="text-xs font-mono text-gray-800 break-all" x-text="param.new_value"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Added Parameters -->
                            <div x-show="Object.keys(comparisonData.comparison.added).length > 0">
                                <h4 class="text-sm font-semibold text-green-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Added Parameters (<span x-text="Object.keys(comparisonData.comparison.added).length"></span>)
                                </h4>
                                <div class="space-y-2">
                                    <template x-for="[name, param] in Object.entries(comparisonData.comparison.added)" :key="name">
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                            <div class="text-xs font-mono text-gray-700 mb-1" x-text="name"></div>
                                            <div class="text-xs font-mono text-gray-800 break-all" x-text="param.value"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Removed Parameters -->
                            <div x-show="Object.keys(comparisonData.comparison.removed).length > 0">
                                <h4 class="text-sm font-semibold text-red-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                    </svg>
                                    Removed Parameters (<span x-text="Object.keys(comparisonData.comparison.removed).length"></span>)
                                </h4>
                                <div class="space-y-2">
                                    <template x-for="[name, param] in Object.entries(comparisonData.comparison.removed)" :key="name">
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                            <div class="text-xs font-mono text-gray-700 mb-1" x-text="name"></div>
                                            <div class="text-xs font-mono text-gray-800 break-all" x-text="param.value"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- No Changes Message -->
                            <div x-show="comparisonData.summary.added_count === 0 && comparisonData.summary.removed_count === 0 && comparisonData.summary.modified_count === 0"
                                 class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Differences Found</h3>
                                <p class="mt-1 text-sm text-gray-500">These backups are identical</p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                    <button @click="closeComparisonModal()"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Port Forwarding Tab -->
    <div x-show="activeTab === 'ports'" x-cloak x-data="{
        portMappings: [],
        loading: true,
        showAddForm: false,
        newMapping: {
            description: '',
            protocol: 'TCP',
            external_port: '',
            internal_port: '',
            internal_client: ''
        },

        async loadPortMappings() {
            this.loading = true;
            try {
                const response = await fetch('/api/devices/{{ $device->id }}/port-mappings');
                const data = await response.json();
                this.portMappings = data.port_mappings;
            } catch (error) {
                console.error('Error loading port mappings:', error);
                alert('Error loading port mappings: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        async addPortMapping() {
            if (!this.newMapping.description || !this.newMapping.external_port ||
                !this.newMapping.internal_port || !this.newMapping.internal_client) {
                alert('Please fill in all fields');
                return;
            }

            taskLoading = true;
            taskMessage = 'Adding Port Forward...';

            try {
                const response = await fetch('/api/devices/{{ $device->id }}/port-mappings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.newMapping)
                });

                const data = await response.json();
                if (response.ok && data.task) {
                    this.showAddForm = false;
                    this.newMapping = { description: '', protocol: 'TCP', external_port: '', internal_port: '', internal_client: '' };
                    startTaskTracking('Adding Port Forward...', data.task.id);
                } else {
                    taskLoading = false;
                    alert('Error adding port forward: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                taskLoading = false;
                console.error('Error adding port mapping:', error);
                alert('Error adding port forward: ' + error.message);
            }
        },

        async deletePortMapping(instance) {
            if (!confirm('Are you sure you want to delete this port forward?')) {
                return;
            }

            taskLoading = true;
            taskMessage = 'Deleting Port Forward...';

            try {
                const response = await fetch('/api/devices/{{ $device->id }}/port-mappings', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ instance: instance })
                });

                const data = await response.json();
                if (response.ok && data.task) {
                    startTaskTracking('Deleting Port Forward...', data.task.id);
                } else {
                    taskLoading = false;
                    alert('Error deleting port forward: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                taskLoading = false;
                console.error('Error deleting port mapping:', error);
                alert('Error deleting port forward: ' + error.message);
            }
        },

        init() {
            this.loadPortMappings();
        }
    }">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-gray-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Port Forwarding (NAT)</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage port forwarding rules for this device</p>
                </div>
                <button @click="showAddForm = !showAddForm" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span x-text="showAddForm ? 'Cancel' : 'Add Port Forward'"></span>
                </button>
            </div>

            <!-- Add Form -->
            <div x-show="showAddForm" x-cloak class="border-t border-gray-200 px-6 py-4 bg-gray-50">
                <form @submit.prevent="addPortMapping()" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <input type="text" x-model="newMapping.description" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Protocol</label>
                        <select x-model="newMapping.protocol" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="TCP">TCP</option>
                            <option value="UDP">UDP</option>
                            <option value="Both">Both</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">External Port</label>
                        <input type="number" x-model="newMapping.external_port" min="1" max="65535" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Internal Port</label>
                        <input type="number" x-model="newMapping.internal_port" min="1" max="65535" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Internal IP Address</label>
                        <input type="text" x-model="newMapping.internal_client" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required
                               placeholder="192.168.1.100"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700">
                            Add Port Forward
                        </button>
                    </div>
                </form>
            </div>

            <div class="border-t border-gray-200">
                <!-- Loading State -->
                <div x-show="loading" class="px-6 py-12 text-center">
                    <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Loading port forwards...</p>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && portMappings.length === 0" class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Port Forwards</h3>
                    <p class="mt-1 text-sm text-gray-500">Add a port forward to allow external access to internal services.</p>
                </div>

                <!-- Port Mappings List -->
                <div x-show="!loading && portMappings.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Protocol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">External Port</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Internal Port</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Internal IP</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="mapping in portMappings" :key="mapping.instance">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="mapping.PortMappingDescription || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="mapping.PortMappingProtocol || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="mapping.ExternalPort || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="mapping.InternalPort || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="mapping.InternalClient || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="deletePortMapping(mapping.instance)"
                                                class="text-red-600 hover:text-red-900">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- WiFi Scan Tab -->
    <div x-show="activeTab === 'wifiscan'" class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6"
             x-data="{
                 scanning: false,
                 scanState: null,
                 scanResults: [],
                 lastUpdate: null,

                 async startScan() {
                     this.scanning = true;
                     this.scanState = 'Requested';
                     try {
                         const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                 'X-Background-Poll': 'true'
                             }
                         });
                         const data = await response.json();
                         if (response.ok) {
                             // Start polling for results
                             this.pollResults();
                         } else {
                             alert('Failed to start scan: ' + (data.message || 'Unknown error'));
                             this.scanning = false;
                         }
                     } catch (error) {
                         alert('Error starting scan: ' + error.message);
                         this.scanning = false;
                     }
                 },

                 async pollResults() {
                     const maxAttempts = 120; // Poll for up to 6 minutes (120 attempts × 3 seconds)
                     let attempts = 0;

                     const poll = async () => {
                         if (attempts++ >= maxAttempts) {
                             this.scanning = false;
                             this.scanState = 'Timeout - Device may require up to 5 minutes to check in';
                             return;
                         }

                         try {
                             const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan-results', {
                                 headers: { 'X-Background-Poll': 'true' }
                             });
                             const data = await response.json();

                             this.scanState = data.state || 'Waiting for device...';
                             this.lastUpdate = new Date().toLocaleTimeString();

                             if (data.state === 'Complete') {
                                 this.scanResults = data.results;
                                 this.scanning = false;
                             } else if (data.state === 'Error' || data.state === 'Error_Internal') {
                                 this.scanning = false;
                                 alert('Scan failed - device reported an error');
                             } else {
                                 // Continue polling every 3 seconds (reduced from 1 second to avoid flashing)
                                 setTimeout(poll, 3000);
                             }
                         } catch (error) {
                             console.error('Error polling results:', error);
                             this.scanning = false;
                         }
                     };

                     poll();
                 },

                 async refreshResults() {
                     try {
                         const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan-results');
                         const data = await response.json();
                         this.scanState = data.state;
                         this.scanResults = data.results;
                         this.lastUpdate = new Date().toLocaleTimeString();
                     } catch (error) {
                         console.error('Error refreshing results:', error);
                     }
                 },

                 getSignalStrengthColor(strength) {
                     const dbm = parseInt(strength);
                     if (dbm >= -50) return 'text-green-600 font-semibold';
                     if (dbm >= -70) return 'text-yellow-600 font-medium';
                     return 'text-red-600';
                 },

                 getSignalStrengthLabel(strength) {
                     const dbm = parseInt(strength);
                     if (dbm >= -50) return 'Excellent';
                     if (dbm >= -60) return 'Good';
                     if (dbm >= -70) return 'Fair';
                     return 'Weak';
                 },

                 getFriendlyRadioName(radioPath, frequencyBand) {
                     if (!radioPath) return 'N/A';

                     // Extract radio number from path
                     const parts = radioPath.split('Radio.');
                     let radioNum = '?';
                     if (parts.length > 1) {
                         const numPart = parts[1].split('.')[0];
                         if (numPart) radioNum = numPart;
                     }

                     // Use frequency band if available
                     if (frequencyBand) {
                         return frequencyBand + ' (Radio ' + radioNum + ')';
                     }

                     // Fallback to just radio number
                     return 'Radio ' + radioNum;
                 },

                 async copyToClipboard(text) {
                     try {
                         await navigator.clipboard.writeText(text);
                         // Optional: Show a brief success message
                         const event = new CustomEvent('toast', {
                             detail: { message: 'Copied to clipboard!', type: 'success' }
                         });
                         window.dispatchEvent(event);
                     } catch (err) {
                         console.error('Failed to copy:', err);
                     }
                 },

                 init() {
                     // Load existing results on init
                     this.refreshResults();
                 }
             }">

            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">WiFi Interference Scan</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Scan for nearby WiFi networks to identify interference and channel congestion
                    </p>
                    <div x-show="lastUpdate" class="mt-1 text-xs text-gray-400">
                        Last updated: <span x-text="lastUpdate"></span>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button @click="refreshResults()"
                            :disabled="scanning"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                    <button @click="startScan()"
                            :disabled="scanning"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg x-show="!scanning" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <svg x-show="scanning" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="scanning ? 'Scanning...' : 'Start Scan'"></span>
                    </button>
                </div>
            </div>

            <!-- Scan Status -->
            <div x-show="scanState" class="mb-4 p-4 rounded-md"
                 :class="{
                     'bg-blue-50 text-blue-700': scanState === 'Requested' || scanState === 'InProgress',
                     'bg-green-50 text-green-700': scanState === 'Complete',
                     'bg-red-50 text-red-700': scanState === 'Error' || scanState === 'Timeout'
                 }">
                <p class="text-sm font-medium">
                    Status: <span x-text="scanState"></span>
                </p>
            </div>

            <!-- Results Table -->
            <div x-show="scanResults.length > 0" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    SSID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    BSSID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Radio
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Channel
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Signal Strength
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Security
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Mode
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="result in scanResults" :key="result.instance">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <span x-text="result.SSID || '(Hidden Network)'"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        <span x-text="result.BSSID || 'N/A'"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center space-x-2 group relative">
                                            <span x-text="getFriendlyRadioName(result.Radio, result.OperatingFrequencyBand)"></span>
                                            <button
                                                @click="copyToClipboard(result.Radio)"
                                                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600"
                                                :title="result.Radio"
                                                type="button">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            </button>
                                            <!-- Tooltip -->
                                            <div class="hidden group-hover:block absolute left-0 top-full mt-1 z-50 bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                <span x-text="result.Radio"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span x-text="result.Channel || 'N/A'"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center space-x-2">
                                            <span :class="getSignalStrengthColor(result.SignalStrength)" x-text="result.SignalStrength + ' dBm'"></span>
                                            <span class="text-xs text-gray-400" x-text="'(' + getSignalStrengthLabel(result.SignalStrength) + ')'"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span x-text="result.SecurityModeEnabled || 'N/A'"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span x-text="result.Mode || 'N/A'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div x-show="scanResults.length === 0 && !scanning" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No scan results</h3>
                <p class="mt-1 text-sm text-gray-500">Click "Start Scan" to scan for nearby WiFi networks</p>
            </div>
        </div>
    </div>

    <!-- Speed Test Results Tab -->
    <div x-show="activeTab === 'speedtest'" x-cloak class="space-y-6">
        @php
            // Get latest completed speed test results
            $latestDownload = $device->tasks()
                ->where('task_type', 'download_diagnostics')
                ->where('status', 'completed')
                ->whereNotNull('result')
                ->latest()
                ->first();

            $latestUpload = $device->tasks()
                ->where('task_type', 'upload_diagnostics')
                ->where('status', 'completed')
                ->whereNotNull('result')
                ->latest()
                ->first();

            // Helper function to calculate speed
            $calculateSpeed = function($bytes, $bomTime, $eomTime) {
                try {
                    $start = new \Carbon\Carbon($bomTime);
                    $end = new \Carbon\Carbon($eomTime);
                    $duration = $end->diffInSeconds($start);

                    if ($duration <= 0) return null;

                    $bytesPerSecond = (int)$bytes / $duration;
                    $mbps = ($bytesPerSecond * 8) / 1000000; // Convert to Mbps

                    return round($mbps, 2);
                } catch (\Exception $e) {
                    return null;
                }
            };
        @endphp

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">TR-143 Speed Test Results</h3>
                <p class="mt-1 text-sm text-gray-500">Latest download and upload diagnostic test results</p>
            </div>

            <div class="px-4 py-5 sm:p-6 space-y-6">
                @if($latestDownload || $latestUpload)
                    <!-- Download Test Results -->
                    @if($latestDownload && isset($latestDownload->result['InternetGatewayDevice.DownloadDiagnostics.DiagnosticsState']) || isset($latestDownload->result['Device.IP.Diagnostics.DownloadDiagnostics.DiagnosticsState']))
                        @php
                            $prefix = isset($latestDownload->result['InternetGatewayDevice.DownloadDiagnostics.DiagnosticsState'])
                                ? 'InternetGatewayDevice.DownloadDiagnostics'
                                : 'Device.IP.Diagnostics.DownloadDiagnostics';

                            $dlState = $latestDownload->result["{$prefix}.DiagnosticsState"]['value'] ?? 'Unknown';
                            $dlBytes = $latestDownload->result["{$prefix}.TestBytesReceived"]['value'] ?? 0;
                            $dlBOM = $latestDownload->result["{$prefix}.BOMTime"]['value'] ?? null;
                            $dlEOM = $latestDownload->result["{$prefix}.EOMTime"]['value'] ?? null;
                            $dlSpeed = $calculateSpeed($dlBytes, $dlBOM, $dlEOM);
                        @endphp

                        <div class="border rounded-lg p-4 bg-blue-50">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-blue-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                    </svg>
                                    Download Test
                                </h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $dlState }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Download Speed</p>
                                    @if($dlSpeed)
                                        <p class="text-2xl font-bold text-blue-900">{{ $dlSpeed }} <span class="text-sm font-normal">Mbps</span></p>
                                    @else
                                        <p class="text-sm text-gray-500">Unable to calculate</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Data Received</p>
                                    <p class="text-lg font-semibold text-blue-900">{{ round($dlBytes / 1048576, 2) }} MB</p>
                                </div>
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Test Date</p>
                                    <p class="text-sm text-blue-900">{{ $latestDownload->updated_at->format('M d, Y H:i:s') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Upload Test Results -->
                    @if($latestUpload && isset($latestUpload->result['InternetGatewayDevice.UploadDiagnostics.DiagnosticsState']) || isset($latestUpload->result['Device.IP.Diagnostics.UploadDiagnostics.DiagnosticsState']))
                        @php
                            $prefix = isset($latestUpload->result['InternetGatewayDevice.UploadDiagnostics.DiagnosticsState'])
                                ? 'InternetGatewayDevice.UploadDiagnostics'
                                : 'Device.IP.Diagnostics.UploadDiagnostics';

                            $ulState = $latestUpload->result["{$prefix}.DiagnosticsState"]['value'] ?? 'Unknown';
                            $ulBytes = $latestUpload->result["{$prefix}.TestBytesSent"]['value'] ?? 0;
                            $ulBOM = $latestUpload->result["{$prefix}.BOMTime"]['value'] ?? null;
                            $ulEOM = $latestUpload->result["{$prefix}.EOMTime"]['value'] ?? null;
                            $ulSpeed = $calculateSpeed($ulBytes, $ulBOM, $ulEOM);
                        @endphp

                        <div class="border rounded-lg p-4 bg-green-50">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-green-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    Upload Test
                                </h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $ulState }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-sm text-green-700 font-medium">Upload Speed</p>
                                    @if($ulSpeed)
                                        <p class="text-2xl font-bold text-green-900">{{ $ulSpeed }} <span class="text-sm font-normal">Mbps</span></p>
                                    @else
                                        <p class="text-sm text-gray-500">Unable to calculate</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm text-green-700 font-medium">Data Sent</p>
                                    <p class="text-lg font-semibold text-green-900">{{ round($ulBytes / 1048576, 2) }} MB</p>
                                </div>
                                <div>
                                    <p class="text-sm text-green-700 font-medium">Test Date</p>
                                    <p class="text-sm text-green-900">{{ $latestUpload->updated_at->format('M d, Y H:i:s') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <!-- No Results -->
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No speed test results</h3>
                        <p class="mt-1 text-sm text-gray-500">Run a speed test from the Dashboard tab to see results here</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Templates Tab -->
    <div x-show="activeTab === 'templates'" x-cloak x-data="{
        templates: [],
        loading: true,
        selectedCategory: 'all',
        showCreateModal: false,
        showApplyModal: false,
        showEditModal: false,
        selectedTemplate: null,
        createForm: {
            name: '',
            description: '',
            category: 'general',
            source_type: 'backup',
            source_id: null,
            tags: [],
            parameter_patterns: [],
            device_model_filter: '',
            strip_device_specific: true
        },
        applyForm: {
            device_ids: [],
            merge_strategy: 'merge',
            create_backup: true
        },
        newTag: '',
        newPattern: '',

        async init() {
            await this.loadTemplates();
        },

        async loadTemplates() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.selectedCategory !== 'all') {
                    params.append('category', this.selectedCategory);
                }

                const response = await fetch('/api/templates?' + params);
                const data = await response.json();
                this.templates = data.templates;
            } catch (error) {
                console.error('Error loading templates:', error);
                window.showToast('Failed to load templates', 'error');
            } finally {
                this.loading = false;
            }
        },

        openCreateModal(sourceType = 'device') {
            this.createForm = {
                name: '',
                description: '',
                category: 'general',
                source_type: sourceType,
                source_id: sourceType === 'device' ? device.id : null,
                tags: [],
                parameter_patterns: [],
                device_model_filter: '',
                strip_device_specific: true
            };
            this.showCreateModal = true;
        },

        async createTemplate() {
            try {
                const response = await fetch('/api/templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(this.createForm)
                });

                if (!response.ok) throw new Error('Failed to create template');

                const data = await response.json();
                window.showToast(data.message, 'success');
                this.showCreateModal = false;
                await this.loadTemplates();
            } catch (error) {
                console.error('Error creating template:', error);
                window.showToast('Failed to create template', 'error');
            }
        },

        openApplyModal(template) {
            this.selectedTemplate = template;
            this.applyForm = {
                device_ids: [device.id],
                merge_strategy: 'merge',
                create_backup: true
            };
            this.showApplyModal = true;
        },

        async applyTemplate() {
            try {
                const response = await fetch(`/api/templates/${this.selectedTemplate.id}/apply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(this.applyForm)
                });

                if (!response.ok) throw new Error('Failed to apply template');

                const data = await response.json();
                window.showToast(data.message, 'success');
                this.showApplyModal = false;
            } catch (error) {
                console.error('Error applying template:', error);
                window.showToast('Failed to apply template', 'error');
            }
        },

        async deleteTemplate(templateId) {
            if (!confirm('Are you sure you want to delete this template?')) return;

            try {
                const response = await fetch(`/api/templates/${templateId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to delete template');

                const data = await response.json();
                window.showToast(data.message, 'success');
                await this.loadTemplates();
            } catch (error) {
                console.error('Error deleting template:', error);
                window.showToast('Failed to delete template', 'error');
            }
        },

        addTag() {
            const tag = this.newTag.trim();
            if (tag && !this.createForm.tags.includes(tag)) {
                this.createForm.tags.push(tag);
                this.newTag = '';
            }
        },

        removeTag(tag) {
            this.createForm.tags = this.createForm.tags.filter(t => t !== tag);
        },

        addPattern() {
            const pattern = this.newPattern.trim();
            if (pattern && !this.createForm.parameter_patterns.includes(pattern)) {
                this.createForm.parameter_patterns.push(pattern);
                this.newPattern = '';
            }
        },

        removePattern(pattern) {
            this.createForm.parameter_patterns = this.createForm.parameter_patterns.filter(p => p !== pattern);
        },

        get categoryTemplates() {
            if (this.selectedCategory === 'all') return this.templates;
            return this.templates.filter(t => t.category === this.selectedCategory);
        }
    }" class="space-y-6">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Configuration Templates</h3>
                        <p class="text-sm text-gray-500 mt-1">Reusable configuration templates for deploying settings across multiple devices</p>
                    </div>
                    <button @click="openCreateModal('device')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Template
                    </button>
                </div>
            </div>

            <!-- Category Filters -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <button @click="selectedCategory = 'all'; loadTemplates()"
                            :class="selectedCategory === 'all' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        All Templates
                    </button>
                    <button @click="selectedCategory = 'wifi'; loadTemplates()"
                            :class="selectedCategory === 'wifi' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        WiFi
                    </button>
                    <button @click="selectedCategory = 'port_forwarding'; loadTemplates()"
                            :class="selectedCategory === 'port_forwarding' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        Port Forwarding
                    </button>
                    <button @click="selectedCategory = 'security'; loadTemplates()"
                            :class="selectedCategory === 'security' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        Security
                    </button>
                    <button @click="selectedCategory = 'diagnostics'; loadTemplates()"
                            :class="selectedCategory === 'diagnostics' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        Diagnostics
                    </button>
                    <button @click="selectedCategory = 'general'; loadTemplates()"
                            :class="selectedCategory === 'general' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white text-gray-700 border-gray-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50">
                        General
                    </button>
                </div>
            </div>

            <!-- Templates List -->
            <div class="divide-y divide-gray-200">
                <template x-if="loading">
                    <div class="px-6 py-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-sm text-gray-500">Loading templates...</p>
                    </div>
                </template>

                <template x-if="!loading && categoryTemplates.length === 0">
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No templates found</h3>
                        <p class="mt-1 text-sm text-gray-500">Create a template to reuse configurations across devices</p>
                    </div>
                </template>

                <template x-for="template in categoryTemplates" :key="template.id">
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <h4 class="text-base font-semibold text-gray-900" x-text="template.name"></h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                          x-text="template.category.replace('_', ' ')"></span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600" x-text="template.description"></p>

                                <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                                    <span x-text="`${template.parameter_count} parameters`"></span>
                                    <span x-text="template.size"></span>
                                    <span x-show="template.source_device" x-text="`From: ${template.source_device?.serial || ''}`"></span>
                                    <span x-text="`Created: ${new Date(template.created_at).toLocaleDateString()}`"></span>
                                </div>

                                <div x-show="template.tags && template.tags.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                                    <template x-for="tag in template.tags">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
                                              x-text="tag"></span>
                                    </template>
                                </div>
                            </div>

                            <div class="ml-4 flex-shrink-0 flex items-center gap-2">
                                <button @click="openApplyModal(template)"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Apply to Device
                                </button>
                                <button @click="deleteTemplate(template.id)"
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Create Template Modal -->
        <div x-show="showCreateModal"
             x-cloak
             class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
             @click.self="showCreateModal = false">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto m-4">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Create Configuration Template</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <!-- Template Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template Name *</label>
                        <input type="text" x-model="createForm.name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., Standard WiFi Configuration">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea x-model="createForm.description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Describe what this template configures..."></textarea>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select x-model="createForm.category"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="general">General</option>
                            <option value="wifi">WiFi</option>
                            <option value="port_forwarding">Port Forwarding</option>
                            <option value="security">Security</option>
                            <option value="diagnostics">Diagnostics</option>
                        </select>
                    </div>

                    <!-- Parameter Patterns -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Parameter Patterns (Optional)</label>
                        <p class="text-xs text-gray-500 mb-2">Specify parameter patterns to include. Use * as wildcard. Leave empty to include all.</p>
                        <div class="flex gap-2 mb-2">
                            <input type="text" x-model="newPattern" @keyup.enter="addPattern()"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., InternetGatewayDevice.LANDevice.*.WLANConfiguration.*">
                            <button @click="addPattern()"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Add
                            </button>
                        </div>
                        <div x-show="createForm.parameter_patterns.length > 0" class="flex flex-wrap gap-2">
                            <template x-for="pattern in createForm.parameter_patterns">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                    <span x-text="pattern" class="mr-2"></span>
                                    <button @click="removePattern(pattern)" class="hover:text-blue-900">×</button>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                        <div class="flex gap-2 mb-2">
                            <input type="text" x-model="newTag" @keyup.enter="addTag()"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Add a tag...">
                            <button @click="addTag()"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Add
                            </button>
                        </div>
                        <div x-show="createForm.tags.length > 0" class="flex flex-wrap gap-2">
                            <template x-for="tag in createForm.tags">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                                    <span x-text="tag" class="mr-2"></span>
                                    <button @click="removeTag(tag)" class="hover:text-gray-900">×</button>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Device Model Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Device Model Filter (Optional)</label>
                        <input type="text" x-model="createForm.device_model_filter"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Leave empty to apply to all models">
                    </div>

                    <!-- Strip Device-Specific Values -->
                    <div class="flex items-center">
                        <input type="checkbox" x-model="createForm.strip_device_specific"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label class="ml-2 block text-sm text-gray-700">
                            Strip device-specific values (MAC addresses, serial numbers, etc.)
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button @click="showCreateModal = false"
                            class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="createTemplate()"
                            :disabled="!createForm.name || !createForm.category"
                            class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Create Template
                    </button>
                </div>
            </div>
        </div>

        <!-- Apply Template Modal -->
        <div x-show="showApplyModal"
             x-cloak
             class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
             @click.self="showApplyModal = false">
            <div class="bg-white rounded-lg shadow-xl max-w-xl w-full m-4">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Apply Template</h3>
                        <button @click="showApplyModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-800">Applying template: <span x-text="selectedTemplate?.name"></span></p>
                                <p class="text-sm text-blue-700 mt-1" x-text="`This will apply ${selectedTemplate?.parameter_count || 0} parameters to this device`"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Create Backup Option -->
                    <div class="flex items-center">
                        <input type="checkbox" x-model="applyForm.create_backup"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label class="ml-2 block text-sm text-gray-700">
                            Create backup before applying template (recommended)
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button @click="showApplyModal = false"
                            class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="applyTemplate()"
                            class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        Apply Template
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Card (Outside Alpine component to avoid reactivity flashing) -->
<!-- OLD Progress Card - Replaced by Task Manager Component -->
<!-- <div id="progressCard" style="display: none;" class="fixed top-4 right-4 z-50">
    ... removed for brevity ...
</div> -->

@endsection
