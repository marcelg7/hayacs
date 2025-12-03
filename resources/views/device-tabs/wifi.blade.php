{{-- WiFi Configuration Tab --}}
<div x-show="activeTab === 'wifi'" x-cloak>
    @php
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        // Use centralized manufacturer detection from Device model
        $isNokia = $device->isNokia();
        $isCalix = $device->isCalix();

        // Show Standard WiFi Setup for TR-181 devices, TR-098 Nokia devices, OR TR-098 Calix devices
        $showStandardWifiSetup = $isDevice2 || ($dataModel === 'TR-098' && ($isNokia || $isCalix));

        $wlanConfigs = [];
        $wifi24Ghz = [];
        $wifi5Ghz = [];

        if ($isDevice2) {
            // TR-181: Device.WiFi.SSID.*, Device.WiFi.Radio.*, Device.WiFi.AccessPoint.*
            $ssidParams = $device->parameters()
                ->where('name', 'LIKE', 'Device.WiFi.SSID.%')
                ->whereNotLike('name', '%Stats%')
                ->get();

            $radioParams = $device->parameters()
                ->where('name', 'LIKE', 'Device.WiFi.Radio.%')
                ->whereNotLike('name', '%Stats%')
                ->get();

            $apParams = $device->parameters()
                ->where('name', 'LIKE', 'Device.WiFi.AccessPoint.%')
                ->whereNotLike('name', '%Stats%')
                ->whereNotLike('name', '%AC.%')
                ->whereNotLike('name', '%Accounting%')
                ->get();

            // Organize radios by number
            $radios = [];
            foreach ($radioParams as $param) {
                if (preg_match('/Device\.WiFi\.Radio\.(\d+)\.(.+)/', $param->name, $matches)) {
                    $radioNum = (int) $matches[1];
                    $field = $matches[2];
                    if (!isset($radios[$radioNum])) {
                        $radios[$radioNum] = [];
                    }
                    $radios[$radioNum][$field] = $param->value;
                }
            }

            // Organize SSIDs by number
            $ssids = [];
            foreach ($ssidParams as $param) {
                if (preg_match('/Device\.WiFi\.SSID\.(\d+)\.(.+)/', $param->name, $matches)) {
                    $ssidNum = (int) $matches[1];
                    $field = $matches[2];
                    if (!isset($ssids[$ssidNum])) {
                        $ssids[$ssidNum] = ['instance' => $ssidNum];
                    }
                    $ssids[$ssidNum][$field] = $param->value;
                }
            }

            // Organize AccessPoints by number (security settings)
            $accessPoints = [];
            foreach ($apParams as $param) {
                if (preg_match('/Device\.WiFi\.AccessPoint\.(\d+)\.(.+)/', $param->name, $matches)) {
                    $apNum = (int) $matches[1];
                    $field = $matches[2];
                    if (!isset($accessPoints[$apNum])) {
                        $accessPoints[$apNum] = [];
                    }
                    $accessPoints[$apNum][$field] = $param->value;
                }
            }

            // Merge SSID and AccessPoint data, determine band from LowerLayers
            foreach ($ssids as $num => $ssid) {
                $config = $ssid;
                $config['instance'] = $num;

                // Get AP settings for this SSID
                if (isset($accessPoints[$num])) {
                    $ap = $accessPoints[$num];
                    $config['Enable'] = $ap['Enable'] ?? $ssid['Enable'] ?? '0';
                    $config['SSIDAdvertisementEnabled'] = $ap['SSIDAdvertisementEnabled'] ?? '1';
                    // Security settings
                    $config['SecurityMode'] = $ap['Security.ModeEnabled'] ?? '';
                    $config['KeyPassphrase'] = $ap['Security.KeyPassphrase'] ?? '';
                    if (empty($config['KeyPassphrase'])) {
                        $config['KeyPassphrase'] = $ap['Security.PreSharedKey'] ?? '';
                    }
                }

                // Determine which radio this SSID is on
                $lowerLayers = $ssid['LowerLayers'] ?? '';
                $radioNum = 1; // default
                if (preg_match('/Radio\.(\d+)/', $lowerLayers, $m)) {
                    $radioNum = (int) $m[1];
                }

                // Get radio info
                if (isset($radios[$radioNum])) {
                    $radio = $radios[$radioNum];
                    $config['RadioEnabled'] = $radio['Enable'] ?? '0';
                    $config['Channel'] = $radio['Channel'] ?? '';
                    $config['AutoChannelEnable'] = $radio['AutoChannelEnable'] ?? '0';
                    $config['OperatingFrequencyBand'] = $radio['OperatingFrequencyBand'] ?? '';
                    $config['OperatingStandards'] = $radio['OperatingStandards'] ?? '';
                    $config['OperatingChannelBandwidth'] = $radio['CurrentOperatingChannelBandwidth'] ?? $radio['OperatingChannelBandwidth'] ?? '';
                    $config['TransmitPower'] = $radio['TransmitPower'] ?? '';
                }

                // Map Standard field for display
                if (!empty($config['OperatingStandards'])) {
                    $config['Standard'] = $config['OperatingStandards'];
                }

                $wlanConfigs[$num] = $config;

                // Determine band - Nokia uses SSID 1-4 for Radio 1 (2.4GHz), 5-8 for Radio 2 (5GHz)
                $band = $config['OperatingFrequencyBand'] ?? '';
                if (str_contains($band, '2.4')) {
                    $wifi24Ghz[$num] = $config;
                } elseif (str_contains($band, '5')) {
                    $wifi5Ghz[$num] = $config;
                } elseif ($radioNum == 1) {
                    $wifi24Ghz[$num] = $config;
                } else {
                    $wifi5Ghz[$num] = $config;
                }
            }

            ksort($wifi24Ghz);
            ksort($wifi5Ghz);

        } else {
            // TR-098: InternetGatewayDevice.LANDevice.1.WLANConfiguration.*
            $wlanParams = $device->parameters()
                ->where('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%')
                ->whereNotLike('name', '%AssociatedDevice%')
                ->whereNotLike('name', '%Stats%')
                ->whereNotLike('name', '%WPS%')
                ->where(function ($query) {
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

                    if ($field === 'PreSharedKey.1.X_000631_KeyPassphrase') {
                        $field = 'X_000631_KeyPassphrase';
                    }

                    $wlanConfigs[$instance][$field] = $param->value;
                }
            }

            ksort($wlanConfigs);

            // Organize into 2.4GHz and 5GHz groups
            // Nokia TR-098: instances 1-4 = 2.4GHz, 5-8 = 5GHz
            // Other TR-098: typically 1-8 for 2.4GHz, 9-16 for 5GHz
            if ($isNokia) {
                $wifi24Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] <= 4);
                $wifi5Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] >= 5 && $config['instance'] <= 8);
            } else {
                $wifi24Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] <= 8);
                $wifi5Ghz = array_filter($wlanConfigs, fn($config) => $config['instance'] >= 9);
            }
        }
    @endphp

    {{-- SSH WiFi Passwords Section (Nokia Devices with SSH credentials) --}}
    @if($isNokia)
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6" x-data="{
        loading: false,
        wifiConfigs: [],
        hasSshCredentials: false,
        credentialsVerified: false,
        showPasswords: false,
        extractedAt: null,
        error: null,

        async init() {
            await this.loadWifiConfigs();
        },

        async loadWifiConfigs() {
            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/wifi-configs`);
                if (response.ok) {
                    const data = await response.json();
                    this.wifiConfigs = data.data || [];
                    this.hasSshCredentials = data.has_ssh_credentials;
                    this.credentialsVerified = data.credentials_verified;
                }
            } catch (error) {
                console.error('Error loading WiFi configs:', error);
            }
        },

        async loadPasswords() {
            this.loading = true;
            this.error = null;
            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/wifi-passwords`);
                if (response.ok) {
                    const data = await response.json();
                    this.wifiConfigs = data.data || [];
                    this.extractedAt = data.extracted_at;
                    this.showPasswords = true;
                } else {
                    const errorData = await response.json();
                    this.error = errorData.error || 'Failed to load passwords';
                }
            } catch (error) {
                this.error = 'Error: ' + error.message;
            }
            this.loading = false;
        },

        async extractWifiConfig() {
            this.loading = true;
            this.error = null;
            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/extract-wifi-config`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    alert(data.message + '\\nNetworks found: ' + data.networks_found);
                    await this.loadWifiConfigs();
                    await this.loadPasswords();
                } else {
                    const errorData = await response.json();
                    this.error = errorData.error || 'Extraction failed';
                }
            } catch (error) {
                this.error = 'Error: ' + error.message;
            }
            this.loading = false;
        },

        async testSsh() {
            this.loading = true;
            this.error = null;
            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/test-ssh`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('SSH connection successful!');
                    this.credentialsVerified = true;
                } else {
                    this.error = data.error || 'SSH connection failed';
                }
            } catch (error) {
                this.error = 'Error: ' + error.message;
            }
            this.loading = false;
        }
    }">
        <div class="px-4 py-4 sm:px-6 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">WiFi Passwords (SSH)</h3>
                        <p class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                            Retrieved via SSH from device - use for support calls
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <template x-if="!hasSshCredentials">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            No SSH Credentials
                        </span>
                    </template>
                    <template x-if="hasSshCredentials && !credentialsVerified">
                        <button @click="testSsh()" :disabled="loading" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400">
                            <svg x-show="loading" class="animate-spin -ml-0.5 mr-1.5 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Test SSH
                        </button>
                    </template>
                    <template x-if="hasSshCredentials && credentialsVerified">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            SSH Verified
                        </span>
                    </template>
                </div>
            </div>
        </div>

        <div class="px-6 py-5 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            {{-- Error Message --}}
            <template x-if="error">
                <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm text-red-700 dark:text-red-400" x-text="error"></p>
                </div>
            </template>

            {{-- No WiFi Configs Yet --}}
            <template x-if="wifiConfigs.length === 0 && !showPasswords">
                <div class="text-center py-6">
                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                        No WiFi passwords retrieved yet
                    </p>
                    <button x-show="hasSshCredentials" @click="extractWifiConfig()" :disabled="loading"
                            class="mt-3 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 disabled:bg-gray-400">
                        <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Extract via SSH
                    </button>
                </div>
            </template>

            {{-- WiFi Configs Available - Show Reveal Button --}}
            <template x-if="wifiConfigs.length > 0 && !showPasswords">
                <div class="text-center py-4">
                    <p class="text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }} mb-3">
                        <span class="font-medium" x-text="wifiConfigs.length"></span> WiFi networks stored
                    </p>
                    <button @click="loadPasswords()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 disabled:bg-gray-400">
                        <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Reveal WiFi Passwords
                    </button>
                </div>
            </template>

            {{-- Password Display --}}
            <template x-if="showPasswords && wifiConfigs.length > 0">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                            Extracted: <span x-text="extractedAt ? new Date(extractedAt).toLocaleString() : 'Unknown'"></span>
                        </p>
                        <div class="flex space-x-2">
                            <button @click="extractWifiConfig()" :disabled="loading"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Re-extract
                            </button>
                            <button @click="showPasswords = false"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                                Hide
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="wifi in wifiConfigs" :key="wifi.interface">
                            <div class="p-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg border border-gray-200 dark:border-{{ $colors['border'] }}">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}" x-text="wifi.ssid"></span>
                                    <span class="text-xs px-2 py-0.5 rounded"
                                          :class="{
                                              'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': wifi.band === '2.4GHz',
                                              'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400': wifi.band === '5GHz'
                                          }"
                                          x-text="wifi.band"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <code class="text-sm font-mono bg-white dark:bg-gray-800 px-2 py-1 rounded border dark:border-gray-600" x-text="wifi.password || '(no password)'"></code>
                                    <button @click="navigator.clipboard.writeText(wifi.password); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 1500)"
                                            class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">Copy</button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}" x-text="wifi.network_type"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
    @endif

    @if($showStandardWifiSetup)
    {{-- Standard WiFi Setup Section for TR-181 and TR-098 Nokia Devices --}}
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6" x-data="{
        ssid: '',
        password: '',
        enableGuest: false,
        guestPassword: '',
        loading: false,
        currentConfig: null,

        async init() {
            console.log('Standard WiFi Setup initializing...');
            await this.loadCurrentConfig();
            console.log('Standard WiFi Setup initialized, currentConfig:', this.currentConfig);
        },

        async loadCurrentConfig() {
            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/standard-wifi`);
                if (response.ok) {
                    this.currentConfig = await response.json();
                    this.ssid = this.currentConfig.ssid || '';
                    this.enableGuest = this.currentConfig.guest_enabled || false;
                    console.log('WiFi config loaded:', this.currentConfig);
                } else {
                    console.error('WiFi config response not ok:', response.status, await response.text());
                }
            } catch (error) {
                console.error('Error loading WiFi config:', error);
            }
        },

        async applyConfig() {
            if (!this.ssid || !this.password) {
                alert('Please enter both Network Name and Password');
                return;
            }
            if (this.password.length < 8) {
                alert('Password must be at least 8 characters');
                return;
            }

            this.loading = true;
            taskLoading = true;
            taskMessage = 'Applying Standard WiFi Configuration...';

            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/standard-wifi`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        ssid: this.ssid,
                        password: this.password,
                        enable_guest: this.enableGuest
                        // Guest password is auto-generated by backend
                    })
                });
                const result = await response.json();

                // Handle both single task and multiple tasks response
                if (result.tasks && result.tasks.length > 0) {
                    // Multiple tasks (Nokia devices) - trigger task polling
                    window.dispatchEvent(new CustomEvent('task-started'));
                    taskMessage = `${result.task_count} WiFi tasks queued...`;

                    // Show guest password if one was generated
                    if (result.guest_password) {
                        setTimeout(() => {
                            alert(`WiFi configuration queued!\n\nGuest Network Password: ${result.guest_password}\n\nPlease save this password - it will be needed for guest access.`);
                        }, 500);
                    }
                } else if (result.task && result.task.id) {
                    // Single task (non-Nokia devices)
                    startTaskTracking('Applying WiFi Configuration...', result.task.id);

                    if (result.guest_password) {
                        setTimeout(() => {
                            alert(`WiFi configuration queued!\n\nGuest Network Password: ${result.guest_password}\n\nPlease save this password - it will be needed for guest access.`);
                        }, 500);
                    }
                } else {
                    taskLoading = false;
                    alert('Configuration queued successfully');
                }
            } catch (error) {
                taskLoading = false;
                alert('Error applying configuration: ' + error);
            }
            this.loading = false;
        },

        async toggleGuest() {
            taskLoading = true;
            taskMessage = (this.enableGuest ? 'Disabling' : 'Enabling') + ' Guest Network...';

            try {
                const deviceId = encodeURIComponent('{{ $device->id }}');
                const response = await fetch(`/api/devices/${deviceId}/guest-network`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        enabled: !this.enableGuest
                    })
                });
                const result = await response.json();
                if (result.task && result.task.id) {
                    this.enableGuest = !this.enableGuest;
                    startTaskTracking(result.message, result.task.id);
                } else {
                    taskLoading = false;
                }
            } catch (error) {
                taskLoading = false;
                alert('Error: ' + error);
            }
        }
    }">
        <div class="px-4 py-4 sm:px-6 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/30 dark:to-purple-900/30">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Standard WiFi Setup</h3>
                    <p class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                        Configure all networks with a single SSID and password
                    </p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            {{-- Current Config Preview --}}
            <template x-if="currentConfig">
                <div class="mb-6 p-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg" x-data="{ showMainPassword: false, showGuestPassword: false }">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Current Configuration</h4>
                        <template x-if="currentConfig.credentials_stored">
                            <span class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                Set by <span x-text="currentConfig.credentials_set_by"></span>
                            </span>
                        </template>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Main Network:</span>
                            <p class="font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="currentConfig.ssid || '-'"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Main Password:</span>
                            <div class="flex items-center space-x-2">
                                <template x-if="currentConfig.password">
                                    <p class="font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="showMainPassword ? currentConfig.password : '••••••••'"></p>
                                </template>
                                <template x-if="!currentConfig.password">
                                    <p class="text-gray-400 italic">Not stored</p>
                                </template>
                                <button x-show="currentConfig.password" @click="showMainPassword = !showMainPassword" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    <svg x-show="!showMainPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg x-show="showMainPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Guest Network:</span>
                            <p class="font-mono" :class="currentConfig.guest_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-400'" x-text="currentConfig.guest_enabled ? currentConfig.guest_ssid : 'Disabled'"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Guest Password:</span>
                            <div class="flex items-center space-x-2">
                                <template x-if="currentConfig.guest_password && currentConfig.guest_enabled">
                                    <p class="font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="showGuestPassword ? currentConfig.guest_password : '••••••••'"></p>
                                </template>
                                <template x-if="!currentConfig.guest_password || !currentConfig.guest_enabled">
                                    <p class="text-gray-400 italic" x-text="currentConfig.guest_enabled ? 'Not stored' : '-'"></p>
                                </template>
                                <button x-show="currentConfig.guest_password && currentConfig.guest_enabled" @click="showGuestPassword = !showGuestPassword" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    <svg x-show="!showGuestPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg x-show="showGuestPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    {{-- Network SSIDs row --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mt-4 pt-4 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">2.4GHz Dedicated:</span>
                            <p class="font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="currentConfig.dedicated_24ghz_enabled ? (currentConfig.dedicated_24ghz_ssid || currentConfig.ssid + '-2.4GHz') : 'Disabled'"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">5GHz Dedicated:</span>
                            <p class="font-mono text-gray-900 dark:text-{{ $colors['text'] }}" x-text="currentConfig.dedicated_5ghz_enabled ? (currentConfig.dedicated_5ghz_ssid || currentConfig.ssid + '-5GHz') : 'Disabled'"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Setup Form --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-2">Network Name (SSID)</label>
                    <input type="text" x-model="ssid" maxlength="32" placeholder="Enter network name"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        This will create: <span class="font-mono" x-text="ssid || '[name]'"></span>,
                        <span class="font-mono" x-text="(ssid || '[name]') + '-2.4GHz'"></span>,
                        <span class="font-mono" x-text="(ssid || '[name]') + '-5GHz'"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-2">WiFi Password</label>
                    <input type="password" x-model="password" minlength="8" maxlength="63" placeholder="Enter password (min 8 chars)"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">Same password for all networks</p>
                </div>
            </div>

            {{-- Guest Network Section --}}
            <div class="mt-6 p-4 border border-gray-200 dark:border-{{ $colors['border'] }} rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Guest Network</h4>
                            <p class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                <span class="font-mono" x-text="(ssid || '[name]') + '-Guest'"></span> - Band-steered guest access
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center cursor-pointer" x-show="!currentConfig">
                            <input type="checkbox" x-model="enableGuest" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-{{ $colors['text'] }}">Enable on setup</span>
                        </label>
                        <button x-show="currentConfig" @click="toggleGuest()"
                                :class="enableGuest ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <span :class="enableGuest ? 'translate-x-5' : 'translate-x-0'"
                                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Apply Button --}}
            <div class="mt-6 flex justify-end">
                <button @click="applyConfig()" :disabled="loading || !ssid || !password"
                        class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Apply Standard Setup
                </button>
            </div>
        </div>
    </div>

    {{-- Networks that will be configured info --}}
    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} border border-gray-200 dark:border-{{ $colors['border'] }} rounded-lg p-4 mb-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-2">Networks Configured by Standard Setup:</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span class="text-gray-600 dark:text-{{ $colors['text-muted'] }}"><strong>Main</strong> - Band-steered (2.4+5GHz)</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span class="text-gray-600 dark:text-{{ $colors['text-muted'] }}"><strong>-2.4GHz</strong> - Dedicated 2.4GHz</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span class="text-gray-600 dark:text-{{ $colors['text-muted'] }}"><strong>-5GHz</strong> - Dedicated 5GHz</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                <span class="text-gray-600 dark:text-{{ $colors['text-muted'] }}"><strong>-Guest</strong> - Guest (toggleable)</span>
            </div>
        </div>
    </div>

    <hr class="border-gray-200 dark:border-{{ $colors['border'] }} my-6">

    <h3 class="text-lg font-medium text-gray-900 dark:text-{{ $colors['text'] }} mb-4">Advanced: Individual Network Configuration</h3>
    @endif

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6 flex items-start">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <p class="text-sm text-blue-700 dark:text-blue-400">Changes will be applied via TR-069 when device checks in. Leave password blank to keep existing.</p>
        </div>
    </div>

    <!-- 2.4GHz WiFi Networks -->
    @if(count($wifi24Ghz) > 0)
    @php
        $radio24GhzEnabled = collect($wifi24Ghz)->first()['RadioEnabled'] ?? '0';
        $radio24GhzChannel = collect($wifi24Ghz)->first()['Channel'] ?? '';
        $radio24GhzAuto = (collect($wifi24Ghz)->first()['AutoChannelEnable'] ?? '0') === '1';
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6" x-data="{ radio24GhzEnabled: {{ $radio24GhzEnabled === '1' || $radio24GhzEnabled === 'true' ? 'true' : 'false' }} }">
        <div class="px-4 py-4 sm:px-6 bg-green-50 dark:bg-green-900/20 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">2.4 GHz</h3>
                    <p class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                        Ch {{ $radio24GhzChannel ?: 'Auto' }}@if($radio24GhzAuto) (Auto)@endif
                        · {{ count($wifi24Ghz) }} SSID{{ count($wifi24Ghz) > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Radio:</span>
                <button @click="async () => {
                    radio24GhzEnabled = !radio24GhzEnabled;
                    const message = (radio24GhzEnabled ? 'Enabling' : 'Disabling') + ' 2.4GHz Radio...';

                    taskLoading = true;
                    taskMessage = message;

                    try {
                        const deviceId = encodeURIComponent('{{ $device->id }}');
                        const response = await fetch(`/api/devices/${deviceId}/wifi-radio`, {
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
                }" :class="radio24GhzEnabled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    <span :class="radio24GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($wifi24Ghz as $config)
                @include('device-tabs.partials.wifi-card-tr181', ['config' => $config, 'band' => '2.4GHz', 'device' => $device, 'colors' => $colors, 'isDevice2' => $isDevice2, 'isCalix' => $isCalix])
            @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- 5GHz WiFi Networks -->
    @if(count($wifi5Ghz) > 0)
    @php
        $radio5GhzEnabled = collect($wifi5Ghz)->first()['RadioEnabled'] ?? '0';
        $radio5GhzChannel = collect($wifi5Ghz)->first()['Channel'] ?? '';
        $radio5GhzAuto = (collect($wifi5Ghz)->first()['AutoChannelEnable'] ?? '0') === '1';
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg" x-data="{ radio5GhzEnabled: {{ $radio5GhzEnabled === '1' || $radio5GhzEnabled === 'true' ? 'true' : 'false' }} }">
        <div class="px-4 py-4 sm:px-6 bg-purple-50 dark:bg-purple-900/20 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">5 GHz</h3>
                    <p class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                        Ch {{ $radio5GhzChannel ?: 'Auto' }}@if($radio5GhzAuto) (Auto)@endif
                        · {{ count($wifi5Ghz) }} SSID{{ count($wifi5Ghz) > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Radio:</span>
                <button @click="async () => {
                    radio5GhzEnabled = !radio5GhzEnabled;
                    const message = (radio5GhzEnabled ? 'Enabling' : 'Disabling') + ' 5GHz Radio...';

                    taskLoading = true;
                    taskMessage = message;

                    try {
                        const deviceId = encodeURIComponent('{{ $device->id }}');
                        const response = await fetch(`/api/devices/${deviceId}/wifi-radio`, {
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
                }" :class="radio5GhzEnabled ? 'bg-purple-600 hover:bg-purple-700' : 'bg-gray-400 hover:bg-gray-500'"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    <span :class="radio5GhzEnabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($wifi5Ghz as $config)
                @include('device-tabs.partials.wifi-card-tr181', ['config' => $config, 'band' => '5GHz', 'device' => $device, 'colors' => $colors, 'isDevice2' => $isDevice2, 'isCalix' => $isCalix])
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
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">Click "Refresh" to fetch WiFi parameters from the device.</p>
        </div>
    </div>
    @endif
</div>
