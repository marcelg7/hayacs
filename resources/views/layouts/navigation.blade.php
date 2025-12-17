{{-- Admin Server Status Bar (Mobile-friendly) --}}
@auth
@if(Auth::user()->isAdmin())
<div x-data="serverStatus()" x-init="fetchStatus(); setInterval(() => fetchStatus(), 30000)"
     class="bg-gray-900 text-gray-300 text-xs py-1.5 px-2 sm:px-4">
    {{-- Mobile: Compact single row with key stats --}}
    <div class="flex items-center justify-between sm:hidden">
        <div class="flex items-center space-x-3">
            <span class="flex items-center">
                <span class="text-gray-500">L:</span>
                <span x-text="load1" :class="load1 > 10 ? 'text-red-400' : load1 > 5 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
            </span>
            <span class="flex items-center">
                <span class="text-gray-500">M:</span>
                <span x-text="memoryPercent + '%'" :class="memoryPercent > 90 ? 'text-red-400' : memoryPercent > 80 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
            </span>
            <span class="flex items-center">
                <span class="text-gray-500">Q:</span>
                <span x-text="queuePending" :class="queuePending > 100 ? 'text-yellow-400' : 'text-gray-400'" class="ml-1 font-mono"></span>
            </span>
            <span class="flex items-center">
                <span class="text-gray-500">D:</span>
                <span x-text="diskPercent + '%'" :class="diskPercent > 90 ? 'text-red-400' : diskPercent > 80 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
            </span>
        </div>
        <span x-text="uptime" class="font-mono text-gray-400 text-xs"></span>
    </div>
    {{-- Desktop: Full status bar --}}
    <div class="hidden sm:flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>
                </svg>
                <span class="text-gray-500">Load:</span>
                <span x-text="load1" :class="load1 > 10 ? 'text-red-400' : load1 > 5 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
                <span class="text-gray-600">/</span>
                <span x-text="load5" class="font-mono text-gray-400"></span>
                <span class="text-gray-600">/</span>
                <span x-text="load15" class="font-mono text-gray-400"></span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-gray-500">Uptime:</span>
                <span x-text="uptime" class="ml-1 font-mono text-gray-300"></span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="text-gray-500">Tasks:</span>
                <span x-text="tasksPending" class="ml-1 font-mono text-yellow-400"></span>
                <span class="text-gray-500 ml-1">pending</span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                </svg>
                <span class="text-gray-500">Disk:</span>
                <span x-text="diskUsedGb + '/' + diskTotalGb + 'GB'" class="ml-1 font-mono text-gray-400"></span>
                <span x-text="'(' + diskPercent + '%)'" :class="diskPercent > 90 ? 'text-red-400' : diskPercent > 80 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                </svg>
                <span class="text-gray-500">MySQL:</span>
                <span x-text="mysqlThreads + ' conn'" class="ml-1 font-mono text-gray-400"></span>
                <span class="text-gray-500 mx-1">/</span>
                <span x-text="mysqlQps + ' qps'" class="font-mono text-gray-400"></span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                </svg>
                <span class="text-gray-500">Mem:</span>
                <span x-text="memoryUsedMb + '/' + memoryTotalMb + 'MB'" class="ml-1 font-mono text-gray-400"></span>
                <span x-text="'(' + memoryPercent + '%)'" :class="memoryPercent > 90 ? 'text-red-400' : memoryPercent > 80 ? 'text-yellow-400' : 'text-green-400'" class="ml-1 font-mono"></span>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <span class="text-gray-500">Queue:</span>
                <span x-text="queuePending" :class="queuePending > 100 ? 'text-yellow-400' : 'text-gray-400'" class="ml-1 font-mono"></span>
                <template x-if="queueFailed > 0">
                    <span class="text-red-400 font-mono ml-1">(<span x-text="queueFailed"></span> failed)</span>
                </template>
            </span>
            <span class="text-gray-600">|</span>
            <span class="flex items-center">
                <svg class="w-3 h-3 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span class="text-gray-500">Cache:</span>
                <span x-text="cacheEntries" class="ml-1 font-mono text-gray-400"></span>
                <span x-text="'(' + cacheSizeKb + 'KB)'" class="ml-1 font-mono text-gray-500"></span>
            </span>
        </div>
        <div class="flex items-center space-x-2 text-gray-500">
            <span x-text="lastUpdate" class="text-xs"></span>
        </div>
    </div>
