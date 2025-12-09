<div x-data="globalSearch()" x-init="init()" @keydown.window="handleGlobalKeydown($event)" class="relative">
    <!-- Persistent Search Input -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <input x-ref="searchInput"
               x-model="query"
               @input.debounce.250ms="search()"
               @focus="onFocus()"
               @keydown.escape="closeResults()"
               @keydown.arrow-down.prevent="navigateDown()"
               @keydown.arrow-up.prevent="navigateUp()"
               @keydown.enter.prevent="selectResult()"
               type="text"
               placeholder="Search..."
               class="w-56 lg:w-72 xl:w-80 pl-10 pr-10 py-2 text-sm bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-all duration-150">
        <!-- Keyboard shortcut hint (shown when empty and not focused) -->
        <div x-show="!query && !isFocused" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <kbd class="px-1.5 py-0.5 text-xs font-mono text-gray-400 bg-gray-200 dark:bg-slate-600 rounded">/</kbd>
        </div>
        <!-- Clear button (shown when has query) -->
        <button x-show="query"
                @mousedown.prevent="clearSearch()"
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Results Dropdown -->
    <div x-show="showResults"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 transform -translate-y-1"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @mousedown.prevent
         class="absolute top-full left-0 mt-2 w-[30rem] max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden z-50"
         x-cloak>

        <!-- Category Filter Pills -->
        <div x-show="query.length >= 2 || results.length > 0" class="px-3 py-2 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/30">
            <div class="flex flex-wrap gap-1.5">
                <button @click="toggleFilter('all')"
                        :class="activeFilter === 'all' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border-indigo-300' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600'"
                        class="px-2 py-0.5 text-xs font-medium rounded-full border transition-colors">
                    All
                </button>
                <button @click="toggleFilter('devices')"
                        :class="activeFilter === 'devices' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border-indigo-300' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600'"
                        class="px-2 py-0.5 text-xs font-medium rounded-full border transition-colors">
                    Devices
                </button>
                <button @click="toggleFilter('subscribers')"
                        :class="activeFilter === 'subscribers' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border-indigo-300' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600'"
                        class="px-2 py-0.5 text-xs font-medium rounded-full border transition-colors">
                    Subscribers
                </button>
                <button @click="toggleFilter('tasks')"
                        :class="activeFilter === 'tasks' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border-indigo-300' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600'"
                        class="px-2 py-0.5 text-xs font-medium rounded-full border transition-colors">
                    Tasks
                </button>
                <button @click="toggleFilter('parameters')"
                        :class="activeFilter === 'parameters' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border-indigo-300' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600'"
                        class="px-2 py-0.5 text-xs font-medium rounded-full border transition-colors">
                    Parameters
                </button>
            </div>
        </div>

        <!-- Results Container -->
        <div class="max-h-[60vh] overflow-y-auto" x-ref="resultsContainer">
            <!-- Loading State -->
            <div x-show="loading" class="px-4 py-6 text-center">
                <svg class="animate-spin h-5 w-5 mx-auto text-indigo-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Recent Searches (shown when focused but no query) -->
            <div x-show="!loading && !query && isFocused && recentSearches.length > 0" class="border-b border-gray-200 dark:border-slate-700">
                <div class="px-3 py-1.5 bg-gray-50 dark:bg-slate-900/50 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recent Searches</span>
                    <button @click.stop="clearRecentSearches()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Clear</button>
                </div>
                <template x-for="(recent, index) in recentSearches" :key="index">
                    <button @click="useRecentSearch(recent)"
                            class="flex items-center gap-3 w-full px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 text-left transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="recent"></span>
                    </button>
                </template>
            </div>

            <!-- Quick Suggestions (shown when query is short) -->
            <div x-show="!loading && query.length >= 1 && query.length < 2 && suggestions.length > 0" class="border-b border-gray-200 dark:border-slate-700">
                <div class="px-3 py-1.5 bg-gray-50 dark:bg-slate-900/50">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Suggestions</span>
                </div>
                <template x-for="(suggestion, index) in suggestions" :key="index">
                    <button @click="useSuggestion(suggestion)"
                            class="flex items-center gap-3 w-full px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 text-left transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="suggestion"></span>
                    </button>
                </template>
            </div>

            <!-- Help Text (shown when focused but no query and no recent searches) -->
            <div x-show="!loading && !query && isFocused && recentSearches.length === 0" class="p-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Quick Search</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-indigo-500 font-medium">Device ID</span>
                        <span class="text-gray-400 font-mono text-xs">487746-ENT-CXNK0083217F</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-green-500 font-medium">Serial</span>
                        <span class="text-gray-400 font-mono text-xs">CXNK0083217F</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-blue-500 font-medium">IP Address</span>
                        <span class="text-gray-400 font-mono text-xs">23.155.130.7</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-purple-500 font-medium">MAC Address</span>
                        <span class="text-gray-400 font-mono text-xs">D0:76:8F:AB:12:34</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-orange-500 font-medium">Model</span>
                        <span class="text-gray-400 font-mono text-xs">844E, 505n, GS4220E, Beacon</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-cyan-500 font-medium">Manufacturer</span>
                        <span class="text-gray-400 font-mono text-xs">Calix, SmartRG, Nokia</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-pink-500 font-medium">Subscriber</span>
                        <span class="text-gray-400 font-mono text-xs">SMITH JOHN, 12345</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-yellow-500 font-medium">Task</span>
                        <span class="text-gray-400 font-mono text-xs">pending, failed, reboot, 12345</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="w-24 shrink-0 text-teal-500 font-medium">Firmware</span>
                        <span class="text-gray-400 font-mono text-xs">12.2.12.9, 23.4.0.1</span>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-slate-700 text-xs text-gray-400 flex items-center gap-4">
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded">↑↓</kbd> navigate</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded">↵</kbd> select</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded">esc</kbd> close</span>
                </div>
            </div>

            <!-- No Results -->
            <div x-show="!loading && query && filteredResults.length === 0 && searched" class="px-4 py-6 text-center">
                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">No results for "<span x-text="query" class="font-medium text-gray-700 dark:text-gray-300"></span>"</p>
                <p x-show="activeFilter !== 'all'" class="text-xs text-gray-400 mt-1">Try removing the filter or searching in all categories</p>
            </div>

            <!-- Results -->
            <template x-for="(category, catIndex) in filteredResults" :key="category.category">
                <div class="border-b border-gray-100 dark:border-slate-700 last:border-0">
                    <!-- Category Header -->
                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-slate-900/50 sticky top-0">
                        <div class="flex items-center gap-2">
                            <template x-if="category.icon === 'device'">
                                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'serial'">
                                <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'network'">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'mac'">
                                <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'parameter'">
                                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'subscriber'">
                                <svg class="w-3.5 h-3.5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'task'">
                                <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </template>
                            <template x-if="category.icon === 'firmware'">
                                <svg class="w-3.5 h-3.5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                </svg>
                            </template>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" x-text="category.category"></span>
                            <span class="text-xs text-gray-400" x-text="'(' + category.items.length + ')'"></span>
                        </div>
                    </div>

                    <!-- Category Items -->
                    <template x-for="(item, itemIndex) in category.items" :key="item.id + '-' + itemIndex">
                        <a :href="item.url"
                           @click="saveRecentSearch()"
                           @mouseenter="setActiveIndex(catIndex, itemIndex)"
                           class="flex items-center gap-3 px-3 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer transition-colors"
                           :class="{'bg-indigo-50 dark:bg-indigo-900/20': isActive(catIndex, itemIndex)}">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate" x-text="item.title"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                                          :class="item.meta_class + ' bg-opacity-10'"
                                          x-text="item.meta"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="item.subtitle"></p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        <!-- Footer with result count -->
        <div x-show="filteredResults.length > 0 && !loading" class="px-3 py-2 bg-gray-50 dark:bg-slate-900/50 border-t border-gray-200 dark:border-slate-700">
            <div class="flex items-center justify-between text-xs text-gray-400">
                <span x-text="filteredTotal + ' result' + (filteredTotal !== 1 ? 's' : '') + (activeFilter !== 'all' ? ' (filtered)' : '')"></span>
                <span class="flex items-center gap-1">
                    <kbd class="px-1 py-0.5 bg-gray-200 dark:bg-slate-700 rounded">↵</kbd>
                    to select
                </span>
            </div>
        </div>
    </div>

    <!-- Click outside to close -->
    <div x-show="showResults"
         @click="closeResults()"
         class="fixed inset-0 z-40"
         x-cloak></div>
