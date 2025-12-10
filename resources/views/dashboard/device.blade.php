@extends('layouts.app')

@section('title', $device->id . ' - Device Details')

@section('content')
{{-- Task Manager Component --}}
@include('components.task-manager', ['deviceId' => $device->id])

@php
    $theme = session('theme', 'standard');
    $themeConfig = config("themes.{$theme}");
    $colors = $themeConfig['colors'];
    $useColorful = $themeConfig['use_colorful_buttons'] ?? false;
@endphp
<div class="space-y-6" x-data="{
    activeTab: (() => {
        const saved = localStorage.getItem('deviceActiveTab_{{ $device->id }}');
        // Redirect old troubleshooting tab users to dashboard (merged)
        return saved === 'troubleshooting' ? 'dashboard' : (saved || 'dashboard');
    })(),
    taskLoading: false,
    taskMessage: '',
    taskId: null,
    timerInterval: null,

    init() {
        this.$watch('activeTab', value => {
            localStorage.setItem('deviceActiveTab_{{ $device->id }}', value);
        });

        // Listen for ping/traceroute completion - switch to Tasks tab and reload
        window.addEventListener('diagnostics-completed', (event) => {
            const taskId = event.detail?.taskId;
            if (!taskId) return;

            // Check if we already redirected for this task
            const redirectedKey = `diagnosticsRedirected_${taskId}`;
            if (sessionStorage.getItem(redirectedKey)) {
                console.log('Already redirected for task', taskId);
                return;
            }

            console.log('Diagnostics completed, switching to Tasks tab for task', taskId);
            sessionStorage.setItem(redirectedKey, 'true');
            this.activeTab = 'tasks';

            // Reload page after a short delay to ensure tab switch is saved
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });

        // Listen for query completion - refresh page to show updated data
        window.addEventListener('query-completed', (event) => {
            const taskId = event.detail?.taskId;
            if (!taskId) return;

            // Check if we already refreshed for this task
            const refreshedKey = `queryRefreshed_${taskId}`;
            if (sessionStorage.getItem(refreshedKey)) {
                console.log('Already refreshed for task', taskId);
                return;
            }

            console.log('Query completed, refreshing page for task', taskId);
            sessionStorage.setItem(refreshedKey, 'true');

            // Reload page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });

        // Listen for WiFi refresh completion - refresh page to show updated radio status
        window.addEventListener('wifi-refresh-completed', (event) => {
            const taskId = event.detail?.taskId;
            if (!taskId) return;

            // Check if we already refreshed for this task
            const refreshedKey = `wifiRefreshed_${taskId}`;
            if (sessionStorage.getItem(refreshedKey)) {
                console.log('Already refreshed for WiFi task', taskId);
                return;
            }

            console.log('WiFi refresh completed, reloading page for task', taskId);
            sessionStorage.setItem(refreshedKey, 'true');

            // Reload page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    },

    startTaskTracking(message, taskId) {
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
    {{-- Header Section --}}
    <div>
        <div class="flex-1 min-w-0">
            @php
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
                                class="flex items-center justify-center w-7 h-7 rounded-full bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all"
                                title="{{$failedCount}} failed task(s) in last 24 hours">
                            <svg class="w-5 h-5 text-white font-bold" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <span class="absolute inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold leading-none text-white bg-red-800 rounded-full pointer-events-none" style="top: -4px; right: -8px;">{{ $failedCount }}</span>

                        {{-- Failed Tasks Details Dropdown --}}
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
                <div class="mt-2 flex flex-wrap items-center text-xs sm:text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} gap-x-3 gap-y-1">
                    <span>Last Inform: {{ $device->last_inform ? $device->last_inform->diffForHumans() : 'Never' }}</span>
                    <span class="hidden sm:inline">Refresh: {{ $device->last_refresh_at ? $device->last_refresh_at->diffForHumans() : 'Never' }}</span>
                    <span class="hidden sm:inline">Backup: {{ $device->last_backup_at ? $device->last_backup_at->diffForHumans() : 'Never' }}</span>
                </div>
                @if($device->subscriber)
                <div class="mt-2 flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <a href="{{ route('subscribers.show', $device->subscriber->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                        {{ $device->subscriber->name }}
                    </a>
                    <span class="ml-2 text-gray-400 dark:text-gray-500">({{ $device->subscriber->account }})</span>
                    @if($device->subscriber->isCableInternet() && $device->serial_number)
                        @php
                            $isValidMac = strlen(preg_replace('/[^a-fA-F0-9]/', '', $device->serial_number)) === 12;
                        @endphp
                        @if($isValidMac)
                            <a href="{{ $device->subscriber->getCablePortalUrl($device->serial_number) }}"
                               target="_blank"
                               class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-800 transition-colors">
                                Cable Portal
                                <svg class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        @endif
                    @endif
                </div>
                @endif
            </div>

            @php
                // Get External IP
                $externalIpParam = $device->parameters()
                    ->where('name', 'LIKE', '%ExternalIPAddress%')
                    ->where('name', 'LIKE', '%WANIPConnection%')
                    ->first();
                $externalIp = $externalIpParam ? $externalIpParam->value : '';

                // Get Uptime parameter (TR-098 or TR-181)
                $uptimeParam = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', 'InternetGatewayDevice.DeviceInfo.UpTime')
                          ->orWhere('name', 'LIKE', 'Device.DeviceInfo.UpTime');
                    })
                    ->first();
                $uptimeSeconds = $uptimeParam ? (int)$uptimeParam->value : null;

                // Format uptime as human readable
                $uptimeFormatted = null;
                if ($uptimeSeconds !== null) {
                    $days = floor($uptimeSeconds / 86400);
                    $hours = floor(($uptimeSeconds % 86400) / 3600);
                    $minutes = floor(($uptimeSeconds % 3600) / 60);

                    $parts = [];
                    if ($days > 0) $parts[] = $days . 'd';
                    if ($hours > 0) $parts[] = $hours . 'h';
                    if ($minutes > 0) $parts[] = $minutes . 'm';
                    $uptimeFormatted = implode(' ', $parts) ?: '< 1m';
                }

                // Get Config File (TR-098 or TR-181 - varies by manufacturer)
                $configFileParam = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%DeviceInfo.X_%ConfigFileVersion%')
                          ->orWhere('name', 'LIKE', '%DeviceInfo.X_%ConfigFile%')
                          ->orWhere('name', 'LIKE', '%DeviceInfo.ConfigFile%');
                    })
                    ->first();
                $configFile = $configFileParam ? $configFileParam->value : null;

                // Get MAC Address (TR-098 or TR-181 - various paths by manufacturer)
                $macAddressParam = $device->parameters()
                    ->where(function($q) {
                        // TR-098 WAN MAC (SmartRG, some Calix)
                        $q->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.1.WANEthernetInterfaceConfig.MACAddress')
                          // TR-098 LAN MAC
                          ->orWhere('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.MACAddress')
                          // TR-181 Ethernet Link MAC (Nokia Beacon G6)
                          ->orWhere('name', 'LIKE', 'Device.Ethernet.Link.%.MACAddress')
                          // TR-181 Device Info MAC
                          ->orWhere('name', 'LIKE', 'Device.DeviceInfo.MACAddress')
                          // TR-181 Ethernet Interface MAC
                          ->orWhere('name', 'LIKE', 'Device.Ethernet.Interface.1.MACAddress');
                    })
                    ->first();
                $macAddress = $macAddressParam ? strtoupper($macAddressParam->value) : null;
            @endphp

            {{-- Persistent Device Info Row --}}
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                {{-- Device Model --}}
                <span class="flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                    <span class="font-medium">{{ $device->manufacturer }} {{ $device->display_name }}</span>
                </span>

                {{-- Data Model --}}
                <span class="flex items-center">
                    <span class="px-1.5 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" title="TR-069 Data Model">
                        {{ $device->getDataModel() }}
                    </span>
                </span>

                {{-- WAN IP with copy button --}}
                @if($externalIp)
                <span class="flex items-center" x-data="{ copied: false }">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                    </svg>
                    <span class="font-mono">{{ $externalIp }}</span>
                    <button @click="navigator.clipboard.writeText('{{ $externalIp }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="ml-1 p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            title="Copy IP address">
                        <svg x-show="!copied" class="w-3.5 h-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                </span>
                @endif

                {{-- MAC Address with OUI lookup tooltip --}}
                @if($macAddress)
                <span class="flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                    <x-mac-address :mac="$macAddress" />
                </span>
                @endif

                {{-- Software Version --}}
                @if($device->software_version)
                <span class="flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <span title="Software Version">v{{ $device->software_version }}</span>
                </span>
                @endif

                {{-- Config File --}}
                @if($configFile)
                <span class="flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span title="Config File">{{ $configFile }}</span>
                </span>
                @endif

                {{-- Device Uptime --}}
                @if($uptimeFormatted)
                <span class="flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span title="Device Uptime">Up {{ $uptimeFormatted }}</span>
                </span>
                @endif

                {{-- Connection Request URL with copy button --}}
                @if($device->connection_request_url)
                <span class="flex items-center" x-data="{ copied: false }">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    <span class="font-mono truncate max-w-xs" title="{{ $device->connection_request_url }}">{{ $device->connection_request_url }}</span>
                    <button @click="navigator.clipboard.writeText('{{ $device->connection_request_url }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="ml-1 p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            title="Copy Connection Request URL">
                        <svg x-show="!copied" class="w-3.5 h-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                </span>
                @endif
            </div>
        </div>

        @php
            // Note: $externalIp already set above
        @endphp

        {{-- Quick Action Buttons (Mobile-optimized with uniform sizing) --}}
        <div class="mt-4 grid grid-cols-3 sm:flex sm:flex-wrap gap-2">
            {{-- Connect Now --}}
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
                        alert('Connection request sent successfully!');
                    } else {
                        alert('Error: ' + (result.error || result.message || 'Failed to send connection request'));
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700 touch-manipulation min-h-[44px]"
               title="Send connection request to device">
                <svg class="w-4 h-4 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <span class="hidden sm:inline">Connect Now</span>
                <span class="sm:hidden ml-1 truncate">Connect</span>
            </button>

            {{-- Query --}}
            <form @submit.prevent="async (e) => {
                taskLoading = true;
                taskMessage = 'Querying Device...';

                try {
                    const response = await fetch('/api/devices/{{ $device->id }}/query', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result.task && result.task.id) {
                        startTaskTracking('Querying Device...', result.task.id);
                    } else {
                        taskLoading = false;
                        alert('Error: ' + (result.error || result.message || 'Unknown error'));
                    }
                } catch (error) {
                    taskLoading = false;
                    alert('Error querying device: ' + error.message);
                }
            }" class="contents">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors['btn-secondary'] }}-600 hover:bg-{{ $colors['btn-secondary'] }}-700 touch-manipulation min-h-[44px]"
                        title="Query basic device information">
                    Query
                </button>
            </form>

            {{-- Reboot Device --}}
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
            }" class="contents">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 touch-manipulation min-h-[44px]"
                        title="Reboot device">
                    Reboot
                </button>
            </form>

            {{-- Factory Reset --}}
            <form @submit.prevent="async (e) => {
                if (!confirm('WARNING: This will erase ALL device settings and data!\n\nAre you absolutely sure you want to factory reset this device?')) return;

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
            }" class="contents">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-danger"] }}-600 hover:bg-{{ $colors["btn-danger"] }}-700 touch-manipulation min-h-[44px]"
                        title="Factory Reset device and restore settings from config backup">
                    <span class="sm:hidden truncate">Reset</span>
                    <span class="hidden sm:inline">Factory Reset</span>
                </button>
            </form>

            {{-- Upgrade Firmware --}}
            @php
                $hasActiveFirmware = $device->deviceType && $device->deviceType->firmware()->where('is_active', true)->exists();
            @endphp
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
            }" class="contents">
                @csrf
                <button type="submit"
                        {{ !$hasActiveFirmware ? 'disabled' : '' }}
                        class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700 touch-manipulation min-h-[44px] {{ !$hasActiveFirmware ? 'opacity-50 cursor-not-allowed' : '' }}"
                        title="{{ $hasActiveFirmware ? 'Upgrade to latest firmware set for this device type' : 'No active firmware set for this device type' }}">
                    Upgrade
                </button>
            </form>

            {{-- Ping --}}
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
            }" class="contents">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-info"] }}-600 hover:bg-{{ $colors["btn-info"] }}-700 touch-manipulation min-h-[44px]"
                        title="Run ping test to 8.8.8.8">
                    Ping
                </button>
            </form>

            {{-- Trace Route --}}
            <form @submit.prevent="async (e) => {
                if ('{{ $device->manufacturer }}' === 'SmartRG') {
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
                    alert('Error starting trace route: ' + error);
                }
            }" class="contents">
                @csrf
                <button type="submit"
                    @if($device->manufacturer === 'SmartRG')
                        disabled
                        title="Not supported for SmartRG devices"
                    @else
                        title="Trace Route to 8.8.8.8"
                    @endif
                    class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-warning"] }}-600 hover:bg-{{ $colors["btn-warning"] }}-700 touch-manipulation min-h-[44px] {{ $device->manufacturer === 'SmartRG' ? 'opacity-50 cursor-not-allowed' : '' }}">
                    <span class="sm:hidden truncate">Trace</span>
                    <span class="hidden sm:inline">Trace Route</span>
                    @if($device->manufacturer === 'SmartRG')
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 ml-1 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 15.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    @endif
                </button>
            </form>

            {{-- Speed Test (hidden for GigaSpires - not currently supported) --}}
            @if(!$device->isGigaSpire())
            <form x-data="{ submitting: false }" @submit.prevent="async (e) => {
                if (submitting) return;
                submitting = true;
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
                            test_type: 'both'
                        })
                    });
                    const result = await response.json();
                    if (result.tasks && result.tasks.length > 0) {
                        startTaskTracking('Running TR-143 SpeedTest (Download & Upload)...', result.tasks[0].id);
                    } else {
                        taskLoading = false;
                        submitting = false;
                        alert('SpeedTest started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    submitting = false;
                    alert('Error starting SpeedTest: ' + error);
                }
            }" class="contents">
                @csrf
                <button type="submit" :disabled="submitting" :class="submitting ? 'opacity-75 cursor-not-allowed' : ''" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-success"] }}-600 hover:bg-{{ $colors["btn-success"] }}-700 touch-manipulation min-h-[44px] transition-opacity"
                        title="Initiate an upload and download speed test">
                    {{-- Normal icon --}}
                    <svg x-show="!submitting" class="w-4 h-4 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    {{-- Spinner icon --}}
                    <svg x-show="submitting" x-cloak class="w-4 h-4 sm:mr-2 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!submitting" class="hidden sm:inline">Speed Test</span>
                    <span x-show="!submitting" class="sm:hidden ml-1 truncate">Speed</span>
                    <span x-show="submitting" x-cloak class="hidden sm:inline">Working...</span>
                    <span x-show="submitting" x-cloak class="sm:hidden ml-1 truncate">...</span>
                </button>
            </form>
            @endif

            {{-- Refresh --}}
            <form x-data="{ submitting: false }" @submit.prevent="async (e) => {
                if (submitting) return;
                submitting = true;
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
                        submitting = false;
                        alert('Refresh started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    submitting = false;
                    alert('Error refreshing troubleshooting info: ' + error);
                }
            }" class="contents">
                @csrf
                <button type="submit" :disabled="submitting" :class="submitting ? 'opacity-75 cursor-not-allowed' : ''" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-info"] }}-600 hover:bg-{{ $colors["btn-info"] }}-700 touch-manipulation min-h-[44px] transition-opacity"
                        title="Do a larger refresh that polls more troubleshooting information">
                    {{-- Normal icon --}}
                    <svg x-show="!submitting" class="w-4 h-4 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    {{-- Spinner icon --}}
                    <svg x-show="submitting" x-cloak class="w-4 h-4 sm:mr-2 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!submitting" class="hidden sm:inline">Refresh</span>
                    <span x-show="!submitting" class="sm:hidden truncate">Refresh</span>
                    <span x-show="submitting" x-cloak class="hidden sm:inline">Working...</span>
                    <span x-show="submitting" x-cloak class="sm:hidden truncate">...</span>
                </button>
            </form>

            {{-- Get Everything (Admin Only) --}}
            @if(auth()->user()?->isAdmin())
            <form x-data="{ submitting: false }" @submit.prevent="async (e) => {
                if (submitting) return;
                submitting = true;
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
                        submitting = false;
                        alert('Get Everything started, but no task ID returned');
                    }
                } catch (error) {
                    taskLoading = false;
                    submitting = false;
                    alert('Error getting all parameters: ' + error);
                }
            }" class="contents">
                @csrf
                <button type="submit" :disabled="submitting" :class="submitting ? 'opacity-75 cursor-not-allowed' : ''" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 touch-manipulation min-h-[44px] transition-opacity">
                    {{-- Normal icon --}}
                    <svg x-show="!submitting" class="w-4 h-4 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{-- Spinner icon --}}
                    <svg x-show="submitting" x-cloak class="w-4 h-4 sm:mr-2 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!submitting" class="hidden sm:inline">Get Everything</span>
                    <span x-show="!submitting" class="sm:hidden truncate">Get All</span>
                    <span x-show="submitting" x-cloak class="hidden sm:inline">Working...</span>
                    <span x-show="submitting" x-cloak class="sm:hidden truncate">...</span>
                </button>
            </form>
            @endif

            {{-- Remote GUI --}}
            @php
                $merIp = null;
                if ($device->manufacturer === 'SmartRG') {
                    // Find MER network IP - starts with 192.168.x.x but NOT 192.168.1.x (LAN subnet)
                    $wanIpParam = $device->parameters()
                        ->where('name', 'LIKE', '%WANIPConnection%ExternalIPAddress')
                        ->where('value', 'LIKE', '192.168.%')
                        ->where('value', 'NOT LIKE', '192.168.1.%')
                        ->first();
                    $merIp = $wanIpParam ? $wanIpParam->value : null;
                }
            @endphp
            <button @click="async () => {
                const isSmartRG = '{{ $device->manufacturer }}' === 'SmartRG';
                const merIp = '{{ $merIp }}';

                if (isSmartRG && merIp) {
                    const url = 'http://' + merIp + '/';
                    window.open(url, '_blank', 'noopener,noreferrer');
                    return;
                }

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
                        // Use port/protocol from API response
                        // Calix: http:8080, Nokia: https:443, default: https:8443
                        const port = result.port || '8443';
                        const protocol = result.protocol || 'https';
                        const externalIp = result.external_ip || '{{ $externalIp }}';
                        if (externalIp) {
                            const url = protocol + '://' + externalIp + ':' + port + '/';
                            // Use noopener,noreferrer to avoid Invalid REFERER errors on Nokia devices
                            window.open(url, '_blank', 'noopener,noreferrer');
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
            }" class="inline-flex items-center justify-center px-2 py-2.5 sm:px-4 sm:py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 touch-manipulation min-h-[44px]"
               title="Open the device's Remote GUI">
                <svg class="w-4 h-4 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
                <span class="hidden sm:inline">GUI</span>
                <span class="sm:hidden truncate">GUI</span>
                @if($device->manufacturer === 'SmartRG')
                    <span class="ml-1 text-xs text-gray-300 hidden sm:inline">(MER)</span>
                @endif
            </button>

            {{-- Remote GUI Open Indicator with Close Button --}}
            @if($device->remote_support_expires_at && $device->remote_support_expires_at->gt(now()))
                <div class="col-span-3 sm:col-span-2 inline-flex items-center justify-center gap-2">
                    <div class="inline-flex items-center px-3 py-1.5 rounded-md bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700">
                        <span class="relative flex h-2.5 w-2.5 mr-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-500"></span>
                        </span>
                        <span class="text-xs font-medium text-yellow-800 dark:text-yellow-300">
                            Remote GUI Open
                            <span class="text-yellow-600 dark:text-yellow-400 ml-1 hidden sm:inline">(expires {{ $device->remote_support_expires_at->diffForHumans() }})</span>
                        </span>
                    </div>
                    <button @click="async () => {
                        if (!confirm('Close remote access? This will disable remote GUI and reset the password.')) return;
                        taskLoading = true;
                        taskMessage = 'Closing remote access...';
                        try {
                            const response = await fetch('/api/devices/{{ $device->id }}/close-remote-access', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                            });
                            const result = await response.json();
                            if (result.success) {
                                taskMessage = 'Remote access closed successfully';
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                taskLoading = false;
                                alert('Failed to close remote access');
                            }
                        } catch (error) {
                            taskLoading = false;
                            alert('Error: ' + error);
                        }
                    }" class="inline-flex items-center px-2 py-1.5 rounded-md text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 hover:bg-red-200 dark:hover:bg-red-900/50"
                       title="Close remote access and reset password">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Close
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tab Navigation (Mobile-scrollable) --}}
    <div class="border-b border-gray-200 dark:border-{{ $colors['border'] }} -mx-4 sm:mx-0">
        <nav class="-mb-px flex space-x-4 sm:space-x-8 overflow-x-auto px-4 sm:px-0 scrollbar-hide">
            <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Dashboard
            </button>
            <button @click="activeTab = 'parameters'"
                    :class="activeTab === 'parameters' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Parameters
            </button>
            <button @click="activeTab = 'tasks'"
                    :class="activeTab === 'tasks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Tasks
            </button>
            <button @click="activeTab = 'wifi'"
                    :class="activeTab === 'wifi' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                WiFi
            </button>
            <button @click="activeTab = 'backups'"
                    :class="activeTab === 'backups' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Backups
            </button>
            <button @click="activeTab = 'events'"
                    :class="activeTab === 'events' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Events
            </button>
            @if(auth()->user()?->isAdmin())
            <button @click="activeTab = 'templates'"
                    :class="activeTab === 'templates' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Templates
            </button>
            @endif
            <button @click="activeTab = 'ports'"
                    :class="activeTab === 'ports' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Ports
            </button>
            <button @click="{{ $device->manufacturer === 'SmartRG' ? '' : "activeTab = 'wifiscan'" }}"
                    :class="activeTab === 'wifiscan' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
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
                    :class="activeTab === 'speedtest' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 dark:text-{{ $colors['text-muted'] }} hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Speed Test
            </button>
        </nav>
    </div>

    {{-- Tab Content --}}
    @include('device-tabs.dashboard', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.parameters', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.tasks', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.wifi', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.backups', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.events', ['device' => $device, 'colors' => $colors, 'events' => $events])
    @if(auth()->user()?->isAdmin())
    @include('device-tabs.templates', ['device' => $device, 'colors' => $colors])
    @endif
    @include('device-tabs.ports', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.wifiscan', ['device' => $device, 'colors' => $colors])
    @include('device-tabs.speedtest', ['device' => $device, 'colors' => $colors])
</div>
@endsection
