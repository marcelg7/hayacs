{{-- WiFi Scan Tab --}}
<div x-show="activeTab === 'wifiscan'" class="space-y-6">
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6"
         x-data="{
             scanning: false,
             scanState: null,
             scanResults: [],
             lastUpdate: null,

             async startScan() {
                 this.scanning = true;
                 this.scanState = 'Requested';
                 try {
                     const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                             'X-Background-Poll': 'true'
                         }
                     });
                     const data = await response.json();
                     if (response.ok) {
                         this.pollResults();
                     } else {
                         alert('Failed to start scan: ' + (data.message || 'Unknown error'));
                         this.scanning = false;
                     }
                 } catch (error) {
                     alert('Error starting scan: ' + error.message);
                     this.scanning = false;
                 }
             },

             async pollResults() {
                 const maxAttempts = 120;
                 let attempts = 0;

                 const poll = async () => {
                     if (attempts++ >= maxAttempts) {
                         this.scanning = false;
                         this.scanState = 'Timeout - Device may require up to 5 minutes to check in';
                         return;
                     }

                     try {
                         const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan-results', {
                             headers: { 'X-Background-Poll': 'true' }
                         });
                         const data = await response.json();

                         this.scanState = data.state || 'Waiting for device...';
                         this.lastUpdate = new Date().toLocaleTimeString();

                         if (data.state === 'Complete') {
                             this.scanResults = data.results;
                             this.scanning = false;
                         } else if (data.state === 'Error' || data.state === 'Error_Internal') {
                             this.scanning = false;
                             alert('Scan failed - device reported an error');
                         } else {
                             setTimeout(poll, 3000);
                         }
                     } catch (error) {
                         console.error('Error polling results:', error);
                         this.scanning = false;
                     }
                 };

                 poll();
             },

             async refreshResults() {
                 try {
                     const response = await fetch('/api/devices/{{ $device->id }}/wifi-scan-results');
                     const data = await response.json();
                     this.scanState = data.state;
                     this.scanResults = data.results;
                     this.lastUpdate = new Date().toLocaleTimeString();
                 } catch (error) {
                     console.error('Error refreshing results:', error);
                 }
             },

             getSignalStrengthColor(strength) {
                 const dbm = parseInt(strength);
                 if (dbm >= -50) return 'text-green-600 font-semibold';
                 if (dbm >= -70) return 'text-yellow-600 font-medium';
                 return 'text-red-600';
             },

             getSignalStrengthLabel(strength) {
                 const dbm = parseInt(strength);
                 if (dbm >= -50) return 'Excellent';
                 if (dbm >= -60) return 'Good';
                 if (dbm >= -70) return 'Fair';
                 return 'Weak';
             },

             getFriendlyRadioName(radioPath, frequencyBand) {
                 if (!radioPath) return 'N/A';
                 const parts = radioPath.split('Radio.');
                 let radioNum = '?';
                 if (parts.length > 1) {
                     const numPart = parts[1].split('.')[0];
                     if (numPart) radioNum = numPart;
                 }
                 if (frequencyBand) {
                     return frequencyBand + ' (Radio ' + radioNum + ')';
                 }
                 return 'Radio ' + radioNum;
             },

             async copyToClipboard(text) {
                 try {
                     await navigator.clipboard.writeText(text);
                 } catch (err) {
                     console.error('Failed to copy:', err);
                 }
             },

             init() {
                 this.refreshResults();
             }
         }">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }}">WiFi Interference Scan</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    Scan for nearby WiFi networks to identify interference and channel congestion
                </p>
                <div x-show="lastUpdate" class="mt-1 text-xs text-gray-400 dark:text-{{ $colors['text-muted'] }}">
                    Last updated: <span x-text="lastUpdate"></span>
                </div>
            </div>
            <div class="flex space-x-3">
                <button @click="refreshResults()"
                        :disabled="scanning"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }} disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <button @click="startScan()"
                        :disabled="scanning"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="!scanning" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <svg x-show="scanning" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="scanning ? 'Scanning...' : 'Start Scan'"></span>
                </button>
            </div>
        </div>

        <!-- Scan Status -->
        <div x-show="scanState" class="mb-4 p-4 rounded-md"
             :class="{
                 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300': scanState === 'Requested' || scanState === 'InProgress',
                 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300': scanState === 'Complete',
                 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300': scanState === 'Error' || scanState === 'Timeout'
             }">
            <p class="text-sm font-medium">
                Status: <span x-text="scanState"></span>
            </p>
        </div>

        <!-- Results Table -->
        <div x-show="scanResults.length > 0" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">SSID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">BSSID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Radio</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Channel</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Signal Strength</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Security</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Mode</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <template x-for="result in scanResults" :key="result.instance">
                            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                    <span x-text="result.SSID || '(Hidden Network)'"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} font-mono">
                                    <span x-text="result.BSSID || 'N/A'"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                    <span x-text="getFriendlyRadioName(result.Radio, result.OperatingFrequencyBand)"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                    <span x-text="result.Channel || 'N/A'"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center space-x-2">
                                        <span :class="getSignalStrengthColor(result.SignalStrength)" x-text="result.SignalStrength + ' dBm'"></span>
                                        <span class="text-xs text-gray-400 dark:text-{{ $colors['text-muted'] }}" x-text="'(' + getSignalStrengthLabel(result.SignalStrength) + ')'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                    <span x-text="result.SecurityModeEnabled || 'N/A'"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                    <span x-text="result.Mode || 'N/A'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="scanResults.length === 0 && !scanning" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No scan results</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Click "Start Scan" to scan for nearby WiFi networks</p>
        </div>
    </div>
</div>
