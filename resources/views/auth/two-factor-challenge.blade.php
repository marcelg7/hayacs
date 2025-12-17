<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Two-Factor Authentication') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Enter the 6-digit code from your authenticator app to continue.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication Code')" />
            <x-text-input
                id="code"
                class="block mt-1 w-full text-center text-2xl tracking-widest"
                type="text"
                name="code"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                autofocus
                required
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-4 space-y-3">
            <label for="remember_device" class="flex items-start">
                <input
                    id="remember_device"
                    type="checkbox"
                    name="remember_device"
                    value="1"
                    class="mt-0.5 rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                >
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Skip 2FA on this device for 48 days') }}
                </span>
            </label>

            @if(app(\App\Services\TrustedDeviceService::class)->isAllowedIp(request()->ip()))
            <label for="trust_device" class="flex items-start">
                <input
                    id="trust_device"
                    type="checkbox"
                    name="trust_device"
                    value="1"
                    class="mt-0.5 rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-green-600 shadow-sm focus:ring-green-500 dark:focus:ring-green-600 dark:focus:ring-offset-gray-800"
                >
                <div class="ms-2">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Trust this device for remote access (90 days)') }}
                    </span>
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                        Allows login from any IP without VPN. Skip 2FA included.
                    </p>
                </div>
            </label>
            @else
            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                    <strong>Remote Access:</strong> Connect via VPN to enable "Trust this device" for future access without VPN.
                </p>
            </div>
            @endif
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline">
                {{ __('Sign out and use a different account') }}
            </button>
        </form>
    </div>

    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            <strong>{{ __('Lost access?') }}</strong>
            {{ __('Contact your administrator to reset your two-factor authentication.') }}
        </p>
    </div>
</x-guest-layout>
