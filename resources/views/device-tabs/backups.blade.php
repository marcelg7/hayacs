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

    // Native Config Files (binary device backups)
    nativeConfigs: [],
    nativeConfigsLoading: false,
    showNativeRestoreConfirm: false,
    selectedNativeConfig: null,

    async loadNativeConfigs() {
        this.nativeConfigsLoading = true;
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/native-configs');
            const data = await response.json();
            this.nativeConfigs = data.config_files || [];
        } catch (error) {
            console.error('Error loading native configs:', error);
        } finally {
            this.nativeConfigsLoading = false;
        }
    },

    formatFileSize(bytes) {
        if (!bytes) return 'Unknown';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    confirmNativeRestore(config) {
        this.selectedNativeConfig = config;
        this.showNativeRestoreConfirm = true;
    },

    async restoreNativeConfig() {
        if (!this.selectedNativeConfig) return;

        this.showNativeRestoreConfirm = false;
        taskLoading = true;
        taskMessage = 'Restoring Native Configuration...';

        try {
            const response = await fetch('/api/devices/{{ $device->id }}/native-configs/restore', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    task_id: this.selectedNativeConfig.task_id
                })
            });

            const data = await response.json();
            if (response.ok && data.task) {
                alert('Native config restore initiated!\n\nTask ID: ' + data.task.id + '\n\nThe device will download and apply the configuration file. This may cause the device to reboot.\n\nCheck the Tasks tab for status.');
                taskLoading = false;
            } else {
                taskLoading = false;
                alert('Error restoring config: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error restoring native config:', error);
            alert('Error restoring config: ' + error.message);
        }
    },

    async requestDeviceConfigFile() {
        if (this.loading) return;

        if (!confirm('This will request the device to upload its internal configuration file via TR-069 Upload RPC.\n\nThis may contain WiFi passwords and other sensitive data that cannot be retrieved via normal parameter queries.\n\nContinue?')) {
            return;
        }

        this.loading = true;
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/request-config-backup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const data = await response.json();
            if (response.ok) {
                alert('Config file request sent to device!\n\nTask ID: ' + data.task.id + '\n\nThe device will upload its configuration file when it connects. Check the Tasks tab for status.');
            } else {
                alert('Error requesting config file: ' + (data.error || data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error requesting config file:', error);
            alert('Error requesting config file: ' + error.message);
        } finally {
            this.loading = false;
        }
    },

    init() {
        this.loadBackups();
        this.loadNativeConfigs();
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
                <button @click="requestDeviceConfigFile()" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 border border-orange-300 dark:border-orange-600 rounded-md shadow-sm text-sm font-medium text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Request the device to upload its internal configuration file (may contain WiFi passwords)">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Request Config File
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

    <!-- Native Config Files Section -->
    <div class="mt-6 bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-orange-50 dark:bg-orange-900/20 flex justify-between items-center">
            <div>
                <h3 class="text-lg leading-6 font-medium text-orange-900 dark:text-orange-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Native Device Config Files
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-orange-700 dark:text-orange-400">Binary config files uploaded from device - includes WiFi passwords and encrypted settings</p>
            </div>
            <button @click="loadNativeConfigs()" :disabled="nativeConfigsLoading"
                    class="inline-flex items-center px-3 py-1.5 border border-orange-300 dark:border-orange-600 rounded-md text-sm font-medium text-orange-700 dark:text-orange-300 bg-white dark:bg-orange-900/30 hover:bg-orange-100 dark:hover:bg-orange-900/50 disabled:opacity-50">
                <svg class="w-4 h-4 mr-1" :class="{'animate-spin': nativeConfigsLoading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>

        <div class="border-t border-orange-200 dark:border-orange-800">
            <!-- Loading State -->
            <div x-show="nativeConfigsLoading" class="px-6 py-8 text-center">
                <svg class="animate-spin h-6 w-6 text-orange-600 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-orange-600 dark:text-orange-400">Loading native configs...</p>
            </div>

            <!-- Empty State -->
            <div x-show="!nativeConfigsLoading && nativeConfigs.length === 0" class="px-6 py-8 text-center">
                <svg class="mx-auto h-10 w-10 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No Native Config Files</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Use "Request Config File" button above to request the device to upload its configuration.</p>
            </div>

            <!-- Config Files List -->
            <div x-show="!nativeConfigsLoading && nativeConfigs.length > 0" class="divide-y divide-orange-100 dark:divide-orange-800">
                <template x-for="config in nativeConfigs" :key="config.task_id">
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-orange-50 dark:hover:bg-orange-900/10">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center">
                                <svg class="w-8 h-8 text-orange-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                        Config Backup #<span x-text="config.task_id"></span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                        <span x-text="formatFileSize(config.file_size)"></span>
                                        <span class="mx-1">|</span>
                                        <span x-text="config.analysis?.format || 'Unknown format'"></span>
                                        <span class="mx-1">|</span>
                                        <span x-text="new Date(config.uploaded_at).toLocaleString()"></span>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="config.description"></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <span x-show="config.analysis?.is_binary" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                Encrypted
                            </span>
                            <button @click="confirmNativeRestore(config)"
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Restore
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Native Config Restore Confirmation Modal -->
    <div x-show="showNativeRestoreConfirm" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showNativeRestoreConfirm = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-{{ $colors['card'] }} rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}" id="modal-title">
                            Restore Native Configuration
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                This will restore the device's configuration from its native binary backup file.
                            </p>
                            <div class="mt-3 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-md">
                                <p class="text-sm text-orange-800 dark:text-orange-300 font-medium">Important:</p>
                                <ul class="mt-1 text-sm text-orange-700 dark:text-orange-400 list-disc list-inside">
                                    <li>The device may reboot during restore</li>
                                    <li>All current settings will be overwritten</li>
                                    <li>WiFi passwords and other encrypted settings will be restored</li>
                                    <li>This operation cannot be undone</li>
                                </ul>
                            </div>
                            <div x-show="selectedNativeConfig" class="mt-3 text-sm">
                                <p class="text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                    Restoring from: <span class="font-medium" x-text="'Backup #' + selectedNativeConfig?.task_id"></span>
                                </p>
                                <p class="text-gray-500 dark:text-gray-400">
                                    Size: <span x-text="formatFileSize(selectedNativeConfig?.file_size)"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="restoreNativeConfig()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Restore Configuration
                    </button>
                    <button type="button" @click="showNativeRestoreConfirm = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-{{ $colors['border'] }} shadow-sm px-4 py-2 bg-white dark:bg-{{ $colors['card'] }} text-base font-medium text-gray-700 dark:text-{{ $colors['text'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
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
