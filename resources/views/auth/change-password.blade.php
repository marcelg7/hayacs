<x-guest-layout>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <div class="mb-4">
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Change Your Password') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('You must change your temporary password before continuing.') }}
            </p>
        </div>

        @if (session('warning'))
            <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
                {{ session('warning') }}
            </div>
        @endif

        <!-- Current Password -->
        <div class="mt-4">
            <x-input-label for="current_password" :value="__('Current Password')" />
            <x-text-input id="current_password" class="block mt-1 w-full"
                          type="password"
                          name="current_password"
                          required
                          autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <!-- New Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('New Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required
                          autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-600">
                Password must be at least 8 characters long.
            </p>
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
                {{ __('Change Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
