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

            @if($theme === 'purple-haze')
            /* Purple Haze Theme - Custom purple styling */
            body.purple-haze-theme {
                background: linear-gradient(135deg, #1e1033 0%, #2d1f4e 50%, #1a0d2e 100%) !important;
                background-attachment: fixed !important;
            }
            .purple-haze-theme .dark\:bg-slate-800,
            .purple-haze-theme .dark\:bg-gray-800 {
                background-color: rgba(88, 28, 135, 0.3) !important;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(168, 85, 247, 0.2);
            }
            .purple-haze-theme .dark\:bg-slate-900 {
                background-color: rgba(59, 7, 100, 0.5) !important;
            }
            .purple-haze-theme .dark\:bg-gray-700,
            .purple-haze-theme .dark\:bg-slate-700 {
                background-color: rgba(126, 34, 206, 0.3) !important;
            }
            .purple-haze-theme .dark\:border-slate-700,
            .purple-haze-theme .dark\:border-gray-700,
            .purple-haze-theme .dark\:border-gray-600 {
                border-color: rgba(168, 85, 247, 0.3) !important;
            }
            .purple-haze-theme .dark\:text-gray-100,
            .purple-haze-theme .dark\:text-slate-100 {
                color: #f3e8ff !important;
            }
            .purple-haze-theme .dark\:text-gray-400,
            .purple-haze-theme .dark\:text-slate-400 {
                color: #c4b5fd !important;
            }
            .purple-haze-theme a:not(.no-purple-link) {
                color: #c084fc;
            }
            .purple-haze-theme a:not(.no-purple-link):hover {
                color: #e9d5ff;
            }
            .purple-haze-theme .bg-blue-600,
            .purple-haze-theme .bg-indigo-600 {
                background-color: #7c3aed !important;
            }
            .purple-haze-theme .bg-blue-600:hover,
            .purple-haze-theme .bg-indigo-600:hover,
            .purple-haze-theme .hover\:bg-blue-700:hover,
            .purple-haze-theme .hover\:bg-indigo-700:hover {
                background-color: #6d28d9 !important;
            }
            .purple-haze-theme input,
            .purple-haze-theme select,
            .purple-haze-theme textarea {
                background-color: rgba(88, 28, 135, 0.4) !important;
                border-color: rgba(168, 85, 247, 0.4) !important;
                color: #f3e8ff !important;
            }
            .purple-haze-theme input:focus,
            .purple-haze-theme select:focus,
            .purple-haze-theme textarea:focus {
                border-color: #a855f7 !important;
                box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3) !important;
            }
            .purple-haze-theme ::-webkit-scrollbar-track {
                background: #1e1033;
            }
            .purple-haze-theme ::-webkit-scrollbar-thumb {
                background: #7c3aed;
            }
            .purple-haze-theme ::-webkit-scrollbar-thumb:hover {
                background: #a855f7;
            }
            @endif

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

            /* Dark mode form inputs */
            .dark input[type="text"],
            .dark input[type="email"],
            .dark input[type="password"],
            .dark input[type="number"],
            .dark input[type="search"],
            .dark input[type="tel"],
            .dark input[type="url"],
            .dark input[type="date"],
            .dark input[type="datetime-local"],
            .dark select,
            .dark textarea {
                background-color: #1e293b;
                border-color: #475569;
                color: #e2e8f0;
            }

            .dark input::placeholder,
            .dark textarea::placeholder {
                color: #64748b;
            }

            .dark input:focus,
            .dark select:focus,
            .dark textarea:focus {
                border-color: #3b82f6;
                ring-color: #3b82f6;
            }

            /* Dark mode scrollbar */
            .dark ::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            .dark ::-webkit-scrollbar-track {
                background: #1e293b;
            }

            .dark ::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 4px;
            }

            .dark ::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
        </style>
    </head>
    <body class="font-sans antialiased {{ $isDark ? 'bg-slate-900 text-slate-100' : 'bg-gray-50 text-gray-900' }} {{ $theme === 'purple-haze' ? 'purple-haze-theme' : '' }}">
        <!-- Global Loading Indicator (with delay to prevent flash) -->
        <div x-data="{
                loading: false,
                showOverlay: false,
                timeout: null,
                startLoading() {
                    this.loading = true;
                    // Only show overlay after 300ms to prevent flash on quick requests
                    this.timeout = setTimeout(() => {
                        if (this.loading) this.showOverlay = true;
                    }, 300);
                },
                stopLoading() {
                    this.loading = false;
                    this.showOverlay = false;
                    if (this.timeout) {
                        clearTimeout(this.timeout);
                        this.timeout = null;
                    }
                }
             }"
             x-on:start-loading.window="startLoading()"
             x-on:stop-loading.window="stopLoading()"
             x-show="showOverlay"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-cloak
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-8 flex flex-col items-center shadow-2xl">
                <div class="spinner mb-4"></div>
                <p class="text-gray-900 dark:text-gray-100 font-medium">Processing...</p>
            </div>
        </div>

        <div class="min-h-screen">
            @include('layouts.navigation')

            <!-- 2FA Grace Period Banner -->
            <x-two-factor-grace-banner />

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-slate-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center justify-between">
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $header }}
                            </div>
                        </div>
                    </div>
                </header>
            @else
                @hasSection('header')
                    <header class="bg-white dark:bg-slate-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center justify-between">
                                <div class="text-gray-900 dark:text-gray-100">
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

        <!-- Page-specific scripts -->
        @stack('scripts')
    </body>
</html>
