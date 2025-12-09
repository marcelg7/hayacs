{{-- Task Diagnostics Results Partial --}}
@if($task->task_type === 'ping_diagnostics')
    @php
        $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
        $firstKey = array_key_first($results ?? []);
        $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.IPPing' : 'InternetGatewayDevice.IPPingDiagnostics';

        $successCount = (int)($results["{$prefix}.SuccessCount"]['value'] ?? 0);
        $avgTime = (int)($results["{$prefix}.AverageResponseTime"]['value'] ?? 0);
        $minTime = (int)($results["{$prefix}.MinimumResponseTime"]['value'] ?? 0);
        $maxTime = (int)($results["{$prefix}.MaximumResponseTime"]['value'] ?? 0);

        $invalidTiming = ($minTime >= 4000000 || $maxTime >= 4000000 || $avgTime >= 4000000) ||
                        ($successCount > 0 && $minTime == 0 && $maxTime == 0 && $avgTime == 0);
    @endphp
    <div class="text-sm">
        <h4 class="font-semibold text-gray-900 dark:text-{{ $colors['text'] }} mb-2">Ping Results</h4>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Success:</span>
                <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.SuccessCount"]['value'] ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Failed:</span>
                <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.FailureCount"]['value'] ?? 'N/A' }}</span>
            </div>
            @if(!$invalidTiming)
                <div>
                    <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Avg:</span>
                    <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $avgTime }} ms</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Min/Max:</span>
                    <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $minTime }}/{{ $maxTime }} ms</span>
                </div>
            @else
                <div class="col-span-2">
                    <p class="text-xs text-yellow-600 dark:text-yellow-400">Invalid timing data (firmware bug)</p>
                </div>
            @endif
        </div>
    </div>
@else
    @php
        $results = is_array($task->result) ? $task->result : json_decode($task->result, true);
        $firstKey = array_key_first($results ?? []);
        $prefix = str_starts_with($firstKey, 'Device.IP.') ? 'Device.IP.Diagnostics.TraceRoute' : 'InternetGatewayDevice.TraceRouteDiagnostics';

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
    <div class="text-sm">
        <h4 class="font-semibold text-gray-900 dark:text-{{ $colors['text'] }} mb-2">Traceroute Results</h4>
        <div class="mb-2">
            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Hops:</span>
            <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.RouteHopsNumberOfEntries"]['value'] ?? 'N/A' }}</span>
            <span class="mx-2">|</span>
            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Time:</span>
            <span class="font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $results["{$prefix}.ResponseTime"]['value'] ?? 'N/A' }} ms</span>
        </div>
        @if(count($hops) > 0)
            <div class="space-y-1 max-h-40 overflow-y-auto">
                @foreach($hops as $hop)
                    <div class="flex items-center text-xs bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded px-2 py-1">
                        <span class="w-6 font-medium">{{ $hop['number'] }}</span>
                        <span class="flex-1 font-mono truncate">{{ $hop['HostAddress'] ?? $hop['HopHostAddress'] ?? '-' }}</span>
                        <span class="ml-2 text-gray-400">{{ $hop['RTTimes'] ?? $hop['HopRTTimes'] ?? '-' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