</div>
<script>
function serverStatus() {
    return {
        load1: '-',
        load5: '-',
        load15: '-',
        uptime: '-',
        tasksPending: '-',
        diskUsedGb: '-',
        diskTotalGb: '-',
        diskPercent: 0,
        mysqlThreads: '-',
        mysqlQps: '-',
        memoryUsedMb: '-',
        memoryTotalMb: '-',
        memoryPercent: 0,
        queuePending: 0,
        queueFailed: 0,
        cacheEntries: 0,
        cacheSizeKb: 0,
        lastUpdate: '',
        async fetchStatus() {
            try {
                const response = await fetch('/admin/system-status', {
                    headers: { 'X-Background-Poll': 'true' }
                });
                const data = await response.json();
                this.load1 = data.load1;
                this.load5 = data.load5;
                this.load15 = data.load15;
                this.uptime = data.uptime;
                this.tasksPending = data.tasks_pending;
                this.diskUsedGb = data.disk_used_gb;
                this.diskTotalGb = data.disk_total_gb;
                this.diskPercent = data.disk_percent;
                this.mysqlThreads = data.mysql_threads;
                this.mysqlQps = data.mysql_qps;
                this.memoryUsedMb = data.memory_used_mb;
                this.memoryTotalMb = data.memory_total_mb;
                this.memoryPercent = data.memory_percent;
                this.queuePending = data.queue_pending;
                this.queueFailed = data.queue_failed;
                this.cacheEntries = data.cache_entries;
                this.cacheSizeKb = data.cache_size_kb;
                this.lastUpdate = new Date().toLocaleTimeString();
            } catch (e) {
                console.error('Failed to fetch server status', e);
            }
        }
    }
}
</script>
@endif
@endauth

