@extends('layouts.app')

@section('title', $device->id . ' - Device Details')

@section('content')
<div class="space-y-6" x-data="{
    activeTab: localStorage.getItem('deviceActiveTab_{{ $device->id }}') || 'dashboard',
    taskLoading: false,
    taskMessage: '',
    taskId: null,
    elapsedTime: 0,
    timerInterval: null,

    init() {
        // Watch for tab changes and save to localStorage
        this.$watch('activeTab', value => {
            localStorage.setItem('deviceActiveTab_{{ $device->id }}', value);
        });
    },

    startTaskTracking(message, taskId) {
        this.taskLoading = true;
        this.taskMessage = message;
        this.taskId = taskId;
        this.elapsedTime = 0;

        // Start count-up timer
        this.timerInterval = setInterval(() => {
            this.elapsedTime++;
        }, 1000);

        // Start polling for task completion
        this.pollTaskStatus();
    },

    async pollTaskStatus() {
        if (!this.taskId) return;

        try {
            const response = await fetch('/api/devices/{{ $device->id }}/tasks');
            const data = await response.json();

            // Find our task
            const task = data.find(t => t.id === this.taskId);

            if (task && (task.status === 'completed' || task.status === 'failed')) {
                // Task is done - stop timer and reload
                clearInterval(this.timerInterval);
                this.taskLoading = false;

                // Wait a moment to show completion, then reload
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                // Keep polling every 2 seconds
                setTimeout(() => this.pollTaskStatus(), 2000);
            }
        } catch (error) {
            console.error('Error polling task status:', error);
            // Retry after 2 seconds
            setTimeout(() => this.pollTaskStatus(), 2000);
        }
    },

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }
}">
    <!-- Loading Overlay -->
    <div x-show="taskLoading" x-cloak class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
            <div class="flex flex-col items-center">
                <!-- Spinner -->
                <svg class="animate-spin h-16 w-16 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <!-- Message -->
                <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="taskMessage"></h3>

                <!-- Timer -->
                <div class="flex items-center text-3xl font-mono text-blue-600 mb-4">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span x-text="formatTime(elapsedTime)"></span>
                </div>

                <!-- Status text -->
                <p class="text-sm text-gray-500 text-center">
                    Waiting for device to execute task...<br>
                    <span class="text-xs">The page will refresh automatically when complete.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Failed Tasks Alert -->
    @php
        $recentFailedTasks = $tasks->where('status', 'failed')->where('created_at', '>', now()->subHours(24))->take(3);
    @endphp
    @if($recentFailedTasks->isNotEmpty())
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4" x-data="{ show: true }" x-show="show" x-cloak>
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-red-800">
                        {{ $recentFailedTasks->count() }} task(s) failed in the last 24 hours
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($recentFailedTasks as $failedTask)
                                <li>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $failedTask->task_type)) }}</strong>
                                    - {{ $failedTask->error ?? 'Unknown error' }}
                                    <span class="text-xs text-red-600">({{ $failedTask->created_at->diffForHumans() }})</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="ml-auto pl-3">
                    <button @click="show = false" class="inline-flex text-red-400 hover:text-red-600">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Header -->
    <div>
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                {{ $device->id }}
            </h2>
            <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    @if($device->online)
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Online</span>
                    @else
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Offline</span>
                    @endif
                </div>
                <div class="mt-2 flex items-center text-sm text-gray-500">
                    Last Inform: {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}
                </div>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2" x-show="activeTab !== 'dashboard'" x-cloak>
            <!-- Connect Now -->
            <form action="/api/devices/{{ $device->id }}/connection-request" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Connect Now
                </button>
            </form>

            <!-- Query Device Info -->
            <form action="/api/devices/{{ $device->id }}/query" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Query Device Info
                </button>
            </form>

            <!-- Reboot Device -->
            <form action="/api/devices/{{ $device->id }}/reboot" method="POST" onsubmit="return confirm('Are you sure you want to reboot this device?');">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Reboot
                </button>
            </form>

            <!-- Factory Reset -->
            <form action="/api/devices/{{ $device->id }}/factory-reset" method="POST" onsubmit="return confirm('⚠️ WARNING: This will erase ALL device settings and data!\n\nAre you absolutely sure you want to factory reset this device?');">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                    Factory Reset
                </button>
            </form>

            <!-- Upgrade Firmware -->
            <form action="/api/devices/{{ $device->id }}/firmware-upgrade" method="POST" onsubmit="return confirm('Are you sure you want to upgrade the firmware on this device?');">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                    Upgrade Firmware
                </button>
            </form>

            <!-- Ping Test -->
            <form action="/api/devices/{{ $device->id }}/ping-test" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-700">
                    Ping Test
                </button>
            </form>

            <!-- Trace Route Test -->
            <form action="/api/devices/{{ $device->id }}/traceroute-test" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700">
                    Trace Route
                </button>
            </form>
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
        </nav>
    </div>

    <!-- Dashboard Tab -->
    <div x-show="activeTab === 'dashboard'" x-cloak>
        <!-- Two-Column Layout: Device Info & Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Left Column: Device Information Summary -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Device Information</h3>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->manufacturer ?? '-' }}</dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Model</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->product_class ?? '-' }}</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">{{ $device->serial_number ?? '-' }}</dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Software Version</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">{{ $device->software_version ?? '-' }}</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Hardware Version</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">{{ $device->hardware_version ?? '-' }}</dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Uptime</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Quick Actions</h3>
                </div>
                <div class="border-t border-gray-200 p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Query Device Info -->
                        <form action="/api/devices/{{ $device->id }}/query" method="POST">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Query Device
                            </button>
                        </form>

                        <!-- Connect Now -->
                        <form action="/api/devices/{{ $device->id }}/connection-request" method="POST">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                Connect Now
                            </button>
                        </form>

                        <!-- Reboot -->
                        <form action="/api/devices/{{ $device->id }}/reboot" method="POST" onsubmit="return confirm('Are you sure you want to reboot this device?');">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                Reboot
                            </button>
                        </form>

                        <!-- Ping Test -->
                        <form action="/api/devices/{{ $device->id }}/ping-test" method="POST">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-700">
                                Ping Test
                            </button>
                        </form>

                        <!-- Trace Route -->
                        <form action="/api/devices/{{ $device->id }}/traceroute-test" method="POST">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700">
                                Trace Route
                            </button>
                        </form>

                        <!-- Firmware Upgrade -->
                        <form action="/api/devices/{{ $device->id }}/firmware-upgrade" method="POST" onsubmit="return confirm('Are you sure you want to upgrade the firmware?');">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                Upgrade Firmware
                            </button>
                        </form>

                        <!-- Factory Reset -->
                        <form action="/api/devices/{{ $device->id }}/factory-reset" method="POST" onsubmit="return confirm('⚠️ WARNING: This will erase ALL device settings!\n\nAre you sure?');">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                                Factory Reset
                            </button>
                        </form>

                        <!-- Refresh Troubleshooting (2-column span) -->
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
                        }" class="col-span-2">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Refresh Troubleshooting Info
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Internet (WAN)</h3>
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
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                @if($status === 'Connected' || $status === 'Up')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $status }}</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">{{ $status }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">External IP Address</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Default Gateway</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DNS Servers</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono text-xs">
                                {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- LAN Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-green-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">LAN</h3>
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
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Subnet Mask</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                                {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DHCP Server</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                @if($dhcpEnabled === 'true' || $dhcpEnabled === '1')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Disabled</span>
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">DHCP Range</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono text-xs">
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
                <h3 class="text-lg leading-6 font-medium text-gray-900">WiFi Networks</h3>
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
                <h3 class="text-lg leading-6 font-medium text-gray-900">Connected Devices</h3>
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
                                    if ($dashWifiData) {
                                        $dashSignalStrength = $dashWifiData['SignalStrength'] ?? null;
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
                                    }

                                    // Get rates
                                    $dashDownRate = $dashWifiData['LastDataDownlinkRate'] ?? null;

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
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Tasks</h3>
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
            <h3 class="text-lg leading-6 font-medium text-gray-900">Device Information</h3>
        </div>
        <div class="border-t border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Device ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->id }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->manufacturer ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">OUI</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->oui ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Product Class</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->product_class ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->serial_number ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Software Version</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->software_version ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Hardware Version</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->hardware_version ?? '-' }}</dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $device->ip_address ?? '-' }}</dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Data Model</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $device->getDataModel() }}
                        </span>
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Connection Request URL</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 break-all">{{ $device->connection_request_url ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Parameters Tab -->
    <div x-show="activeTab === 'parameters'" x-cloak class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Device Parameters</h3>
            <p class="mt-1 text-sm text-gray-500">All parameters stored for this device</p>
        </div>
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
        @if($parameters->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $parameters->links() }}
        </div>
        @endif
    </div>

    <!-- Tasks Tab -->
    <div x-show="activeTab === 'tasks'" x-cloak class="bg-white shadow rounded-lg overflow-hidden" x-data="{ expandedTask: null }">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Device Tasks</h3>
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
            <h3 class="text-lg leading-6 font-medium text-gray-900">CWMP Sessions</h3>
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
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
                <h3 class="text-lg leading-6 font-medium text-gray-900">WAN Information</h3>
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
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
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
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Default Gateway</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DNS Servers</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">MAC Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.MACAddress") : $getExactParam("{$wanPrefix}.MACAddress") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Uptime</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
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
                <h3 class="text-lg leading-6 font-medium text-gray-900">LAN Information</h3>
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
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                        </dd>
                    </div>

                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Subnet Mask</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DHCP Server</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
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
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                        </dd>
                    </div>

                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">DHCP End Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 3. WiFi Radio Status -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-purple-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">WiFi Radio Status</h3>
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
                <h3 class="text-lg leading-6 font-medium text-gray-900">Connected Devices</h3>
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
                                    if ($wifiData) {
                                        $signalStrength = $wifiData['SignalStrength'] ?? null;
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
                                    }

                                    // Get rates
                                    $downRate = $wifiData['LastDataDownlinkRate'] ?? null;
                                    $upRate = $wifiData['LastDataUplinkRate'] ?? null;

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
                        <p class="text-sm text-gray-500">No connected devices found. Click "Query Device Info" to fetch host table information.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 5. ACS Event Log -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-red-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">ACS Event Log</h3>
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
                        <h3 class="text-lg leading-6 font-medium text-gray-900">2.4GHz Networks</h3>
                        <p class="mt-1 text-sm text-gray-600">Wireless SSIDs on 2.4GHz band (instances 1-8)</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-gray-700">2.4GHz Radio:</span>
                        <button @click="async () => {
                            radio24GhzEnabled = !radio24GhzEnabled;
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
                                    startTaskTracking((radio24GhzEnabled ? 'Enabling' : 'Disabling') + ' 2.4GHz Radio...', result.task.id);
                                } else {
                                    alert('Radio toggled, but no task ID returned');
                                }
                            } catch (error) {
                                alert('Error: ' + error);
                                radio24GhzEnabled = !radio24GhzEnabled;
                            }
                        }
                        " :class="radio24GhzEnabled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'"
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
                            for (let [key, value] of formData.entries()) {
                                if (key !== '_token') {
                                    // Convert checkbox values to boolean
                                    if (e.target[key].type === 'checkbox') {
                                        data[key] = value === '1';
                                    } else {
                                        data[key] = value || undefined;
                                    }
                                }
                            }

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
                                    alert('Configuration updated, but no task ID returned');
                                }
                            } catch (error) {
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
                                <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
                        <h3 class="text-lg leading-6 font-medium text-gray-900">5GHz Networks</h3>
                        <p class="mt-1 text-sm text-gray-600">Wireless SSIDs on 5GHz band (instances 9-16)</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-gray-700">5GHz Radio:</span>
                        <button @click="async () => {
                            radio5GhzEnabled = !radio5GhzEnabled;
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
                                    startTaskTracking((radio5GhzEnabled ? 'Enabling' : 'Disabling') + ' 5GHz Radio...', result.task.id);
                                } else {
                                    alert('Radio toggled, but no task ID returned');
                                }
                            } catch (error) {
                                alert('Error: ' + error);
                                radio5GhzEnabled = !radio5GhzEnabled;
                            }
                        }
                        " :class="radio5GhzEnabled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'"
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
                            for (let [key, value] of formData.entries()) {
                                if (key !== '_token') {
                                    // Convert checkbox values to boolean
                                    if (e.target[key].type === 'checkbox') {
                                        data[key] = value === '1';
                                    } else {
                                        data[key] = value || undefined;
                                    }
                                }
                            }

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
                                    alert('Configuration updated, but no task ID returned');
                                }
                            } catch (error) {
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
                                <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
</div>
@endsection
