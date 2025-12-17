<!-- Task Manager Component -->
<div x-data="taskManager('{{ $deviceId }}')"
     x-init="init()"
     @task-started.window="justStartedTask = true; startPolling(); fetchTasks();"
     class="fixed top-16 sm:top-20 right-2 sm:right-4 left-2 sm:left-auto z-40">

    <!-- Mini Task Indicator (always visible when tasks exist) -->
    <div x-show="hasActiveTasks()"
         x-transition
         @click="expanded = !expanded"
         class="cursor-pointer bg-blue-600 text-white rounded-lg shadow-lg px-3 sm:px-4 py-2.5 sm:py-3 flex items-center space-x-2 sm:space-x-3 hover:bg-blue-700 transition-colors touch-manipulation">

        <!-- Spinner -->
        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white flex-shrink-0"></div>

        <!-- Task Count & Time -->
        <div class="text-sm font-medium flex-1 min-w-0">
            <span x-text="getTaskSummary()" class="truncate block"></span>
        </div>

        <!-- Active task description on mobile (abbreviated) -->
        <div class="hidden" x-show="tasks.active && tasks.active.description">
            <span class="text-xs opacity-80 truncate max-w-[120px] block" x-text="tasks.active?.description?.substring(0, 20) + '...'"></span>
        </div>

        <!-- Expand/Collapse Icon -->
        <svg class="w-4 h-4 transition-transform flex-shrink-0" :class="{'rotate-180': expanded}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
         class="absolute top-14 sm:top-16 right-0 left-0 sm:left-auto w-full sm:w-[32rem] md:w-[40rem] lg:w-[48rem] bg-white dark:bg-slate-800 rounded-lg shadow-2xl border border-gray-200 dark:border-slate-700 max-h-[70vh] sm:max-h-[600px] overflow-hidden flex flex-col"
         @click.away="expanded = false">

        <!-- Panel Header -->
        <div class="px-3 sm:px-4 py-2.5 sm:py-3 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between bg-gray-50 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tasks</h3>
            <button @click="expanded = false" class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 touch-manipulation">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Task List -->
        <div class="flex-1 overflow-y-auto">

            <!-- Active Task -->
            <template x-if="tasks.active">
                <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1 flex-wrap">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 flex-shrink-0"></div>
                                <span class="text-xs font-medium text-blue-600 dark:text-blue-400" x-text="'#' + tasks.active.id"></span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 break-words" x-text="tasks.active.description"></span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div x-show="tasks.active.progress" x-text="tasks.active.progress" class="break-words"></div>
                                <div class="flex items-center flex-wrap gap-1 sm:gap-2">
                                    <span x-text="formatTime(tasks.active.elapsed)"></span>
                                    <template x-if="tasks.active.estimated_remaining">
                                        <span class="text-gray-500">• ~<span x-text="formatTime(tasks.active.estimated_remaining)"></span> left</span>
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
                    <div class="px-3 sm:px-4 py-2 bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-700">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Queued <span x-text="`(${Math.min(5, tasks.queued.length)} of ${tasks.queued_total})`"></span>
                        </span>
                    </div>
                    <template x-for="(task, index) in tasks.queued.slice(0, 5)" :key="task.id">
                        <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <div class="w-4 h-4 rounded-full border-2 border-gray-400 dark:border-gray-500 flex-shrink-0"></div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300 break-words" x-text="task.description"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Waiting...</div>
                                </div>
                                <button
                                    @click="cancelTask(task.id)"
                                    class="ml-2 px-2 py-1 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-xs font-medium touch-manipulation flex-shrink-0">
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
                    <div class="px-3 sm:px-4 py-2 bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-700">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Recent</span>
                    </div>
                    <template x-for="task in tasks.recent_completed" :key="task.id">
                        <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-slate-700"
                             :class="{
                                 'bg-green-50 dark:bg-green-900/20': task.status === 'completed',
                                 'bg-red-50 dark:bg-red-900/20': task.status === 'failed',
                                 'bg-gray-50 dark:bg-slate-700/20': task.status === 'cancelled'
                             }">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <template x-if="task.status === 'completed'">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        </template>
                                        <template x-if="task.status === 'failed'">
                                            <svg class="w-4 h-4 text-red-600 dark:text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                        </template>
                                        <template x-if="task.status === 'cancelled'">
                                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                        </template>
                                        <span class="text-sm text-gray-700 dark:text-gray-300 break-words" x-text="task.description"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="formatTime(task.elapsed)"></span>
                                        <template x-if="task.error">
                                            <span class="text-red-600 dark:text-red-400 ml-2 break-words" x-text="'• ' + task.error"></span>
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
                <div class="p-6 sm:p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        stopPollingTimeout: null, // Grace period before stopping polling
        lastActiveTime: null, // Track when we last had active tasks
        justStartedTask: false, // Track if a task was just started (before first poll returns)

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

                // Detect newly completed tasks and dispatch events for auto-refresh
                // Check if a previously active task completed
                if (this.tasks.active && (!data.active || data.active.id !== this.tasks.active.id)) {
                    // Previous active task completed - dispatch relevant events
                    const prevTask = this.tasks.active;
                    this.checkAndDispatchTaskEvents(prevTask);
                }

                // Also check recent_completed for new tasks we haven't seen
                if (data.recent_completed && data.recent_completed.length > 0) {
                    for (const task of data.recent_completed) {
                        // Only process if we haven't already dispatched for this task
                        if (!this.dispatchedTasks) {
                            this.dispatchedTasks = new Set();
                        }
                        if (!this.dispatchedTasks.has(task.id)) {
                            // Check if it's a recently completed task (within last 30 seconds for WiFi tasks)
                            const taskAge = task.completed_at ? (Date.now() - new Date(task.completed_at).getTime()) / 1000 : task.elapsed;
                            const isWiFiTask = task.description && (task.description.includes('WiFi:') || task.description.includes('wifi'));
                            const maxAge = isWiFiTask ? 30 : 10; // Give WiFi tasks more time to trigger refresh
                            const isRecent = taskAge <= maxAge;

                            if (isRecent && task.status === 'completed') {
                                console.log('Processing recent task:', task.id, task.description, 'age:', taskAge, 'maxAge:', maxAge);
                                this.checkAndDispatchTaskEvents(task);
                                this.dispatchedTasks.add(task.id);
                            }
                        }
                    }
                }

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

                // Clear justStartedTask once we have real data
                if (this.justStartedTask && (data.active || data.queued?.length > 0 || data.recent_completed?.length > 0)) {
                    this.justStartedTask = false;
                }

                // Track when we last had active tasks - use grace period before stopping
                if (this.hasActiveTasks()) {
                    this.lastActiveTime = Date.now();
                    // Cancel any pending stop
                    if (this.stopPollingTimeout) {
                        clearTimeout(this.stopPollingTimeout);
                        this.stopPollingTimeout = null;
                    }
                } else {
                    // No active tasks - wait 10 seconds before stopping polling
                    // This handles the gap between initial task completion and follow-up task creation
                    if (!this.stopPollingTimeout) {
                        this.stopPollingTimeout = setTimeout(() => {
                            if (!this.hasActiveTasks()) {
                                this.stopPolling();
                            }
                            this.stopPollingTimeout = null;
                        }, 10000); // 10 second grace period
                    }
                }
            } catch (error) {
                console.error('Error fetching tasks:', error);
            }
        },

        startPolling() {
            if (this.polling) return;

            this.polling = true;
            this.lastActiveTime = Date.now();
            // Cancel any pending stop
            if (this.stopPollingTimeout) {
                clearTimeout(this.stopPollingTimeout);
                this.stopPollingTimeout = null;
            }
            // Fetch immediately so new tasks show right away
            this.fetchTasks();
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
            if (this.stopPollingTimeout) {
                clearTimeout(this.stopPollingTimeout);
                this.stopPollingTimeout = null;
            }
        },

        hasActiveTasks() {
            return this.justStartedTask || this.tasks.active || (this.tasks.queued && this.tasks.queued.length > 0);
        },

        checkAndDispatchTaskEvents(task) {
            if (!task || !task.description) return;

            // Check for port mapping task completion
            const isPortMappingTask =
                task.description.includes('port mapping') ||
                task.description.includes('port forward') ||
                task.description.includes('Port Mapping') ||
                task.description.includes('Port Forward') ||
                task.description.includes('Configure port') ||
                task.description.includes('Create port') ||
                task.description.includes('Delete port');

            if (isPortMappingTask) {
                console.log('Dispatching port-mappings-changed event for task:', task.id, task.description);
                window.dispatchEvent(new CustomEvent('port-mappings-changed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }

            // Check for speed test completion
            const isSpeedTestTask =
                task.description.includes('Speed test') ||
                task.description.includes('speed test');

            if (isSpeedTestTask) {
                console.log('Dispatching speedtest-completed event for task:', task.id, task.description);
                window.dispatchEvent(new CustomEvent('speedtest-completed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }

            // Check for ping/traceroute completion - switch to Tasks tab to show results
            const isDiagnosticsTask =
                task.description.includes('Ping test') ||
                task.description.includes('ping test') ||
                task.description.includes('Traceroute') ||
                task.description.includes('traceroute');

            if (isDiagnosticsTask) {
                console.log('Dispatching diagnostics-completed event for task:', task.id, task.description);
                window.dispatchEvent(new CustomEvent('diagnostics-completed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }

            // Check for query/get params completion - refresh page to show updated data
            const isQueryTask =
                task.description.includes('Get Parameters') ||
                task.description.includes('get parameters') ||
                task.description.includes('Querying');

            if (isQueryTask) {
                console.log('Dispatching query-completed event for task:', task.id, task.description);
                window.dispatchEvent(new CustomEvent('query-completed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }

            // Check for WiFi configuration refresh task - refresh page to show updated radio status
            // Trigger on: WiFi: Refresh (the refresh task) or WiFi: Configure (set_parameter_values when config completes)
            const isWiFiRefreshTask = task.description.includes('WiFi: Refresh');
            const isWiFiConfigTask = task.description.includes('WiFi: Configure') && task.task_type === 'set_parameter_values';

            if (isWiFiRefreshTask || isWiFiConfigTask) {
                console.log('WiFi task completed - dispatching wifi-refresh-completed event for task:', task.id, task.description, task.task_type);
                window.dispatchEvent(new CustomEvent('wifi-refresh-completed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }

            // Check for Get Everything task completion
            const isGetEverythingTask = task.description && task.description.includes('Get Everything');

            if (isGetEverythingTask) {
                console.log('Dispatching get-everything-completed event for task:', task.id, task.description);
                window.dispatchEvent(new CustomEvent('get-everything-completed', {
                    detail: { taskId: task.id, description: task.description }
                }));
            }
        },

        getTaskSummary() {
            const total = (this.tasks.active ? 1 : 0) + this.tasks.queued_total;
            // Show elapsed time from active task, or from first queued task if no active task
            let elapsed = 0;
            let taskId = null;
            if (this.tasks.active) {
                elapsed = this.tasks.active.elapsed;
                taskId = this.tasks.active.id;
            } else if (this.tasks.queued && this.tasks.queued.length > 0) {
                elapsed = this.tasks.queued[0].elapsed;
                taskId = this.tasks.queued[0].id;
            }
            const taskIdStr = taskId ? `#${taskId}` : '';
            return `${taskIdStr} ${total} ${total === 1 ? 'task' : 'tasks'} • ${this.formatTime(elapsed)}`;
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