<nav x-data="{ open: false }" class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 shadow-lg">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/hay-logo.png') }}" alt="Hay Communications" class="h-12 w-auto">
                        <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">Hay ACS</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    {{-- Devices Dropdown --}}
                    <div class="hidden sm:flex sm:items-center" x-data="{ open: false }">
                        <div class="relative">
                            <button @click="open = !open" @click.away="open = false"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                                {{ request()->routeIs('devices.*') || request()->routeIs('device.show') || request()->routeIs('device-types.*') || request()->routeIs('firmware.*')
                                    ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100 focus:border-indigo-700'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700' }}">
                                {{ __('Devices') }}
                                <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute left-0 z-50 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5"
                                style="display: none;">
                                <div class="py-1">
                                    <a href="{{ route('devices.index') }}"
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('devices.*') || request()->routeIs('device.show') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                            </svg>
                                            {{ __('All Devices') }}
                                        </div>
                                    </a>
                                    @if(Auth::user()->isAdmin())
                                    <a href="{{ route('device-types.index') }}"
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('device-types.*') || request()->routeIs('firmware.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                            </svg>
                                            {{ __('Device Types') }}
                                        </div>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <x-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">
                        {{ __('Analytics') }}
                    </x-nav-link>
                    <x-nav-link :href="route('docs.index')" :active="request()->routeIs('docs.*')">
                        {{ __('Docs') }}
                    </x-nav-link>
                    <x-nav-link :href="route('subscribers.index')" :active="request()->routeIs('subscribers.*')">
                        {{ __('Subscribers') }}
                    </x-nav-link>
                    @if(Auth::user()->isAdmin())
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                            {{ __('Reports') }}
                        </x-nav-link>
                    @endif
                    @if(Auth::user()->isAdmin())
                        {{-- Automation Dropdown (Groups, Workflows & Tasks) --}}
                        <div class="hidden sm:flex sm:items-center" x-data="{ open: false }">
                            <div class="relative">
                                <button @click="open = !open" @click.away="open = false"
                                    class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                                    {{ request()->routeIs('device-groups.*') || request()->routeIs('workflows.*') || request()->routeIs('admin.tasks.*')
                                        ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100 focus:border-indigo-700'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700' }}">
                                    {{ __('Automation') }}
                                    <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute left-0 z-50 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5"
                                    style="display: none;">
                                    <div class="py-1">
                                        <a href="{{ route('admin.tasks.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('admin.tasks.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                                </svg>
                                                {{ __('Tasks') }}
                                            </div>
                                        </a>
                                        <a href="{{ route('device-groups.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('device-groups.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                                </svg>
                                                {{ __('Device Groups') }}
                                            </div>
                                        </a>
                                        <a href="{{ route('workflows.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('workflows.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                                {{ __('Workflows') }}
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Users Dropdown (Users & Trusted Devices) --}}
                        <div class="hidden sm:flex sm:items-center" x-data="{ open: false }">
                            <div class="relative">
                                <button @click="open = !open" @click.away="open = false"
                                    class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                                    {{ request()->routeIs('users.*') || request()->routeIs('admin.trusted-devices.*')
                                        ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100 focus:border-indigo-700'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700' }}">
                                    {{ __('Users') }}
                                    <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute left-0 z-50 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5"
                                    style="display: none;">
                                    <div class="py-1">
                                        <a href="{{ route('users.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('users.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                                </svg>
                                                {{ __('User Management') }}
                                            </div>
                                        </a>
                                        <a href="{{ route('admin.trusted-devices.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 {{ request()->routeIs('admin.trusted-devices.*') ? 'bg-gray-100 dark:bg-gray-600' : '' }}">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                                </svg>
                                                {{ __('Trusted Devices') }}
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 space-x-4">
                <!-- Global Search -->
                <x-global-search />

                <!-- Theme Switcher -->
                <x-theme-switcher />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-slate-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('feedback.index')">
                            {{ __('Feedback') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-slate-700 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            {{-- Devices Section --}}
            <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Devices</div>
            </div>
            <x-responsive-nav-link :href="route('devices.index')" :active="request()->routeIs('devices.*') || request()->routeIs('device.show')">
                {{ __('All Devices') }}
            </x-responsive-nav-link>
            @if(Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('device-types.index')" :active="request()->routeIs('device-types.*') || request()->routeIs('firmware.*')">
                    {{ __('Device Types') }}
                </x-responsive-nav-link>
            @endif
            {{-- General Section --}}
            <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">General</div>
            </div>
            <x-responsive-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">
                {{ __('Analytics') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('docs.index')" :active="request()->routeIs('docs.*')">
                {{ __('Docs') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('subscribers.index')" :active="request()->routeIs('subscribers.*')">
                {{ __('Subscribers') }}
            </x-responsive-nav-link>
            @if(Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>
            @endif
            @if(Auth::user()->isAdmin())
                {{-- Automation Section --}}
                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                    <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Automation</div>
                </div>
                <x-responsive-nav-link :href="route('admin.tasks.index')" :active="request()->routeIs('admin.tasks.*')">
                    {{ __('Tasks') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('device-groups.index')" :active="request()->routeIs('device-groups.*')">
                    {{ __('Device Groups') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('workflows.index')" :active="request()->routeIs('workflows.*')">
                    {{ __('Workflows') }}
                </x-responsive-nav-link>
                {{-- Users Section --}}
                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                    <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Users</div>
                </div>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    {{ __('User Management') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.trusted-devices.index')" :active="request()->routeIs('admin.trusted-devices.*')">
                    {{ __('Trusted Devices') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-slate-700">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('feedback.index')">
                    {{ __('Feedback') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
