{{-- Selective Restore Modal --}}
<div x-show="showSelectiveRestoreModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4" @click.self="showSelectiveRestoreModal = false">
    <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-purple-200 dark:border-purple-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-purple-900 dark:text-purple-300">Selective Restore</h3>
                    <p class="text-sm text-purple-700 dark:text-purple-400 mt-1" x-show="selectiveRestoreBackup">
                        Choose specific parameters to restore from: <span x-text="selectiveRestoreBackup?.name"></span>
                    </p>
                </div>
                <button @click="showSelectiveRestoreModal = false" class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Search and Controls -->
        <div class="px-6 py-3 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-b border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="flex items-center gap-3">
                <!-- Search Box -->
                <div class="flex-1">
                    <input type="text" x-model="parameterSearchQuery" placeholder="Search parameters..."
                           class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm">
                </div>
                <!-- Select/Deselect All -->
                <button @click="selectAllParameters()"
                        class="px-3 py-2 text-sm font-medium text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/30 rounded-md hover:bg-purple-200 dark:hover:bg-purple-900/50">
                    Select All
                </button>
                <button @click="deselectAllParameters()"
                        class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-gray-100 dark:bg-{{ $colors['bg'] }} rounded-md hover:bg-gray-200 dark:hover:bg-{{ $colors['border'] }}">
                    Deselect All
                </button>
                <!-- Counter -->
                <div class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md">
                    <span x-text="selectedParameters.length"></span> / <span x-text="filteredBackupParameters.length"></span> selected
                </div>
            </div>
        </div>

        <!-- Parameters List -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <template x-if="filteredBackupParameters.length === 0">
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No writable parameters found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Try adjusting your search query</p>
                </div>
            </template>

            <div class="space-y-2">
                <template x-for="param in filteredBackupParameters" :key="param.name">
                    <label class="flex items-start p-3 bg-white dark:bg-{{ $colors['card'] }} border border-gray-200 dark:border-{{ $colors['border'] }} rounded-md hover:bg-purple-50 dark:hover:bg-purple-900/10 hover:border-purple-300 dark:hover:border-purple-700 cursor-pointer transition-colors">
                        <input type="checkbox"
                               :checked="selectedParameters.includes(param.name)"
                               @change="toggleParameter(param.name)"
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 dark:border-{{ $colors['border'] }} rounded mt-1">
                        <div class="ml-3 flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-mono text-gray-900 dark:text-{{ $colors['text'] }} break-all" x-text="param.name"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-{{ $colors['bg'] }} text-gray-700 dark:text-{{ $colors['text-muted'] }}"
                                      x-text="param.type || 'string'"></span>
                            </div>
                            <div class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }} font-mono">
                                Value: <span x-text="param.value || '(empty)'"></span>
                            </div>
                        </div>
                    </label>
                </template>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-t border-gray-200 dark:border-{{ $colors['border'] }} flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                <svg class="inline h-5 w-5 text-blue-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                A safety backup will be created automatically before restore
            </div>
            <div class="flex gap-3">
                <button @click="showSelectiveRestoreModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Cancel
                </button>
                <button @click="executeSelectiveRestore()"
                        :disabled="selectedParameters.length === 0"
                        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    Restore <span x-text="selectedParameters.length"></span> Parameter<span x-show="selectedParameters.length !== 1">s</span>
                </button>
            </div>
        </div>
    </div>
</div>