</div>

<script>
function globalSearch() {
    return {
        query: '',
        results: [],
        total: 0,
        loading: false,
        searched: false,
        isFocused: false,
        showResults: false,
        activeCategory: 0,
        activeItem: 0,
        activeFilter: 'all',
        recentSearches: [],
        suggestions: [],

        // Filter mapping for categories
        filterMap: {
            'all': null,
            'devices': ['Devices', 'By Serial Number', 'By IP Address', 'By MAC Address', 'By Firmware'],
            'subscribers': ['Subscribers'],
            'tasks': ['Tasks'],
            'parameters': ['Parameters']
        },

        // Suggestions for common searches
        suggestionList: [
            'pending', 'failed', 'completed', 'reboot',
            'Calix', 'Nokia', 'SmartRG',
            '844E', 'Beacon', 'GS4220E',
            'online', 'offline'
        ],

        init() {
            // Load recent searches from localStorage
            this.loadRecentSearches();
        },

        get filteredResults() {
            if (this.activeFilter === 'all') {
                return this.results;
            }
            const allowedCategories = this.filterMap[this.activeFilter] || [];
            return this.results.filter(cat => allowedCategories.includes(cat.category));
        },

        get filteredTotal() {
            return this.filteredResults.reduce((sum, cat) => sum + cat.items.length, 0);
        },

        toggleFilter(filter) {
            this.activeFilter = filter;
            this.activeCategory = 0;
            this.activeItem = 0;
        },

        loadRecentSearches() {
            try {
                const stored = localStorage.getItem('hayacs_recent_searches');
                if (stored) {
                    this.recentSearches = JSON.parse(stored).slice(0, 5);
                }
            } catch (e) {
                console.warn('Failed to load recent searches:', e);
            }
        },

        saveRecentSearch() {
            if (this.query.length < 2) return;

            try {
                // Remove duplicates and add to front
                let searches = this.recentSearches.filter(s => s.toLowerCase() !== this.query.toLowerCase());
                searches.unshift(this.query);
                searches = searches.slice(0, 5);

                localStorage.setItem('hayacs_recent_searches', JSON.stringify(searches));
                this.recentSearches = searches;
            } catch (e) {
                console.warn('Failed to save recent search:', e);
            }
        },

        clearRecentSearches() {
            try {
                localStorage.removeItem('hayacs_recent_searches');
                this.recentSearches = [];
            } catch (e) {
                console.warn('Failed to clear recent searches:', e);
            }
        },

        useRecentSearch(search) {
            this.query = search;
            this.search();
        },

        useSuggestion(suggestion) {
            this.query = suggestion;
            this.search();
        },

        updateSuggestions() {
            if (this.query.length < 1) {
                this.suggestions = [];
                return;
            }

            const lowerQuery = this.query.toLowerCase();
            this.suggestions = this.suggestionList
                .filter(s => s.toLowerCase().startsWith(lowerQuery) && s.toLowerCase() !== lowerQuery)
                .slice(0, 5);
        },

        handleGlobalKeydown(e) {
            // / key to focus search (when not in an input)
            if (e.key === '/' && !this.isTypingInInput()) {
                e.preventDefault();
                this.$refs.searchInput.focus();
                return;
            }

            // Cmd/Ctrl + K to focus search
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.$refs.searchInput.focus();
            }
        },

        isTypingInInput() {
            const el = document.activeElement;
            if (!el) return false;
            const tag = el.tagName.toUpperCase();
            return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
        },

        onFocus() {
            this.isFocused = true;
            this.showResults = true;
            this.loadRecentSearches();
        },

        closeResults() {
            this.showResults = false;
            this.isFocused = false;
        },

        clearSearch() {
            this.query = '';
            this.results = [];
            this.searched = false;
            this.showResults = true;
            this.isFocused = true;
            this.activeFilter = 'all';
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
        },

        async search() {
            // Update suggestions for short queries
            this.updateSuggestions();

            if (this.query.length < 2) {
                this.results = [];
                this.searched = false;
                return;
            }

            this.loading = true;
            this.showResults = true;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(`/search?q=${encodeURIComponent(this.query)}&limit=15`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const text = await response.text();
                    console.error('Search failed:', response.status, text.substring(0, 200));
                    throw new Error(`Search failed: ${response.status}`);
                }

                const data = await response.json();
                this.results = data.results || [];
                this.total = data.total || 0;
                this.searched = true;
                this.activeCategory = 0;
                this.activeItem = 0;
            } catch (error) {
                console.error('Search error:', error);
                this.results = [];
                this.searched = true;
            } finally {
                this.loading = false;
            }
        },

        navigateDown() {
            if (this.filteredResults.length === 0) return;

            const currentCategory = this.filteredResults[this.activeCategory];
            if (this.activeItem < currentCategory.items.length - 1) {
                this.activeItem++;
            } else if (this.activeCategory < this.filteredResults.length - 1) {
                this.activeCategory++;
                this.activeItem = 0;
            }
            this.scrollToActive();
        },

        navigateUp() {
            if (this.filteredResults.length === 0) return;

            if (this.activeItem > 0) {
                this.activeItem--;
            } else if (this.activeCategory > 0) {
                this.activeCategory--;
                this.activeItem = this.filteredResults[this.activeCategory].items.length - 1;
            }
            this.scrollToActive();
        },

        selectResult() {
            if (this.filteredResults.length === 0) return;

            const category = this.filteredResults[this.activeCategory];
            if (category && category.items[this.activeItem]) {
                this.saveRecentSearch();
                window.location.href = category.items[this.activeItem].url;
            }
        },

        isActive(catIndex, itemIndex) {
            return this.activeCategory === catIndex && this.activeItem === itemIndex;
        },

        setActiveIndex(catIndex, itemIndex) {
            this.activeCategory = catIndex;
            this.activeItem = itemIndex;
        },

        scrollToActive() {
            this.$nextTick(() => {
                const container = this.$refs.resultsContainer;
                if (!container) return;
                const activeEl = container.querySelector('[class*="bg-indigo-50"], [class*="bg-blue-900"]');
                if (activeEl) {
                    activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        }
    }
}
</script>
