{{-- Device Parameters & Sessions Tab --}}
<div x-show="activeTab === 'parameters'" x-cloak class="space-y-6">
    {{-- Parameters Section --}}
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg overflow-hidden" x-data="{
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
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Device Parameters</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">All parameters stored for this device</p>
                </div>
                <div>
                    <a :href="`/api/devices/{{ $device->id }}/parameters/export?format=csv${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span x-text="searchQuery ? 'Export Filtered CSV' : 'Export CSV'"></span>
                    </a>
                </div>
            </div>

            {{-- Smart Search Box --}}
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
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md leading-5 bg-white dark:bg-{{ $colors['card'] }} text-gray-900 dark:text-{{ $colors['text'] }} placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Search parameters by name or value... (e.g., WiFi, IP, Serial)"
                    >
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-show="!searching && searchResults">
                    Found <span x-text="searchResults?.data?.length || 0"></span> matching parameter<span x-text="(searchResults?.data?.length !== 1) ? 's' : ''"></span>
                </p>
                <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-400" x-show="searching">
                    <svg class="inline h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </p>
            </div>
        </div>

        {{-- Search Results --}}
        <div class="overflow-x-auto" x-show="searchResults" x-cloak>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Parameter Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Last Updated</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                <template x-for="param in searchResults?.data || []" :key="param.id">
                    <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="param.name"></td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-{{ $colors['text'] }} break-all" x-text="param.value"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="param.type || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="param.last_updated_human || '-'"></td>
                    </tr>
                </template>
                <template x-if="searchResults?.data?.length === 0">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No matching parameters found.</td>
                    </tr>
                </template>
            </tbody>
            </table>
        </div>

        {{-- Default Parameters Table (when not searching) --}}
        <div class="overflow-x-auto" x-show="!searchResults">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Parameter Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Last Updated</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                @forelse($parameters as $param)
                <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-{{ $colors['text'] }}">{{ $param->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-{{ $colors['text'] }} break-all">{{ $param->value }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $param->type ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $param->last_updated ? $param->last_updated->diffForHumans() : '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No parameters found.</td>
                </tr>
                @endforelse
            </tbody>
            </table>
        </div>

        @if($parameters->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-{{ $colors['border'] }}" x-show="!searchResults">
            {{ $parameters->links() }}
        </div>
        @endif
    </div>

    {{-- CWMP Sessions Section --}}
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg overflow-hidden">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-{{ $colors['border'] }} bg-indigo-50 dark:bg-indigo-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">CWMP Sessions</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Recent TR-069 communication sessions</p>
        </div>
        <div class="overflow-x-auto">
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
    </div>
</div>
