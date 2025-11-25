{{-- CWMP Sessions Tab --}}
<div x-show="activeTab === 'sessions'" x-cloak class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg overflow-hidden">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">CWMP Sessions</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Started</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Ended</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Messages</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Events</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            @forelse($sessions as $session)
            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }}">#{{ $session->id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $session->started_at->format('Y-m-d H:i:s') }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $session->ended_at ? $session->ended_at->format('Y-m-d H:i:s') : 'In Progress' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $session->messages_exchanged }}</td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    @if($session->inform_events)
                        @foreach($session->inform_events as $event)
                            <span class="inline-block bg-gray-100 dark:bg-{{ $colors['bg'] }} rounded px-2 py-1 text-xs mr-1 mb-1">{{ $event['code'] ?? 'Unknown' }}</span>
                        @endforeach
                    @else
                        -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No sessions found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
