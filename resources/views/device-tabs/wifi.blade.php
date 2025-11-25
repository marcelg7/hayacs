{{-- WiFi Configuration Tab --}}
<div x-show="activeTab === 'wifi'" x-cloak>
    @php
        // Get all WLAN configuration parameters
        $wlanConfigs = [];
        $wlanParams = $device->parameters()
            ->where('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%')
            ->whereNotLike('name', '%AssociatedDevice%')
            ->whereNotLike('name', '%Stats%')
            ->whereNotLike('name', '%WPS%')
            ->where(function ($query) {
                // Include PreSharedKey.1 only for X_000631_KeyPassphrase, exclude other PreSharedKey params
                $query->where('name', 'NOT LIKE', '%PreSharedKey.1%')
                    ->orWhere('name', 'LIKE', '%PreSharedKey.1.X_000631_KeyPassphrase');
            })
            ->get();

        foreach ($wlanParams as $param) {
            if (preg_match('/WLANConfiguration\.(\d+)\.(.+)/', $param->name, $matches)) {
                $instance = (int) $matches[1];
                $field = $matches[2];

                if (!isset($wlanConfigs[$instance])) {
                    $wlanConfigs[$instance] = ['instance' => $instance];
                }

                // Normalize PreSharedKey.1.X_000631_KeyPassphrase to just X_000631_KeyPassphrase
                if ($field === 'PreSharedKey.1.X_000631_KeyPassphrase') {
                    $field = 'X_000631_KeyPassphrase';
                }

                $wlanConfigs[$instance][$field] = $param->value;
            }
        }

        // Sort by instance number
        ksort($wlanConfigs);

        // Organize into 2.4GHz and 5GHz groups
        $wifi24Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] <= 8);
        $wifi5Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] >= 9);
    @endphp

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6 flex items-start">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300">WiFi Configuration</h3>
            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">Manage wireless network settings for all SSIDs. Changes will be applied immediately via TR-069. Security type is set to WPA2-PSK with AES encryption.</p>
        </div>
    </div>

    <!-- 2.4GHz WiFi Networks -->
    @if(count($wifi24Ghz) > 0)
    @php
        // Get radio enabled status from first instance (all instances on same radio share this)
        $radio24GhzEnabled = collect($wifi24Ghz)->first()['RadioEnabled'] ?? '0';
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6" x-data="{ radio24GhzEnabled: {{ $radio24GhzEnabled === '1' ? 'true' : 'false' }} }">
        <div class="px-4 py-5 sm:px-6 bg-green-50 dark:bg-green-900/20">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">2.4GHz Networks</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Wireless SSIDs on 2.4GHz band (instances 1-8)</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">2.4GHz Radio:</span>
                    <button @click="async () => {
                        radio24GhzEnabled = !radio24GhzEnabled;
                        const message = (radio24GhzEnabled ? 'Enabling' : 'Disabling') + ' 2.4GHz Radio...';

                        taskLoading = true;
                        taskMessage = message;

                        try {
                            const response = await fetch('/api/devices/{{ $device->id }}/wifi-radio', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    band: '2.4GHz',
                                    enabled: radio24GhzEnabled
                                })
                            });
                            const result = await response.json();
                            if (result.task && result.task.id) {
                                startTaskTracking(message, result.task.id);
                            } else {
                                taskLoading = false;
                                alert('Radio toggled, but no task ID returned');
                            }
                        } catch (error) {
                            taskLoading = false;
                            alert('Error: ' + error);
                            radio24GhzEnabled = !radio24GhzEnabled;
                        }
                    }" :class="radio24GhzEnabled ? 'bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700' : 'bg-{{ $colors['btn-secondary'] }}-400 hover:bg-{{ $colors['btn-secondary'] }}-500'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <span :class="radio24GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                    <span class="text-sm font-medium" :class="radio24GhzEnabled ? 'text-green-600' : 'text-gray-500'" x-text="radio24GhzEnabled ? 'Enabled' : 'Disabled'"></span>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($wifi24Ghz as $config)
                @include('device-tabs.partials.wifi-card', ['config' => $config, 'band' => '2.4GHz', 'device' => $device, 'colors' => $colors])
            @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- 5GHz WiFi Networks -->
    @if(count($wifi5Ghz) > 0)
    @php
        $radio5GhzEnabled = collect($wifi5Ghz)->first()['RadioEnabled'] ?? '0';
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg" x-data="{ radio5GhzEnabled: {{ $radio5GhzEnabled === '1' ? 'true' : 'false' }} }">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">5GHz Networks</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Wireless SSIDs on 5GHz band (instances 9-16)</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">5GHz Radio:</span>
                    <button @click="async () => {
                        radio5GhzEnabled = !radio5GhzEnabled;
                        const message = (radio5GhzEnabled ? 'Enabling' : 'Disabling') + ' 5GHz Radio...';

                        taskLoading = true;
                        taskMessage = message;

                        try {
                            const response = await fetch('/api/devices/{{ $device->id }}/wifi-radio', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    band: '5GHz',
                                    enabled: radio5GhzEnabled
                                })
                            });
                            const result = await response.json();
                            if (result.task && result.task.id) {
                                startTaskTracking(message, result.task.id);
                            } else {
                                taskLoading = false;
                                alert('Radio toggled, but no task ID returned');
                            }
                        } catch (error) {
                            taskLoading = false;
                            alert('Error: ' + error);
                            radio5GhzEnabled = !radio5GhzEnabled;
                        }
                    }" :class="radio5GhzEnabled ? 'bg-{{ $colors['btn-success'] }}-600 hover:bg-{{ $colors['btn-success'] }}-700' : 'bg-{{ $colors['btn-secondary'] }}-400 hover:bg-{{ $colors['btn-secondary'] }}-500'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <span :class="radio5GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                    <span class="text-sm font-medium" :class="radio5GhzEnabled ? 'text-green-600' : 'text-gray-500'" x-text="radio5GhzEnabled ? 'Enabled' : 'Disabled'"></span>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($wifi5Ghz as $config)
                @include('device-tabs.partials.wifi-card', ['config' => $config, 'band' => '5GHz', 'device' => $device, 'colors' => $colors])
            @endforeach
            </div>
        </div>
    </div>
    @endif

    @if(count($wlanConfigs) === 0)
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">No WiFi Configuration Found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Click "Query Device Info" to fetch WiFi parameters from the device.</p>
        </div>
    </div>
    @endif
</div>
