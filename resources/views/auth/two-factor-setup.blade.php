<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Set Up Two-Factor Authentication') }}
        </h2>

        @if($graceExpired)
            <div class="mt-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-700 dark:text-red-300">
                    <strong>{{ __('Required:') }}</strong>
                    {{ __('Your 14-day grace period has expired. You must set up two-factor authentication to continue using the system.') }}
                </p>
            </div>
        @else
            <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    <strong>{{ $graceDaysRemaining }} {{ Str::plural('day', $graceDaysRemaining) }}</strong>
                    {{ __('remaining to set up two-factor authentication.') }}
                </p>
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <!-- Step 1: Install App -->
        <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-100">
                {{ __('Step 1: Install an Authenticator App') }}
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Download one of these apps on your phone:') }}
            </p>
            <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside">
                <li>Google Authenticator</li>
                <li>Microsoft Authenticator</li>
                <li>Authy</li>
                <li>1Password</li>
            </ul>
        </div>

        <!-- Step 2: Scan QR Code -->
        <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-100">
                {{ __('Step 2: Scan This QR Code') }}
            </h3>
            <div class="mt-3 flex justify-center">
                <div class="p-4 bg-white rounded-lg">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            <!-- Manual entry fallback -->
            <details class="mt-3">
                <summary class="text-sm text-gray-600 dark:text-gray-400 cursor-pointer hover:text-gray-900 dark:hover:text-gray-100">
                    {{ __("Can't scan? Enter code manually") }}
                </summary>
                <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        {{ __('Enter this code in your authenticator app:') }}
                    </p>
                    <code class="block p-2 bg-white dark:bg-gray-800 rounded font-mono text-sm break-all select-all">
                        {{ $secret }}
                    </code>
                </div>
            </details>
        </div>

        <!-- Step 3: Verify -->
        <div>
            <h3 class="font-medium text-gray-900 dark:text-gray-100">
                {{ __('Step 3: Verify Setup') }}
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Enter the 6-digit code from your authenticator app to complete setup.') }}
            </p>

            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-3">
                @csrf

                <div>
                    <x-input-label for="code" :value="__('Verification Code')" />
                    <x-text-input
                        id="code"
                        class="block mt-1 w-full text-center text-2xl tracking-widest"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between mt-4">
                    @if(!$graceExpired)
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline">
                            {{ __('Set up later') }}
                        </a>
                    @else
                        <span></span>
                    @endif

                    <x-primary-button>
                        {{ __('Enable Two-Factor Authentication') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
