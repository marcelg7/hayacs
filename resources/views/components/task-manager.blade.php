<!-- Task Manager Component -->
<div x-data="taskManager('{{ $deviceId }}')"
     x-init="init()"
     @task-started.window="startPolling()"
     class="fixed top-20 right-4 z-40">

    <!-- Mini Task Indicator (always visible when tasks exist) -->
    <div x-show="hasActiveTasks()"
         x-transition
         @click="expanded = !expanded"
         class="cursor-pointer bg-blue-600 text-white rounded-lg shadow-lg px-4 py-3 flex items-center space-x-3 hover:bg-blue-700 transition-colors">

        <!-- Spinner -->
        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>

        <!-- Task Count & Time -->
        <div class="text-sm font-medium">
            <span x-text="getTaskSummary()"></span>
        </div>

        <!-- Expand/Collapse Icon -->
        <svg class="w-4 h-4 transition-transform" :class="{'rotate-180': expanded}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </div>

    <!-- Expanded Task Panel -->
    <div x-show="expanded"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute top-16 right-0 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-700 max-h-[600px] overflow-hidden flex flex-col"
         @click.away="expanded = false">

        <!-- Panel Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tasks</h3>
            <button @click="expanded = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Task List -->
        <div class="flex-1 overflow-y-auto">

            <!-- Active Task -->
            <template x-if="tasks.active">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-1">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="tasks.active.description"></span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div x-show="tasks.active.progress" x-text="tasks.active.progress"></div>
                                <div class="flex items-center space-x-2">
                                    <span x-text="formatTime(tasks.active.elapsed)"></span>
                                    <template x-if="tasks.active.estimated_remaining">
                                        <span class="text-gray-500">• ~<span x-text="formatTime(tasks.active.estimated_remaining)"></span> remaining</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Queued Tasks -->
            <template x-if="tasks.queued && tasks.queued.length > 0">
                <div>
                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Queued <span x-text="`(${Math.min(5, tasks.queued.length)} of ${tasks.queued_total})`"></span>
                        </span>
                    </div>
                    <template x-for="(task, index) in tasks.queued.slice(0, 5)" :key="task.id">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <div class="w-4 h-4 rounded-full border-2 border-gray-400 dark:border-gray-500"></div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="task.description"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Waiting...</div>
                                </div>
                                <button
                                    @click="cancelTask(task.id)"
                                    class="ml-2 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-xs font-medium">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Recent Completed Tasks -->
            <template x-if="tasks.recent_completed && tasks.recent_completed.length > 0">
                <div>
                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Recent</span>
                    </div>
                    <template x-for="task in tasks.recent_completed" :key="task.id">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700"
                             :class="{
                                 'bg-green-50 dark:bg-green-900/20': task.status === 'completed',
                                 'bg-red-50 dark:bg-red-900/20': task.status === 'failed'
                             }">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <template x-if="task.status === 'completed'">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        </template>
                                        <template x-if="task.status === 'failed'">
                                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                        </template>
                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="task.description"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="formatTime(task.elapsed)"></span>
                                        <template x-if="task.error">
                                            <span class="text-red-600 dark:text-red-400 ml-2" x-text="'• ' + task.error"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- No Tasks -->
            <template x-if="!hasActiveTasks() && !tasks.recent_completed?.length">
                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <p class="text-sm">No tasks running</p>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function taskManager(deviceId) {
    return {
        deviceId: deviceId,
        tasks: {
            active: null,
            queued: [],
            queued_total: 0,
            recent_completed: []
        },
        expanded: false,
        polling: false,
        pollInterval: null,
        completedTasks: new Set(), // Track which tasks we've auto-dismissed

        init() {
            this.fetchTasks();
            this.startPolling();
        },

        async fetchTasks() {
            try {
                const response = await fetch(`/api/devices/${this.deviceId}/tasks`, {
                    headers: { 'X-Background-Poll': 'true' }
                });
                const data = await response.json();

                // Auto-dismiss newly completed tasks after 10 seconds
                if (data.active && this.tasks.active && this.tasks.active.id !== data.active.id) {
                    // Previous active task completed
                    const prevTaskId = this.tasks.active.id;
                    if (!this.completedTasks.has(prevTaskId)) {
                        this.completedTasks.add(prevTaskId);
                        setTimeout(() => {
                            this.removeCompletedTask(prevTaskId);
                        }, 10000);
                    }
                }

                this.tasks = data;

                // Stop polling if no active or queued tasks
                if (!this.hasActiveTasks()) {
                    this.stopPolling();
                }
            } catch (error) {
                console.error('Error fetching tasks:', error);
            }
        },

        startPolling() {
            if (this.polling) return;

            this.polling = true;
            this.pollInterval = setInterval(() => {
                this.fetchTasks();
            }, 2000);
        },

        stopPolling() {
            if (!this.polling) return;

            this.polling = false;
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        hasActiveTasks() {
            return this.tasks.active || (this.tasks.queued && this.tasks.queued.length > 0);
        },

        getTaskSummary() {
            const total = (this.tasks.active ? 1 : 0) + this.tasks.queued_total;
            const elapsed = this.tasks.active ? this.tasks.active.elapsed : 0;
            return `${total} ${total === 1 ? 'task' : 'tasks'}  •  ${this.formatTime(elapsed)}`;
        },

        formatTime(seconds) {
            if (!seconds) return '0s';

            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;

            if (mins > 0) {
                return `${mins}m ${secs}s`;
            }
            return `${secs}s`;
        },

        async cancelTask(taskId) {
            if (!confirm('Cancel this task?')) return;

            try {
                const response = await fetch(`/api/devices/${this.deviceId}/tasks/${taskId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Background-Poll': 'true'
                    }
                });

                if (response.ok) {
                    // Refresh task list
                    await this.fetchTasks();
                } else {
                    const error = await response.json();
                    alert(error.error || 'Failed to cancel task');
                }
            } catch (error) {
                console.error('Error cancelling task:', error);
                alert('Failed to cancel task');
            }
        },

        removeCompletedTask(taskId) {
            // Remove from recent_completed list
            this.tasks.recent_completed = this.tasks.recent_completed.filter(t => t.id !== taskId);
        }
    }
}
</script>
