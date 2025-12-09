{{-- Device Tasks Tab --}}
<div x-show="activeTab === 'tasks'" x-cloak class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg overflow-hidden" x-data="{
    expandedTask: null
}">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Device Tasks</h3>
    </div>

    {{-- Mobile: Card layout --}}
    <div class="sm:hidden divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
        @forelse($tasks as $task)
        <div class="p-4 {{ ($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result ? 'cursor-pointer' : '' }}"
            @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
            @click="expandedTask = expandedTask === {{ $task->id }} ? null : {{ $task->id }}"
            @endif>
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                            {{ str_replace('_', ' ', ucwords($task->task_type, '_')) }}
                        </span>
                        @if($task->status === 'pending')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pending</span>
                        @elseif($task->status === 'sent')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Sent</span>
                        @elseif($task->status === 'completed')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
                        @elseif($task->status === 'cancelled')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Cancelled</span>
                        @elseif($task->status === 'failed')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ ucfirst($task->status) }}</span>
                        @endif
                    </div>
                    @if($task->description)
                        <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} mt-1 line-clamp-2">{{ $task->description }}</p>
                    @endif
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-400 dark:text-gray-500">
                        <span>#{{ $task->id }}</span>
                        <span>{{ $task->created_at->diffForHumans() }}</span>
                    </div>
                    @if($task->status === 'failed' && $task->error)
                        <p class="text-xs text-red-600 dark:text-red-400 mt-2 bg-red-50 dark:bg-red-900/20 p-2 rounded">{{ $task->error }}</p>
                    @endif
                </div>
                @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                    <svg class="h-5 w-5 text-blue-500 ml-2 flex-shrink-0 transition-transform" :class="expandedTask === {{ $task->id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                @endif
            </div>
            {{-- Expanded diagnostics results for mobile --}}
            @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
            <div x-show="expandedTask === {{ $task->id }}" x-cloak class="mt-3 pt-3 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                @include('device-tabs.partials.task-diagnostics-results', ['task' => $task, 'colors' => $colors])
            </div>
            @endif
        </div>
        @empty
        <div class="p-8 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No tasks found.</div>
        @endforelse
    </div>

    {{-- Desktop: Table layout --}}
    <table class="hidden sm:table min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Created</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Completed</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            @forelse($tasks as $task)
            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} {{ ($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result ? 'cursor-pointer' : '' }}"
                @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                @click="expandedTask = expandedTask === {{ $task->id }} ? null : {{ $task->id }}"
                @endif>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }}">#{{ $task->id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }}">
                    {{ str_replace('_', ' ', ucwords($task->task_type, '_')) }}
                    @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
                        <span class="ml-2 text-blue-600 dark:text-blue-400">▼</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" title="{{ $task->description }}">
                    {{ $task->description ? Str::limit($task->description, 50) : '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    @if($task->status === 'pending')
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pending</span>
                    @elseif($task->status === 'sent')
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Sent</span>
                    @elseif($task->status === 'completed')
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
                    @elseif($task->status === 'cancelled')
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Cancelled</span>
                    @elseif($task->status === 'failed')
                        <div class="inline-flex items-center space-x-1 group relative">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                            <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="hidden group-hover:block absolute left-0 top-full mt-1 z-50 w-72 bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                                @if($task->error)
                                    <p class="font-medium mb-1">{{ $task->error }}</p>
                                @endif
                                <p class="text-gray-300 text-xs">This is normal TR-069 behavior. Some requests fail if the device reconnects, is busy, or doesn't support certain parameters.</p>
                            </div>
                        </div>
                    @else
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ ucfirst($task->status) }}</span>
                    @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $task->created_at->format('Y-m-d H:i:s') }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $task->completed_at ? $task->completed_at->format('Y-m-d H:i:s') : '-' }}</td>
            </tr>
            @if(($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') && $task->result)
            <tr x-show="expandedTask === {{ $task->id }}" x-cloak class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                <td colspan="6" class="px-6 py-4">
                    <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg p-4 shadow-sm">
                        <h4 class="font-semibold text-gray-900 dark:text-{{ $colors['text'] }} mb-3">Diagnostic Results</h4>
                        @if($task->task_type === 'ping_diagnostics')
                            @php
                                $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
                                $firstKey = array_key_first($results ?? []);
                                // TR-181 uses IPPing (not IPPingDiagnostics)
                                $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.IPPing' : 'InternetGatewayDevice.IPPingDiagnostics';

                                // Get timing values
                                $successCount = (int)($results["{$prefix}.SuccessCount"]['value'] ?? 0);
                                $avgTime = (int)($results["{$prefix}.AverageResponseTime"]['value'] ?? 0);
                                $minTime = (int)($results["{$prefix}.MinimumResponseTime"]['value'] ?? 0);
                                $maxTime = (int)($results["{$prefix}.MaximumResponseTime"]['value'] ?? 0);

                                // Detect invalid timing data (firmware bug - returns garbage values)
                                $invalidTiming = ($minTime >= 4000000 || $maxTime >= 4000000 || $avgTime >= 4000000) ||
                                                ($successCount > 0 && $minTime == 0 && $maxTime == 0 && $avgTime == 0);
                            @endphp
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Success Count:</span>
                                    <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.SuccessCount"]['value'] ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Failure Count:</span>
                                    <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.FailureCount"]['value'] ?? 'N/A' }}</span>
                                </div>
                                @if($invalidTiming)
                                    <div class="col-span-2">
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-3">
                                            <div class="flex items-start">
                                                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                <div>
                                                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Invalid Timing Data</p>
                                                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">Device firmware returned invalid response times, but {{ $successCount }} ping(s) succeeded. This is a known firmware bug on some devices.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div>
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Average Response Time:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.AverageResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Min Response Time:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.MinimumResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Max Response Time:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.MaximumResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">State:</span>
                                    <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.DiagnosticsState"]['value'] ?? 'N/A' }}</span>
                                </div>
                            </div>
                        @else
                            @php
                                $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
                                $firstKey = array_key_first($results ?? []);
                                // TR-181 uses TraceRoute (not TraceRouteDiagnostics)
                                $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.TraceRoute' : 'InternetGatewayDevice.TraceRouteDiagnostics';

                                // Extract hop data - handles both TR-181 (Host, HostAddress, RTTimes) and TR-098 (HopHost, HopHostAddress, HopRTTimes)
                                $hops = [];
                                foreach ($results as $key => $data) {
                                    if (preg_match('/RouteHops\.(\d+)\.(.+)$/', $key, $matches)) {
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
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Response Time:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.ResponseTime"]['value'] ?? 'N/A' }} ms</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Number of Hops:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.RouteHopsNumberOfEntries"]['value'] ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">State:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.DiagnosticsState"]['value'] ?? 'N/A' }}</span>
                                    </div>
                                </div>

                                @if(count($hops) > 0)
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-{{ $colors['text'] }} mb-2">Route Hops</h5>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }} text-sm">
                                            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Hop</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Host</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">IP Address</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">RTT</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                                                @foreach($hops as $hop)
                                                <tr>
                                                    <td class="px-3 py-2 whitespace-nowrap text-gray-900 dark:text-{{ $colors['text'] }}">{{ $hop['number'] }}</td>
                                                    <td class="px-3 py-2 text-gray-900 dark:text-{{ $colors['text'] }}">{{ $hop['Host'] ?? $hop['HopHost'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 whitespace-nowrap text-gray-900 dark:text-{{ $colors['text'] }}">{{ $hop['HostAddress'] ?? $hop['HopHostAddress'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 whitespace-nowrap text-gray-900 dark:text-{{ $colors['text'] }}">{{ $hop['RTTimes'] ?? $hop['HopRTTimes'] ?? '-' }}</td>
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
                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No tasks found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
