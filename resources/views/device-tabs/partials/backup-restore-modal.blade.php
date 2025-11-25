{{-- Restore Confirmation Modal --}}
<div x-show="showRestoreConfirm" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center" @click.self="showRestoreConfirm = false">
    <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="ml-4 text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Restore Configuration?</h3>
        </div>
        <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} mb-4">
            This will restore the device configuration to the state saved in this backup. All writable parameters will be updated.
        </p>
        <p x-show="selectedBackup" class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }} mb-4">
            Backup: <span x-text="selectedBackup?.name"></span>
        </p>
        <div class="flex space-x-3">
            <button @click="showRestoreConfirm = false"
                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                Cancel
            </button>
            <button @click="restoreBackup()"
                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700">
                Restore
            </button>
        </div>
    </div>
</div>
