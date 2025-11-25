{{-- Backup Row Partial --}}
<div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} border-b border-gray-100 dark:border-{{ $colors['border'] }}">
    <div class="flex items-center justify-between">
        <input type="checkbox" @click="toggleCompareSelection(backup)" :checked="isSelectedForComparison(backup)"
               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 dark:border-{{ $colors['border'] }} rounded mr-4 flex-shrink-0">
        <div class="flex-1">
            <h5 class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="backup.name"></h5>
            <p x-show="backup.description" class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="backup.description"></p>
            <div class="mt-2 flex items-center text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }} space-x-4">
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span x-text="backup.created_at"></span>
                </span>
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                    </svg>
                    <span x-text="backup.parameter_count + ' parameters'"></span>
                </span>
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span x-text="backup.size"></span>
                </span>
            </div>
        </div>
        <div class="ml-4 flex space-x-2">
            <button @click="downloadBackup(backup.id)"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Download
            </button>
            <button @click="openSelectiveRestore(backup)"
                    class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 dark:text-purple-300 bg-white dark:bg-{{ $colors['card'] }} hover:bg-purple-50 dark:hover:bg-purple-900/20">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                Selective
            </button>
            <button @click="confirmRestore(backup)"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Restore All
            </button>
        </div>
    </div>
</div>
