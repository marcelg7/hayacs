@extends('layouts.app')

@section('title', $device->id . ' - Device Details')

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'info' }">
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
        <div class="mt-4 flex flex-wrap gap-2">
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
        </nav>
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
            <form action="/api/devices/{{ $device->id }}/refresh-troubleshooting" method="POST">
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

                    // Filter out inactive hosts
                    $hosts = array_filter($hosts, function($host) {
                        return isset($host['Active']) && ($host['Active'] === 'true' || $host['Active'] === '1');
                    });
                @endphp

                @if(count($hosts) > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hostname</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interface</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($hosts as $host)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $host['HostName'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">{{ $host['IPAddress'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">{{ $host['MACAddress'] ?? $host['PhysAddress'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $host['InterfaceType'] ?? $host['AddressSource'] ?? '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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
</div>
@endsection
