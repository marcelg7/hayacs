{{-- Comparison Modal --}}
<div x-show="showComparisonModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4" @click.self="closeComparisonModal()">
    <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-purple-200 dark:border-purple-800 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-purple-900 dark:text-purple-300">Backup Comparison</h3>
                <p class="mt-1 text-sm text-purple-700 dark:text-purple-400">Comparing configuration differences between two backups</p>
            </div>
            <button @click="closeComparisonModal()" class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Backup Info Header -->
        <div x-show="comparisonData" class="px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-b border-gray-200 dark:border-{{ $colors['border'] }} grid grid-cols-2 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                <div class="text-xs font-semibold text-blue-900 dark:text-blue-300 uppercase tracking-wide mb-1">Backup 1 (Older)</div>
                <div class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="comparisonData?.backup1.name"></div>
                <div class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }} mt-1" x-text="comparisonData?.backup1.created_at"></div>
                <div class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }} mt-1" x-text="comparisonData?.backup1.parameter_count + ' parameters'"></div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                <div class="text-xs font-semibold text-green-900 dark:text-green-300 uppercase tracking-wide mb-1">Backup 2 (Newer)</div>
                <div class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="comparisonData?.backup2.name"></div>
                <div class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }} mt-1" x-text="comparisonData?.backup2.created_at"></div>
                <div class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }} mt-1" x-text="comparisonData?.backup2.parameter_count + ' parameters'"></div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div x-show="comparisonData" class="px-6 py-3 bg-white dark:bg-{{ $colors['card'] }} border-b border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="flex items-center space-x-6 text-sm">
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        <span x-text="comparisonData?.summary.added_count"></span> Added
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                        <span x-text="comparisonData?.summary.removed_count"></span> Removed
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                        <span x-text="comparisonData?.summary.modified_count"></span> Modified
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-{{ $colors['bg'] }} dark:text-{{ $colors['text'] }}">
                        <span x-text="comparisonData?.summary.unchanged_count"></span> Unchanged
                    </span>
                </div>
            </div>
        </div>

        <!-- Changes Content (Scrollable) -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <template x-if="comparisonData">
                <div class="space-y-6">
                    <!-- Modified Parameters -->
                    <div x-show="Object.keys(comparisonData.comparison.modified).length > 0">
                        <h4 class="text-sm font-semibold text-yellow-900 dark:text-yellow-300 mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Modified Parameters (<span x-text="Object.keys(comparisonData.comparison.modified).length"></span>)
                        </h4>
                        <div class="space-y-2">
                            <template x-for="[name, param] in Object.entries(comparisonData.comparison.modified)" :key="name">
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                                    <div class="text-xs font-mono text-gray-700 dark:text-{{ $colors['text-muted'] }} mb-2" x-text="name"></div>
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded px-3 py-2">
                                            <div class="text-xs font-semibold text-red-900 dark:text-red-300 uppercase mb-1">Old Value</div>
                                            <div class="text-xs font-mono text-gray-800 dark:text-{{ $colors['text'] }} break-all" x-text="param.old_value"></div>
                                        </div>
                                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded px-3 py-2">
                                            <div class="text-xs font-semibold text-green-900 dark:text-green-300 uppercase mb-1">New Value</div>
                                            <div class="text-xs font-mono text-gray-800 dark:text-{{ $colors['text'] }} break-all" x-text="param.new_value"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Added Parameters -->
                    <div x-show="Object.keys(comparisonData.comparison.added).length > 0">
                        <h4 class="text-sm font-semibold text-green-900 dark:text-green-300 mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Added Parameters (<span x-text="Object.keys(comparisonData.comparison.added).length"></span>)
                        </h4>
                        <div class="space-y-2">
                            <template x-for="[name, param] in Object.entries(comparisonData.comparison.added)" :key="name">
                                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                    <div class="text-xs font-mono text-gray-700 dark:text-{{ $colors['text-muted'] }} mb-1" x-text="name"></div>
                                    <div class="text-xs font-mono text-gray-800 dark:text-{{ $colors['text'] }} break-all" x-text="param.value"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Removed Parameters -->
                    <div x-show="Object.keys(comparisonData.comparison.removed).length > 0">
                        <h4 class="text-sm font-semibold text-red-900 dark:text-red-300 mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                            Removed Parameters (<span x-text="Object.keys(comparisonData.comparison.removed).length"></span>)
                        </h4>
                        <div class="space-y-2">
                            <template x-for="[name, param] in Object.entries(comparisonData.comparison.removed)" :key="name">
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                    <div class="text-xs font-mono text-gray-700 dark:text-{{ $colors['text-muted'] }} mb-1" x-text="name"></div>
                                    <div class="text-xs font-mono text-gray-800 dark:text-{{ $colors['text'] }} break-all" x-text="param.value"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- No Changes Message -->
                    <div x-show="comparisonData.summary.added_count === 0 && comparisonData.summary.removed_count === 0 && comparisonData.summary.modified_count === 0"
                         class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No Differences Found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">These backups are identical</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-t border-gray-200 dark:border-{{ $colors['border'] }} flex justify-end">
            <button @click="closeComparisonModal()"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                Close
            </button>
        </div>
    </div>
</div>
