{{-- Import Backup Modal --}}
<div x-show="showImportModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center" @click.self="closeImportModal()">
    <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Import Backup</h3>
            <button @click="closeImportModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-{{ $colors['text'] }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} mb-4">
            Upload a previously exported backup file to restore it to this device.
        </p>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-2">
                    Backup File (JSON)
                </label>
                <input type="file" accept=".json,application/json"
                       @change="importFile = $event.target.files[0]"
                       class="block w-full text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-2">
                    Backup Name (Optional)
                </label>
                <input type="text" x-model="importName" placeholder="Leave empty to use original name"
                       class="block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['bg'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
        </div>
        <div class="mt-6 flex space-x-3">
            <button @click="closeImportModal()"
                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                Cancel
            </button>
            <button @click="importBackup()" :disabled="!importFile || loading"
                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Import
            </button>
        </div>
    </div>
</div>
