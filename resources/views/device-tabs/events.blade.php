{{-- Device Events History Tab --}}
<div x-show="activeTab === 'events'" x-cloak class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg overflow-hidden">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Event History</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">TR-069 events reported by this device</p>
            </div>
            <div class="flex items-center space-x-4">
                {{-- Reboot frequency indicator --}}
                @php
                    $rebootCount24h = $device->events()->boots()->where('created_at', '>=', now()->subHours(24))->count();
                    $rebootCount7d = $device->events()->boots()->where('created_at', '>=', now()->subDays(7))->count();
                @endphp
                @if($rebootCount24h >= 3)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $rebootCount24h }} reboots in 24h
                    </span>
                @elseif($rebootCount7d >= 5)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $rebootCount7d }} reboots in 7d
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Event Statistics Summary --}}
    <div class="px-4 py-3 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-b border-gray-200 dark:border-{{ $colors['border'] }}">
        <div class="flex flex-wrap gap-4 text-sm">
            @php
                $eventStats = $device->events()
                    ->selectRaw('event_type, COUNT(*) as count')
                    ->groupBy('event_type')
                    ->pluck('count', 'event_type')
                    ->toArray();
            @endphp
            @foreach(['boot' => 'Reboots', 'bootstrap' => 'Factory Resets', 'periodic' => 'Periodic', 'connection_request' => 'Connection Requests', 'transfer_complete' => 'Transfers', 'diagnostics_complete' => 'Diagnostics'] as $type => $label)
                @if(isset($eventStats[$type]) && $eventStats[$type] > 0)
                    <span class="inline-flex items-center px-2 py-1 rounded bg-{{ $type === 'bootstrap' ? 'purple' : ($type === 'boot' ? 'orange' : 'blue') }}-100 dark:bg-{{ $type === 'bootstrap' ? 'purple' : ($type === 'boot' ? 'orange' : 'blue') }}-900 text-{{ $type === 'bootstrap' ? 'purple' : ($type === 'boot' ? 'orange' : 'blue') }}-800 dark:text-{{ $type === 'bootstrap' ? 'purple' : ($type === 'boot' ? 'orange' : 'blue') }}-200">
                        {{ $label }}: {{ $eventStats[$type] }}
                    </span>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Events Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Details</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Source IP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Session</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                @forelse($events as $event)
                <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        <div>{{ $event->created_at->format('Y-m-d H:i:s') }}</div>
                        <div class="text-xs text-gray-400">{{ $event->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $badgeColor = match($event->event_type) {
                                'bootstrap' => 'purple',
                                'boot' => 'orange',
                                'periodic' => 'gray',
                                'connection_request' => 'blue',
                                'transfer_complete' => 'green',
                                'diagnostics_complete' => 'cyan',
                                'value_change' => 'yellow',
                                'request_download' => 'indigo',
                                default => 'gray',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $badgeColor }}-100 text-{{ $badgeColor }}-800 dark:bg-{{ $badgeColor }}-900 dark:text-{{ $badgeColor }}-200">
                            {{ $event->label }}
                        </span>
                        <div class="text-xs text-gray-400 mt-1">{{ $event->event_code }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        @if($event->command_key)
                            <span class="text-xs font-mono bg-gray-100 dark:bg-{{ $colors['bg'] }} px-1 rounded">{{ Str::limit($event->command_key, 30) }}</span>
                        @endif
                        @if($event->details)
                            <div class="text-xs mt-1">
                                @foreach($event->details as $key => $value)
                                    <span class="text-gray-400">{{ $key }}:</span> {{ Str::limit($value, 40) }}
                                @endforeach
                            </div>
                        @endif
                        @if(!$event->command_key && !$event->details)
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        {{ $event->source_ip ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        @if($event->session_id)
                            <span class="text-xs">#{{ $event->session_id }}</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p>No events recorded yet.</p>
                            <p class="text-xs mt-1">Events will appear here as the device communicates with the ACS.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($events->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            {{ $events->links() }}
        </div>
    @endif
</div>
