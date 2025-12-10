{{-- Speed Test Tab --}}
<div x-show="activeTab === 'speedtest'" class="space-y-6">
    @if($device->isGigaSpire())
    {{-- GigaSpire Not Supported Message --}}
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-12">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-700">
                <svg class="h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path stroke-linecap="round" stroke-width="2" d="M4 4l16 16"/>
                </svg>
            </div>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Speed Tests Not Currently Supported</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                GigaSpire devices do not currently support speed tests via TR-069.
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-{{ $colors['text-muted'] }}">
                This feature is on our roadmap for future development.
            </p>
        </div>
    </div>
    @else
    {{-- Current Results Card --}}
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6"
         x-data="{
             testing: false,
             testState: null,
             testResults: null,
             completedAt: null,
             lastUpdate: null,
             history: [],
             chart: null,
             testMethod: null,
             testNote: null,

             async startTest() {
                 this.testing = true;
                 this.testState = 'Requested';
                 this.testNote = null;
                 try {
                     const response = await fetch('/api/devices/{{ $device->id }}/speedtest', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                             'X-Background-Poll': 'true'
                         },
                         body: JSON.stringify({ test_type: 'both' })
                     });
                     const data = await response.json();
                     if (response.ok) {
                         // Store test method and note for UI display
                         this.testMethod = data.method || 'tr143';
                         this.testNote = data.note || null;
                         // Trigger task manager indicator
                         if (data.tasks && data.tasks.length > 0) {
                             window.dispatchEvent(new CustomEvent('task-started', {
                                 detail: { message: 'Running Speed Test (Download & Upload)...', taskId: data.tasks[0].id }
                             }));
                         }
                         this.pollResults();
                     } else {
                         alert('Failed to start speed test: ' + (data.message || 'Unknown error'));
                         this.testing = false;
                     }
                 } catch (error) {
                     alert('Error starting speed test: ' + error.message);
                     this.testing = false;
                 }
             },

             async pollResults() {
                 const maxAttempts = 120;
                 let attempts = 0;

                 const poll = async () => {
                     if (attempts++ >= maxAttempts) {
                         this.testing = false;
                         this.testState = 'Timeout - Device may require up to 5 minutes to check in';
                         return;
                     }

                     try {
                         const response = await fetch('/api/devices/{{ $device->id }}/speedtest/status', {
                             headers: { 'X-Background-Poll': 'true' }
                         });
                         const data = await response.json();

                         this.testState = data.state || 'Waiting for device...';
                         this.lastUpdate = new Date().toLocaleTimeString();

                         if (data.state === 'Complete') {
                             this.testResults = data.results;
                             this.completedAt = data.completed_at;
                             this.testing = false;
                             // Refresh history to include new result
                             await this.loadHistory();
                         } else if (data.state === 'Error' || data.state === 'Error_Internal') {
                             this.testing = false;
                             alert('Speed test failed - device reported an error');
                         } else {
                             setTimeout(poll, 3000);
                         }
                     } catch (error) {
                         console.error('Error polling results:', error);
                         this.testing = false;
                     }
                 };

                 poll();
             },

             async refreshResults() {
                 try {
                     const response = await fetch('/api/devices/{{ $device->id }}/speedtest/status');
                     const data = await response.json();
                     this.testState = data.state;
                     this.testResults = data.results;
                     this.completedAt = data.completed_at;
                     this.testMethod = data.method || 'tr143';
                     this.testNote = data.note || null;
                     this.lastUpdate = new Date().toLocaleTimeString();
                 } catch (error) {
                     console.error('Error refreshing results:', error);
                 }
             },

             async loadHistory() {
                 try {
                     const response = await fetch('/api/devices/{{ $device->id }}/speedtest/history');
                     const data = await response.json();
                     this.history = data.results || [];
                     this.renderChart();
                 } catch (error) {
                     console.error('Error loading history:', error);
                 }
             },

             renderChart() {
                 const canvas = document.getElementById('speedtest-chart-{{ $device->id }}');
                 if (!canvas || this.history.length === 0) return;

                 // Destroy existing chart if it exists
                 if (this.chart) {
                     this.chart.destroy();
                 }

                 // Reverse to show oldest first (left to right)
                 const sortedHistory = [...this.history].reverse();

                 const labels = sortedHistory.map(r => {
                     const date = new Date(r.completed_at || r.created_at);
                     return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                 });

                 const downloadData = sortedHistory.map(r => r.download_mbps || 0);
                 const uploadData = sortedHistory.map(r => r.upload_mbps || 0);

                 const isDark = document.documentElement.classList.contains('dark');
                 const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                 const textColor = isDark ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)';

                 this.chart = new Chart(canvas, {
                     type: 'line',
                     data: {
                         labels: labels,
                         datasets: [
                             {
                                 label: 'Download (Mbps)',
                                 data: downloadData,
                                 borderColor: 'rgb(34, 197, 94)',
                                 backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                 fill: true,
                                 tension: 0.3,
                                 pointRadius: 4,
                                 pointHoverRadius: 6
                             },
                             {
                                 label: 'Upload (Mbps)',
                                 data: uploadData,
                                 borderColor: 'rgb(59, 130, 246)',
                                 backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                 fill: true,
                                 tension: 0.3,
                                 pointRadius: 4,
                                 pointHoverRadius: 6
                             }
                         ]
                     },
                     options: {
                         responsive: true,
                         maintainAspectRatio: false,
                         interaction: {
                             intersect: false,
                             mode: 'index'
                         },
                         plugins: {
                             legend: {
                                 position: 'top',
                                 labels: { color: textColor }
                             },
                             tooltip: {
                                 callbacks: {
                                     label: function(context) {
                                         return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' Mbps';
                                     }
                                 }
                             }
                         },
                         scales: {
                             x: {
                                 grid: { color: gridColor },
                                 ticks: {
                                     color: textColor,
                                     maxRotation: 45,
                                     minRotation: 45
                                 }
                             },
                             y: {
                                 beginAtZero: true,
                                 grid: { color: gridColor },
                                 ticks: { color: textColor },
                                 title: {
                                     display: true,
                                     text: 'Speed (Mbps)',
                                     color: textColor
                                 }
                             }
                         }
                     }
                 });
             },

             formatSpeed(bps) {
                 if (!bps) return 'N/A';
                 const mbps = (parseInt(bps) / 1000000).toFixed(2);
                 return mbps + ' Mbps';
             },

             formatDateTime(isoString) {
                 if (!isoString) return 'N/A';
                 const date = new Date(isoString);
                 return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
             },

             init() {
                 this.refreshResults();
                 this.loadHistory();

                 // Listen for speed test completion events from task manager
                 window.addEventListener('speedtest-completed', () => {
                     console.log('Speed test completed event received, refreshing results...');
                     this.refreshResults();
                     this.loadHistory();
                 });
             }
         }">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Speed Test</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    Test the device's internet connection speed
                </p>
                <div x-show="lastUpdate" class="mt-1 text-xs text-gray-400 dark:text-{{ $colors['text-muted'] }}">
                    Last updated: <span x-text="lastUpdate"></span>
                </div>
            </div>
            <div class="flex space-x-3">
                <button @click="refreshResults(); loadHistory();"
                        :disabled="testing"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <button @click="startTest()"
                        :disabled="testing"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="!testing" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <svg x-show="testing" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="testing ? 'Testing...' : 'Start Test'"></span>
                </button>
            </div>
        </div>

        <!-- Test Status -->
        <div x-show="testState" class="mb-4 p-4 rounded-md"
             :class="{
                 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300': testState === 'Requested' || testState === 'InProgress',
                 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300': testState === 'Complete',
                 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300': testState === 'Error' || (testState && testState.includes && testState.includes('Timeout'))
             }">
            <p class="text-sm font-medium">
                Status: <span x-text="testState"></span>
            </p>
        </div>

        <!-- Results Display -->
        <div x-show="testResults" class="space-y-4">
            <!-- Test Completed Timestamp -->
            <div x-show="completedAt" class="text-center">
                <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    Test completed: <span class="font-medium text-gray-700 dark:text-{{ $colors['text'] }}" x-text="formatDateTime(completedAt)"></span>
                </p>
            </div>

            <!-- Speed Results -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Download Speed</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="formatSpeed(testResults?.download)"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Upload Speed</p>
                            <template x-if="testMethod === 'download_rpc' && !testResults?.upload">
                                <p class="text-lg text-gray-400 dark:text-{{ $colors['text-muted'] }}">Not supported</p>
                            </template>
                            <template x-if="testMethod !== 'download_rpc' || testResults?.upload">
                                <p class="text-2xl font-bold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="formatSpeed(testResults?.upload)"></p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Note for devices without upload support -->
            <div x-show="testNote" class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-md">
                <p class="text-sm text-yellow-700 dark:text-yellow-300" x-text="testNote"></p>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="!testResults && !testing" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No speed test results</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Click "Start Test" to run a speed test on this device</p>
        </div>

        <!-- History Chart -->
        <div x-show="history.length > 0" class="mt-8 pt-6 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <h4 class="text-md font-medium text-gray-900 dark:text-{{ $colors['text'] }} mb-4">Speed Test History</h4>
            <div class="h-64">
                <canvas id="speedtest-chart-{{ $device->id }}"></canvas>
            </div>
        </div>

        <!-- History Table -->
        <div x-show="history.length > 0" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Date/Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Download</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Upload</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <template x-for="result in history.slice(0, 10)" :key="result.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }}" x-text="formatDateTime(result.completed_at || result.created_at)"></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-400" x-text="result.download_mbps ? result.download_mbps.toFixed(2) + ' Mbps' : 'N/A'"></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-blue-600 dark:text-blue-400" x-text="result.upload_mbps ? result.upload_mbps.toFixed(2) + ' Mbps' : 'N/A'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="history.length > 10" class="mt-2 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }} text-center">
                Showing latest 10 of <span x-text="history.length"></span> results
            </p>
        </div>
    </div>
    @endif
</div>

{{-- Load Chart.js if not already loaded --}}
<script>
if (typeof Chart === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    document.head.appendChild(script);
}
</script>
