{{-- Config Backups Tab --}}
<div x-show="activeTab === 'backups'" x-cloak x-data="{
    backups: [],
    loading: true,
    selectedBackup: null,
    showRestoreConfirm: false,
    selectedForComparison: [],
    showComparisonModal: false,
    comparisonData: null,
    showImportModal: false,
    importFile: null,
    importName: '',
    showSelectiveRestoreModal: false,
    selectiveRestoreBackup: null,
    selectedParameters: [],
    parameterSearchQuery: '',

    get initialBackups() {
        return this.backups.filter(b =>
            b.description && b.description.includes('first TR-069 connection')
        );
    },

    get userBackups() {
        return this.backups.filter(b =>
            !b.is_auto &&
            (!b.description || !b.description.includes('first TR-069 connection'))
        );
    },

    get autoBackups() {
        return this.backups.filter(b =>
            b.is_auto &&
            (!b.description || !b.description.includes('first TR-069 connection'))
        );
    },

    async loadBackups() {
        this.loading = true;
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/backups');
            const data = await response.json();
            this.backups = data.backups;
        } catch (error) {
            console.error('Error loading backups:', error);
            alert('Error loading backups: ' + error.message);
        } finally {
            this.loading = false;
        }
    },

    async createBackup() {
        if (this.loading) return;

        const name = prompt('Enter a name for this backup (optional):');
        if (name === null) return;

        this.loading = true;
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/backups', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name: name || undefined,
                    description: 'Manual backup created via UI'
                })
            });

            const data = await response.json();
            if (response.ok) {
                alert(data.message);
                await this.loadBackups();
            } else {
                alert('Error creating backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error creating backup:', error);
            alert('Error creating backup: ' + error.message);
        } finally {
            this.loading = false;
        }
    },

    confirmRestore(backup) {
        this.selectedBackup = backup;
        this.showRestoreConfirm = true;
    },

    async restoreBackup() {
        if (!this.selectedBackup) return;

        this.showRestoreConfirm = false;
        taskLoading = true;
        taskMessage = 'Restoring Configuration...';

        try {
            const response = await fetch(`/api/devices/{{ $device->id }}/backups/${this.selectedBackup.id}/restore`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const data = await response.json();
            if (response.ok && data.task) {
                startTaskTracking('Restoring Configuration...', data.task.id);
            } else {
                taskLoading = false;
                alert('Error restoring backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error restoring backup:', error);
            alert('Error restoring backup: ' + error.message);
        }
    },

    openSelectiveRestore(backup) {
        this.selectiveRestoreBackup = backup;
        this.selectedParameters = [];
        this.parameterSearchQuery = '';
        this.showSelectiveRestoreModal = true;
    },

    get filteredBackupParameters() {
        if (!this.selectiveRestoreBackup || !this.selectiveRestoreBackup.backup_data) return [];

        const query = this.parameterSearchQuery.toLowerCase();
        return Object.entries(this.selectiveRestoreBackup.backup_data)
            .filter(([name, data]) => {
                if (query && !name.toLowerCase().includes(query)) {
                    return false;
                }
                return data.writable ?? false;
            })
            .map(([name, data]) => ({ name, ...data }))
            .sort((a, b) => a.name.localeCompare(b.name));
    },

    toggleParameter(paramName) {
        const index = this.selectedParameters.indexOf(paramName);
        if (index > -1) {
            this.selectedParameters.splice(index, 1);
        } else {
            this.selectedParameters.push(paramName);
        }
    },

    selectAllParameters() {
        this.selectedParameters = this.filteredBackupParameters.map(p => p.name);
    },

    deselectAllParameters() {
        this.selectedParameters = [];
    },

    async executeSelectiveRestore() {
        if (!this.selectiveRestoreBackup || this.selectedParameters.length === 0) return;

        this.showSelectiveRestoreModal = false;
        taskLoading = true;
        taskMessage = 'Restoring Selected Parameters...';

        try {
            const response = await fetch(`/api/devices/{{ $device->id }}/backups/${this.selectiveRestoreBackup.id}/restore`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    parameters: this.selectedParameters,
                    create_backup: true
                })
            });

            const data = await response.json();
            if (response.ok && data.task) {
                startTaskTracking('Restoring Selected Parameters...', data.task.id);
            } else {
                taskLoading = false;
                alert('Error restoring backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error restoring backup:', error);
            alert('Error restoring backup: ' + error.message);
        }
    },

    toggleCompareSelection(backup) {
        const index = this.selectedForComparison.findIndex(b => b.id === backup.id);
        if (index > -1) {
            this.selectedForComparison.splice(index, 1);
        } else {
            if (this.selectedForComparison.length < 2) {
                this.selectedForComparison.push(backup);
            } else {
                alert('You can only compare 2 backups at a time');
            }
        }
    },

    isSelectedForComparison(backup) {
        return this.selectedForComparison.some(b => b.id === backup.id);
    },

    async compareBackups() {
        if (this.selectedForComparison.length !== 2) return;

        const backup1Id = this.selectedForComparison[0].id;
        const backup2Id = this.selectedForComparison[1].id;

        try {
            const response = await fetch(`/api/devices/{{ $device->id }}/backups/${backup1Id}/compare/${backup2Id}`);
            const data = await response.json();
            if (response.ok) {
                this.comparisonData = data;
                this.showComparisonModal = true;
            } else {
                alert('Error comparing backups: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error comparing backups:', error);
            alert('Error comparing backups: ' + error.message);
        }
    },

    clearCompareSelection() {
        this.selectedForComparison = [];
    },

    closeComparisonModal() {
        this.showComparisonModal = false;
        this.comparisonData = null;
    },

    openImportModal() {
        this.showImportModal = true;
        this.importFile = null;
        this.importName = '';
    },

    closeImportModal() {
        this.showImportModal = false;
        this.importFile = null;
        this.importName = '';
    },

    async importBackup() {
        if (!this.importFile) {
            alert('Please select a backup file to import');
            return;
        }

        this.loading = true;

        try {
            const formData = new FormData();
            formData.append('backup_file', this.importFile);
            if (this.importName) {
                formData.append('name', this.importName);
            }

            const response = await fetch('/api/devices/{{ $device->id }}/backups/import', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const data = await response.json();
            if (response.ok) {
                alert(data.message);
                this.closeImportModal();
                await this.loadBackups();
            } else {
                alert('Error importing backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error importing backup:', error);
            alert('Error importing backup: ' + error.message);
        } finally {
            this.loading = false;
        }
    },

    downloadBackup(backupId) {
        window.location.href = `/api/devices/{{ $device->id }}/backups/${backupId}/download`;
    },

    init() {
        this.loadBackups();
    }
}">
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-gray-50 dark:bg-{{ $colors['bg'] }} flex justify-between items-center">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Configuration Backups</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Backup and restore device configuration</p>
            </div>
            <div class="flex space-x-3">
                <button x-show="selectedForComparison.length > 0" @click="clearCompareSelection()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Clear Selection
                </button>
                <button x-show="selectedForComparison.length === 2" @click="compareBackups()"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Compare Selected (2)
                </button>
                <button @click="openImportModal()" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Import Backup
                </button>
                <button @click="createBackup()" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Backup
                </button>
            </div>
        </div>

        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <!-- Loading State -->
            <div x-show="loading" class="px-6 py-12 text-center">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Loading backups...</p>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && backups.length === 0" class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No Backups Found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Create your first backup to preserve the current device configuration.</p>
            </div>

            <!-- Backups List - Grouped by Retention Type -->
            <div x-show="!loading && backups.length > 0">
                <!-- Initial Backups (Protected) -->
                <div x-show="initialBackups.length > 0" class="border-b border-gray-200 dark:border-{{ $colors['border'] }}">
                    <div class="px-6 py-3 bg-green-50 dark:bg-green-900/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <h4 class="text-sm font-semibold text-green-900 dark:text-green-300">Initial Backup</h4>
                                <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Protected - Never Deleted
                                </span>
                            </div>
                            <span class="text-xs text-green-700 dark:text-green-400" x-text="initialBackups.length + ' backup' + (initialBackups.length !== 1 ? 's' : '')"></span>
                        </div>
                    </div>
                    <template x-for="backup in initialBackups" :key="backup.id">
                        @include('device-tabs.partials.backup-row', ['device' => $device, 'colors' => $colors])
                    </template>
                </div>

                <!-- User Created Backups (90 Day Retention) -->
                <div x-show="userBackups.length > 0" class="border-b border-gray-200 dark:border-{{ $colors['border'] }}">
                    <div class="px-6 py-3 bg-blue-50 dark:bg-blue-900/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300">User Created Backups</h4>
                                <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    90 Day Retention
                                </span>
                            </div>
                            <span class="text-xs text-blue-700 dark:text-blue-400" x-text="userBackups.length + ' backup' + (userBackups.length !== 1 ? 's' : '')"></span>
                        </div>
                    </div>
                    <template x-for="backup in userBackups" :key="backup.id">
                        @include('device-tabs.partials.backup-row', ['device' => $device, 'colors' => $colors])
                    </template>
                </div>

                <!-- Automated Backups (7 Day Retention) -->
                <div x-show="autoBackups.length > 0">
                    <div class="px-6 py-3 bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-600 dark:text-{{ $colors['text-muted'] }} mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Automated Daily Backups</h4>
                                <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    7 Day Retention
                                </span>
                            </div>
                            <span class="text-xs text-gray-700 dark:text-{{ $colors['text-muted'] }}" x-text="autoBackups.length + ' backup' + (autoBackups.length !== 1 ? 's' : '')"></span>
                        </div>
                    </div>
                    <template x-for="backup in autoBackups" :key="backup.id">
                        @include('device-tabs.partials.backup-row', ['device' => $device, 'colors' => $colors])
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Backup Modal -->
    @include('device-tabs.partials.backup-import-modal', ['device' => $device, 'colors' => $colors])

    <!-- Selective Restore Modal -->
    @include('device-tabs.partials.backup-selective-modal', ['device' => $device, 'colors' => $colors])

    <!-- Restore Confirmation Modal -->
    @include('device-tabs.partials.backup-restore-modal', ['device' => $device, 'colors' => $colors])

    <!-- Comparison Modal -->
    @include('device-tabs.partials.backup-comparison-modal', ['device' => $device, 'colors' => $colors])
</div>
