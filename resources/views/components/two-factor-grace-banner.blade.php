@auth
    @if(!auth()->user()->hasTwoFactorEnabled() && auth()->user()->isInTwoFactorGracePeriod())
        @php
            $daysRemaining = auth()->user()->getTwoFactorGraceDaysRemaining();
        @endphp
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-100 dark:border-yellow-800">
            <div class="max-w-7xl mx-auto py-3 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap">
                    <div class="flex-1 flex items-center">
                        <span class="flex p-2 rounded-lg bg-yellow-100 dark:bg-yellow-800">
                            <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </span>
                        <p class="ml-3 text-sm text-yellow-700 dark:text-yellow-200">
                            <strong>Two-factor authentication required:</strong>
                            <span class="inline">
                                {{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }} remaining to set up 2FA.
                            </span>
                        </p>
                    </div>
                    <div class="flex-shrink-0 mt-2 sm:mt-0 sm:ml-3">
                        <a href="{{ route('two-factor.setup') }}" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-yellow-800 bg-yellow-200 hover:bg-yellow-300 dark:text-yellow-200 dark:bg-yellow-800 dark:hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                            Set up now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth
