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
                    @if(Auth::user()->isAdmin())
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
            @if(Auth::user()->isAdmin())
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
