@php
    $theme = session('theme', 'standard');
    $themeConfig = config("themes.{$theme}", config('themes.standard'));
    $colors = $themeConfig['colors'] ?? config('themes.standard.colors');
@endphp
<nav x-data="{ open: false }" class="bg-white dark:bg-{{ $colors['card'] ?? 'gray-800' }} border-b border-{{ $colors['border'] ?? 'gray-200' }} shadow-lg">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/hay-logo.png') }}" alt="Hay Communications" class="h-12 w-auto">
                        <span class="text-xl font-bold text-{{ $colors['primary'] ?? 'indigo' }}-600 dark:text-{{ $colors['primary'] ?? 'indigo' }}-400">Hay ACS</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('devices.index')" :active="request()->routeIs('devices.*') || request()->routeIs('device.show')">
                        {{ __('Devices') }}
                    </x-nav-link>
                    <x-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">
                        {{ __('Analytics') }}
                    </x-nav-link>
                    <x-nav-link :href="route('subscribers.index')" :active="request()->routeIs('subscribers.*')">
                        {{ __('Subscribers') }}
                    </x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        {{ __('Reports') }}
                    </x-nav-link>
                    @if(Auth::user()->isAdmin())
                        {{-- Automation Dropdown (Groups & Workflows) --}}
                        <div class="hidden sm:flex sm:items-center" x-data="{ open: false }">
                            <div class="relative">
                                <button @click="open = !open" @click.away="open = false"
                                    class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                                    {{ request()->routeIs('device-groups.*') || request()->routeIs('workflows.*')
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
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('Users') }}
                        </x-nav-link>
                        <x-nav-link :href="route('device-types.index')" :active="request()->routeIs('device-types.*') || request()->routeIs('firmware.*')">
                            {{ __('Device Types') }}
                        </x-nav-link>
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
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-{{ $colors['text-muted'] ?? 'gray-500' }} dark:text-{{ $colors['text-muted'] ?? 'gray-400' }} bg-white dark:bg-{{ $colors['card'] ?? 'gray-800' }} hover:text-{{ $colors['text'] ?? 'gray-700' }} dark:hover:text-{{ $colors['text'] ?? 'gray-300' }} focus:outline-none transition ease-in-out duration-150">
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
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-{{ $colors['text-muted'] ?? 'gray-400' }} dark:text-{{ $colors['text-muted'] ?? 'gray-500' }} hover:text-{{ $colors['text'] ?? 'gray-500' }} dark:hover:text-{{ $colors['text'] ?? 'gray-400' }} hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-{{ $colors['text'] ?? 'gray-500' }} dark:focus:text-{{ $colors['text'] ?? 'gray-400' }} transition duration-150 ease-in-out">
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
            <x-responsive-nav-link :href="route('devices.index')" :active="request()->routeIs('devices.*') || request()->routeIs('device.show')">
                {{ __('Devices') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">
                {{ __('Analytics') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('subscribers.index')" :active="request()->routeIs('subscribers.*')">
                {{ __('Subscribers') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                {{ __('Reports') }}
            </x-responsive-nav-link>
            @if(Auth::user()->isAdmin())
                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                    <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Automation</div>
                </div>
                <x-responsive-nav-link :href="route('device-groups.index')" :active="request()->routeIs('device-groups.*')">
                    {{ __('Device Groups') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('workflows.index')" :active="request()->routeIs('workflows.*')">
                    {{ __('Workflows') }}
                </x-responsive-nav-link>
                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                    <div class="px-4 py-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Admin</div>
                </div>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('device-types.index')" :active="request()->routeIs('device-types.*') || request()->routeIs('firmware.*')">
                    {{ __('Device Types') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-{{ $colors['border'] ?? 'gray-200' }} dark:border-{{ $colors['border'] ?? 'gray-600' }}">
            <div class="px-4">
                <div class="font-medium text-base text-{{ $colors['text'] ?? 'gray-800' }} dark:text-{{ $colors['text'] ?? 'gray-200' }}">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-{{ $colors['text-muted'] ?? 'gray-500' }}">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
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
