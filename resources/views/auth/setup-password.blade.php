<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Set Up Your Password') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Welcome, :name! Please create a secure password for your account.', ['name' => $user->name]) }}
        </p>
    </div>

    <form method="POST" action="{{ URL::temporarySignedRoute('password.setup.store', now()->addHour(), ['user' => $user->id]) }}">
        @csrf

        <!-- Password Requirements -->
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Password Requirements:</h3>
            <ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
                <li>• At least 12 characters long</li>
                <li>• Contains uppercase and lowercase letters</li>
                <li>• Contains at least one number</li>
                <li>• Contains at least one symbol (!@#$%^&* etc.)</li>
                <li>• Has not appeared in known data breaches</li>
            </ul>
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required
                          autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                          type="password"
                          name="password_confirmation"
                          required
                          autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>
                {{ __('Set Password & Continue') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
