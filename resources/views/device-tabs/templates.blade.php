{{-- Templates Tab --}}
<div x-show="activeTab === 'templates'" x-cloak x-data="{
    templates: [],
    loading: true,
    selectedCategory: 'all',
    showCreateModal: false,
    showApplyModal: false,
    showEditModal: false,
    selectedTemplate: null,
    createForm: {
        name: '',
        description: '',
        category: 'general',
        source_type: 'backup',
        source_id: null,
        tags: [],
        parameter_patterns: [],
        device_model_filter: '',
        strip_device_specific: true
    },
    applyForm: {
        device_ids: [],
        merge_strategy: 'merge',
        create_backup: true
    },
    newTag: '',
    newPattern: '',

    async init() {
        await this.loadTemplates();
    },

    async loadTemplates() {
        this.loading = true;
        try {
            const params = new URLSearchParams();
            if (this.selectedCategory !== 'all') {
                params.append('category', this.selectedCategory);
            }

            const response = await fetch('/api/templates?' + params);
            const data = await response.json();
            this.templates = data.templates;
        } catch (error) {
            console.error('Error loading templates:', error);
            window.showToast && window.showToast('Failed to load templates', 'error');
        } finally {
            this.loading = false;
        }
    },

    openCreateModal(sourceType = 'device') {
        this.createForm = {
            name: '',
            description: '',
            category: 'general',
            source_type: sourceType,
            source_id: sourceType === 'device' ? {{ $device->id }} : null,
            tags: [],
            parameter_patterns: [],
            device_model_filter: '',
            strip_device_specific: true
        };
        this.showCreateModal = true;
    },

    async createTemplate() {
        try {
            const response = await fetch('/api/templates', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify(this.createForm)
            });

            if (!response.ok) throw new Error('Failed to create template');

            const data = await response.json();
            window.showToast && window.showToast(data.message, 'success');
            this.showCreateModal = false;
            await this.loadTemplates();
        } catch (error) {
            console.error('Error creating template:', error);
            window.showToast && window.showToast('Failed to create template', 'error');
        }
    },

    openApplyModal(template) {
        this.selectedTemplate = template;
        this.applyForm = {
            device_ids: [{{ $device->id }}],
            merge_strategy: 'merge',
            create_backup: true
        };
        this.showApplyModal = true;
    },

    async applyTemplate() {
        try {
            const response = await fetch(`/api/templates/${this.selectedTemplate.id}/apply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify(this.applyForm)
            });

            if (!response.ok) throw new Error('Failed to apply template');

            const data = await response.json();
            window.showToast && window.showToast(data.message, 'success');
            this.showApplyModal = false;
        } catch (error) {
            console.error('Error applying template:', error);
            window.showToast && window.showToast('Failed to apply template', 'error');
        }
    },

    async deleteTemplate(templateId) {
        if (!confirm('Are you sure you want to delete this template?')) return;

        try {
            const response = await fetch(`/api/templates/${templateId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                }
            });

            if (!response.ok) throw new Error('Failed to delete template');

            const data = await response.json();
            window.showToast && window.showToast(data.message, 'success');
            await this.loadTemplates();
        } catch (error) {
            console.error('Error deleting template:', error);
            window.showToast && window.showToast('Failed to delete template', 'error');
        }
    },

    addTag() {
        const tag = this.newTag.trim();
        if (tag && !this.createForm.tags.includes(tag)) {
            this.createForm.tags.push(tag);
            this.newTag = '';
        }
    },

    removeTag(tag) {
        this.createForm.tags = this.createForm.tags.filter(t => t !== tag);
    },

    addPattern() {
        const pattern = this.newPattern.trim();
        if (pattern && !this.createForm.parameter_patterns.includes(pattern)) {
            this.createForm.parameter_patterns.push(pattern);
            this.newPattern = '';
        }
    },

    removePattern(pattern) {
        this.createForm.parameter_patterns = this.createForm.parameter_patterns.filter(p => p !== pattern);
    },

    get categoryTemplates() {
        if (this.selectedCategory === 'all') return this.templates;
        return this.templates.filter(t => t.category === this.selectedCategory);
    }
}" class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Configuration Templates</h3>
                    <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} mt-1">Reusable configuration templates for deploying settings across multiple devices</p>
                </div>
                <button @click="openCreateModal('device')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Template
                </button>
            </div>
        </div>

        <!-- Category Filters -->
        <div class="px-6 py-3 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-b border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="flex flex-wrap gap-2">
                <button @click="selectedCategory = 'all'; loadTemplates()"
                        :class="selectedCategory === 'all' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    All Templates
                </button>
                <button @click="selectedCategory = 'wifi'; loadTemplates()"
                        :class="selectedCategory === 'wifi' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    WiFi
                </button>
                <button @click="selectedCategory = 'port_forwarding'; loadTemplates()"
                        :class="selectedCategory === 'port_forwarding' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Port Forwarding
                </button>
                <button @click="selectedCategory = 'security'; loadTemplates()"
                        :class="selectedCategory === 'security' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Security
                </button>
                <button @click="selectedCategory = 'diagnostics'; loadTemplates()"
                        :class="selectedCategory === 'diagnostics' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Diagnostics
                </button>
                <button @click="selectedCategory = 'general'; loadTemplates()"
                        :class="selectedCategory === 'general' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-white dark:bg-{{ $colors['card'] }} text-gray-700 dark:text-{{ $colors['text'] }} border-gray-300 dark:border-{{ $colors['border'] }}'"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    General
                </button>
            </div>
        </div>

        <!-- Templates List -->
        <div class="divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
            <template x-if="loading">
                <div class="px-6 py-12 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Loading templates...</p>
                </div>
            </template>

            <template x-if="!loading && categoryTemplates.length === 0">
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No templates found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Create a template to reuse configurations across devices</p>
                </div>
            </template>

            <template x-for="template in categoryTemplates" :key="template.id">
                <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="template.name"></h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                      x-text="template.category.replace('_', ' ')"></span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}" x-text="template.description"></p>

                            <div class="mt-2 flex items-center gap-4 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                <span x-text="`${template.parameter_count} parameters`"></span>
                                <span x-text="template.size"></span>
                                <span x-show="template.source_device" x-text="`From: ${template.source_device?.serial || ''}`"></span>
                                <span x-text="`Created: ${new Date(template.created_at).toLocaleDateString()}`"></span>
                            </div>

                            <div x-show="template.tags && template.tags.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="tag in template.tags">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-{{ $colors['bg'] }} text-gray-700 dark:text-{{ $colors['text-muted'] }}"
                                          x-text="tag"></span>
                                </template>
                            </div>
                        </div>

                        <div class="ml-4 flex-shrink-0 flex items-center gap-2">
                            <button @click="openApplyModal(template)"
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Apply to Device
                            </button>
                            <button @click="deleteTemplate(template.id)"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-{{ $colors['border'] }} text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div x-show="showCreateModal"
         x-cloak
         class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
         @click.self="showCreateModal = false">
        <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto m-4">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Create Configuration Template</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 space-y-4">
                <!-- Template Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Template Name *</label>
                    <input type="text" x-model="createForm.name"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., Standard WiFi Configuration">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Description</label>
                    <textarea x-model="createForm.description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Describe what this template configures..."></textarea>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Category *</label>
                    <select x-model="createForm.category"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="general">General</option>
                        <option value="wifi">WiFi</option>
                        <option value="port_forwarding">Port Forwarding</option>
                        <option value="security">Security</option>
                        <option value="diagnostics">Diagnostics</option>
                    </select>
                </div>

                <!-- Parameter Patterns -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Parameter Patterns (Optional)</label>
                    <p class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }} mb-2">Specify parameter patterns to include. Use * as wildcard. Leave empty to include all.</p>
                    <div class="flex gap-2 mb-2">
                        <input type="text" x-model="newPattern" @keyup.enter="addPattern()"
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., InternetGatewayDevice.LANDevice.*.WLANConfiguration.*">
                        <button @click="addPattern()"
                                class="px-4 py-2 bg-{{ $colors['btn-primary'] }}-600 text-white text-sm font-medium rounded-md hover:bg-{{ $colors['btn-primary'] }}-700">
                            Add
                        </button>
                    </div>
                    <div x-show="createForm.parameter_patterns.length > 0" class="flex flex-wrap gap-2">
                        <template x-for="pattern in createForm.parameter_patterns">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                <span x-text="pattern" class="mr-2"></span>
                                <button @click="removePattern(pattern)" class="hover:text-blue-900">&times;</button>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Tags</label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" x-model="newTag" @keyup.enter="addTag()"
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Add a tag...">
                        <button @click="addTag()"
                                class="px-4 py-2 bg-{{ $colors['btn-primary'] }}-600 text-white text-sm font-medium rounded-md hover:bg-{{ $colors['btn-primary'] }}-700">
                            Add
                        </button>
                    </div>
                    <div x-show="createForm.tags.length > 0" class="flex flex-wrap gap-2">
                        <template x-for="tag in createForm.tags">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 dark:bg-{{ $colors['bg'] }} text-gray-700 dark:text-{{ $colors['text-muted'] }}">
                                <span x-text="tag" class="mr-2"></span>
                                <button @click="removeTag(tag)" class="hover:text-gray-900">&times;</button>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Device Model Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">Device Model Filter (Optional)</label>
                    <input type="text" x-model="createForm.device_model_filter"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Leave empty to apply to all models">
                </div>

                <!-- Strip Device-Specific Values -->
                <div class="flex items-center">
                    <input type="checkbox" x-model="createForm.strip_device_specific"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label class="ml-2 block text-sm text-gray-700 dark:text-{{ $colors['text'] }}">
                        Strip device-specific values (MAC addresses, serial numbers, etc.)
                    </label>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-t border-gray-200 dark:border-{{ $colors['border'] }} flex justify-end gap-3">
                <button @click="showCreateModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Cancel
                </button>
                <button @click="createTemplate()"
                        :disabled="!createForm.name || !createForm.category"
                        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    Create Template
                </button>
            </div>
        </div>
    </div>

    <!-- Apply Template Modal -->
    <div x-show="showApplyModal"
         x-cloak
         class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
         @click.self="showApplyModal = false">
        <div class="bg-white dark:bg-{{ $colors['card'] }} rounded-lg shadow-xl max-w-xl w-full m-4">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-{{ $colors['border'] }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Apply Template</h3>
                    <button @click="showApplyModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Applying template: <span x-text="selectedTemplate?.name"></span></p>
                            <p class="text-sm text-blue-700 dark:text-blue-400 mt-1" x-text="`This will apply ${selectedTemplate?.parameter_count || 0} parameters to this device`"></p>
                        </div>
                    </div>
                </div>

                <!-- Create Backup Option -->
                <div class="flex items-center">
                    <input type="checkbox" x-model="applyForm.create_backup"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label class="ml-2 block text-sm text-gray-700 dark:text-{{ $colors['text'] }}">
                        Create backup before applying template (recommended)
                    </label>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} border-t border-gray-200 dark:border-{{ $colors['border'] }} flex justify-end gap-3">
                <button @click="showApplyModal = false"
                        class="px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} text-sm font-medium rounded-md text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                    Cancel
                </button>
                <button @click="applyTemplate()"
                        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700">
                    Apply Template
                </button>
            </div>
        </div>
    </div>
</div>
