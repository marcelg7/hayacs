{{-- Port Forwarding Tab --}}
<div x-show="activeTab === 'ports'" x-cloak x-data="{
    portMappings: [],
    connectedDevices: [],
    loading: true,
    showAddForm: false,
    newMapping: {
        description: '',
        protocol: 'TCP',
        external_port: '',
        internal_port: '',
        internal_client: '',
        custom_ip: ''
    },

    async loadPortMappings() {
        this.loading = true;
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/port-mappings');
            const data = await response.json();
            this.portMappings = data.port_mappings;
        } catch (error) {
            console.error('Error loading port mappings:', error);
            alert('Error loading port mappings: ' + error.message);
        } finally {
            this.loading = false;
        }
    },

    async refreshPortMappings() {
        taskLoading = true;
        taskMessage = 'Refreshing Port Mappings from Device...';

        try {
            const response = await fetch('/api/devices/{{ $device->id }}/port-mappings/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const data = await response.json();
            if (response.ok && data.task) {
                startTaskTracking('Refreshing Port Mappings...', data.task.id);
            } else {
                taskLoading = false;
                alert('Error refreshing port mappings: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error refreshing port mappings:', error);
            alert('Error refreshing port mappings: ' + error.message);
        }
    },

    async loadConnectedDevices() {
        try {
            const response = await fetch('/api/devices/{{ $device->id }}/connected-devices');
            const data = await response.json();
            this.connectedDevices = data.connected_devices || [];
        } catch (error) {
            console.error('Error loading connected devices:', error);
        }
    },

    async addPortMapping() {
        // Handle custom IP - use custom_ip value if 'custom' is selected
        let internalClient = this.newMapping.internal_client;
        if (internalClient === 'custom') {
            internalClient = this.newMapping.custom_ip;
        }

        if (!this.newMapping.description || !this.newMapping.external_port ||
            !this.newMapping.internal_port || !internalClient) {
            alert('Please fill in all fields');
            return;
        }

        // Validate IP format
        const ipPattern = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
        if (!ipPattern.test(internalClient)) {
            alert('Please enter a valid IP address');
            return;
        }

        const isBothProtocol = this.newMapping.protocol === 'Both';
        taskLoading = true;
        taskMessage = isBothProtocol ? 'Adding Port Forward (TCP & UDP)...' : 'Adding Port Forward...';

        try {
            // Build request payload with resolved internal client IP
            const payload = {
                description: this.newMapping.description,
                protocol: this.newMapping.protocol,
                external_port: this.newMapping.external_port,
                internal_port: this.newMapping.internal_port,
                internal_client: internalClient
            };

            const response = await fetch('/api/devices/{{ $device->id }}/port-mappings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (response.ok && data.task) {
                this.showAddForm = false;
                this.newMapping = { description: '', protocol: 'TCP', external_port: '', internal_port: '', internal_client: '', custom_ip: '' };
                const trackingMessage = isBothProtocol ? 'Adding Port Forward (TCP & UDP)...' : 'Adding Port Forward...';
                startTaskTracking(trackingMessage, data.task.id);
            } else {
                taskLoading = false;
                alert('Error adding port forward: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error adding port mapping:', error);
            alert('Error adding port forward: ' + error.message);
        }
    },

    async deletePortMapping(instance) {
        if (!confirm('Are you sure you want to delete this port forward?')) {
            return;
        }

        taskLoading = true;
        taskMessage = 'Deleting Port Forward...';

        try {
            const response = await fetch('/api/devices/{{ $device->id }}/port-mappings', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ instance: instance })
            });

            const data = await response.json();
            if (response.ok && data.task) {
                startTaskTracking('Deleting Port Forward...', data.task.id);
            } else {
                taskLoading = false;
                alert('Error deleting port forward: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            taskLoading = false;
            console.error('Error deleting port mapping:', error);
            alert('Error deleting port forward: ' + error.message);
        }
    },

    init() {
        this.loadPortMappings();
        this.loadConnectedDevices();

        // Listen for port mapping changes from task completion
        window.addEventListener('port-mappings-changed', () => {
            console.log('Port mappings changed event received, reloading...');
            this.loadPortMappings();
        });
    }
}">
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-gray-50 dark:bg-{{ $colors['bg'] }}">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Port Forwarding (NAT)</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Manage port forwarding rules for this device</p>
                </div>
                <div class="flex gap-2">
                    <button @click="refreshPortMappings()" :disabled="loading"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} disabled:opacity-50 disabled:cursor-not-allowed touch-manipulation"
                            title="Refresh port mappings from device">
                        <svg class="w-4 h-4 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="hidden sm:inline">Refresh</span>
                    </button>
                    <button @click="showAddForm = !showAddForm" :disabled="loading"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700 disabled:opacity-50 disabled:cursor-not-allowed touch-manipulation">
                        <svg class="w-4 h-4 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span class="hidden sm:inline" x-text="showAddForm ? 'Cancel' : 'Add Port Forward'"></span>
                        <span class="sm:hidden" x-text="showAddForm ? 'Cancel' : 'Add'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Form -->
        <div x-show="showAddForm" x-cloak class="border-t border-gray-200 dark:border-{{ $colors['border'] }} px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }}">
            <form @submit.prevent="addPortMapping()" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Description</label>
                    <input type="text" x-model="newMapping.description" required
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Protocol</label>
                    <select x-model="newMapping.protocol" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="TCP">TCP</option>
                        <option value="UDP">UDP</option>
                        <option value="Both">Both</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">External Port</label>
                    <input type="number" x-model="newMapping.external_port" min="1" max="65535" required
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Internal Port</label>
                    <input type="number" x-model="newMapping.internal_port" min="1" max="65535" required
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">
                        Internal IP Address
                    </label>
                    <!-- Dropdown for connected devices -->
                    <template x-if="connectedDevices.length > 0">
                        <div class="space-y-2">
                            <select x-model="newMapping.internal_client"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">-- Select a device --</option>
                                <template x-for="device in connectedDevices" :key="device.ip">
                                    <option :value="device.ip" x-text="device.hostname + ' (' + device.ip + ')'"></option>
                                </template>
                                <option value="custom">Enter IP manually...</option>
                            </select>
                            <!-- Manual IP input (shown when "custom" is selected) -->
                            <template x-if="newMapping.internal_client === 'custom'">
                                <input type="text"
                                       x-model="newMapping.custom_ip"
                                       @input="if($event.target.value) newMapping.internal_client = $event.target.value"
                                       pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$"
                                       placeholder="192.168.1.100"
                                       class="block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </template>
                        </div>
                    </template>
                    <!-- Fallback to text input if no connected devices -->
                    <template x-if="connectedDevices.length === 0">
                        <input type="text" x-model="newMapping.internal_client"
                               pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required
                               placeholder="192.168.1.100"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </template>
                    <p x-show="connectedDevices.length > 0" class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        <span x-text="connectedDevices.length"></span> device(s) detected on network
                    </p>
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700">
                        Add Port Forward
                    </button>
                </div>
            </form>
        </div>

        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <!-- Loading State -->
            <div x-show="loading" class="px-6 py-12 text-center">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Loading port forwards...</p>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && portMappings.length === 0" class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No Port Forwards</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Add a port forward to allow external access to internal services.</p>
            </div>

            <!-- Port Mappings List -->
            <div x-show="!loading && portMappings.length > 0">
                {{-- Mobile: Card layout --}}
                <div class="sm:hidden divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <template x-for="mapping in portMappings" :key="mapping.instance">
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="mapping.Description || mapping.PortMappingDescription || 'Unnamed'"></p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200" x-text="mapping.Protocol || mapping.PortMappingProtocol || 'N/A'"></span>
                                        <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                            <span x-text="mapping.ExternalPort || '-'"></span>
                                            <svg class="inline-block w-3 h-3 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                            </svg>
                                            <span x-text="mapping.InternalPort || '-'"></span>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 font-mono" x-text="mapping.InternalClient || 'N/A'"></p>
                                </div>
                                <button @click="deletePortMapping(mapping.instance)"
                                        class="ml-4 p-2 text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 touch-manipulation">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Desktop: Table layout --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Protocol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">External Port</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Internal Port</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Internal IP</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            <template x-for="mapping in portMappings" :key="mapping.instance">
                                <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                    {{-- TR-181 uses Description/Protocol, TR-098 uses PortMappingDescription/PortMappingProtocol --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="mapping.Description || mapping.PortMappingDescription || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="mapping.Protocol || mapping.PortMappingProtocol || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="mapping.ExternalPort || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="mapping.InternalPort || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="mapping.InternalClient || 'N/A'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="deletePortMapping(mapping.instance)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
