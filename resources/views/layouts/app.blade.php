@php
    $theme = session('theme', 'standard');
    $themeConfig = config("themes.{$theme}", config('themes.standard'));
    $isDark = ($themeConfig['type'] ?? 'light') === 'dark';
    $colors = $themeConfig['colors'] ?? config('themes.standard.colors');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }

            /* Theme-specific styles */
            .theme-bg { background-color: var(--theme-bg); }
            .theme-card { background-color: var(--theme-card); }
            .theme-text { color: var(--theme-text); }
            .theme-btn-primary { @apply bg-{{ $colors['primary'] }}-600 hover:bg-{{ $colors['primary'] }}-700 text-white; }
            .theme-btn-secondary { @apply bg-{{ $colors['secondary'] }}-600 hover:bg-{{ $colors['secondary'] }}-700 text-white; }

            /* Loading spinner */
            .spinner {
                border: 3px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #fff;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Pulse animation for loading states */
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: .5; }
            }

            .pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        </style>
    </head>
    <body class="font-sans antialiased bg-{{ $colors['bg'] }} dark:bg-{{ $colors['bg'] }}">
        <!-- Global Loading Indicator -->
        <div x-data="{ loading: false }"
             x-on:start-loading.window="loading = true"
             x-on:stop-loading.window="loading = false"
             x-show="loading"
             x-cloak
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg p-8 flex flex-col items-center shadow-2xl">
                <div class="spinner mb-4"></div>
                <p class="text-{{ $colors['text'] }} dark:text-{{ $colors['text'] }} font-medium">Processing...</p>
            </div>
        </div>

        <div class="min-h-screen">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-{{ $colors['card'] }} shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center justify-between">
                            <div class="text-{{ $colors['text'] }} dark:text-{{ $colors['text'] }}">
                                {{ $header }}
                            </div>
                        </div>
                    </div>
                </header>
            @else
                @hasSection('header')
                    <header class="bg-white dark:bg-{{ $colors['card'] }} shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center justify-between">
                                <div class="text-{{ $colors['text'] }} dark:text-{{ $colors['text'] }}">
                                    @yield('header')
                                </div>
                            </div>
                        </div>
                    </header>
                @endif
            @endisset

            <!-- Page Content -->
            <main>
                <div class="py-12">
                    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 xl:px-12">
                        @isset($slot)
                            {{ $slot }}
                        @else
                            @yield('content')
                        @endisset
                    </div>
                </div>
            </main>
        </div>

        <!-- Alpine.js Helper for Loading States -->
        <script>
            // Global loading helper
            window.startLoading = function() {
                window.dispatchEvent(new CustomEvent('start-loading'));
            };

            window.stopLoading = function() {
                window.dispatchEvent(new CustomEvent('stop-loading'));
            };

            // Auto-attach to all fetch requests (except background polling)
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                // Check if this is a background poll (second argument contains headers)
                const options = args[1] || {};
                const skipLoading = options.headers && options.headers['X-Background-Poll'];

                if (!skipLoading) {
                    startLoading();
                }

                return originalFetch.apply(this, args)
                    .finally(() => {
                        if (!skipLoading) {
                            stopLoading();
                        }
                    });
            };
        </script>
    </body>
</html>
