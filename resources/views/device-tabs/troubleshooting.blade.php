{{-- Troubleshooting Tab --}}
<div x-show="activeTab === 'troubleshooting'" x-cloak class="space-y-6">
    <!-- Refresh Button -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-blue-900">Troubleshooting Information</h3>
            <p class="mt-1 text-sm text-blue-700">Click refresh to fetch the latest WAN, LAN, WiFi, and connected device information from the device.</p>
        </div>
        <form @submit.prevent="async (e) => {
            try {
                const response = await fetch('/api/devices/{{ $device->id }}/refresh-troubleshooting', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();
                if (result.task && result.task.id) {
                    startTaskTracking('Refreshing Troubleshooting Info...', result.task.id);
                } else {
                    alert('Refresh started, but no task ID returned');
                }
            } catch (error) {
                alert('Error refreshing troubleshooting info: ' + error);
            }
        }">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh Troubleshooting Info
            </button>
        </form>
    </div>

    @php
        // Helper function to get parameter value
        $getParam = function($pattern) use ($device) {
            $param = $device->parameters()->where('name', 'LIKE', "%{$pattern}%")->first();
            return $param ? $param->value : '-';
        };

        // Helper to get exact parameter
        $getExactParam = function($name) use ($device) {
            $param = $device->parameters()->where('name', $name)->first();
            return $param ? $param->value : '-';
        };

        // Determine data model
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';
    @endphp

    <!-- 1. WAN Information -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-blue-50 dark:bg-blue-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">WAN Information</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Internet connection details</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <dl>
                @if($isDevice2)
                    @php
                        $wanPrefix = 'Device.IP.Interface.1';
                        $pppPrefix = 'Device.PPP.Interface.1';
                    @endphp
                @else
                    @php
                        // Dynamically discover WAN prefix by finding which instance actually has data
                        $wanIpParam = $device->parameters()
                            ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
                            ->first();

                        if ($wanIpParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanIpParam->name, $matches)) {
                            $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
                        } else {
                            $wanPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
                        }

                        // Same for PPP
                        $wanPppParam = $device->parameters()
                            ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANPPPConnection.%.ExternalIPAddress')
                            ->first();

                        if ($wanPppParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANPPPConnection\.(\d+)\./', $wanPppParam->name, $matches)) {
                            $pppPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANPPPConnection.{$matches[3]}";
                        } else {
                            $pppPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
                        }
                    @endphp
                @endif

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Connection Status</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                        @php
                            $status = $isDevice2 ? $getExactParam("{$wanPrefix}.Status") : $getExactParam("{$wanPrefix}.ConnectionStatus");
                        @endphp
                        @if($status === 'Connected' || $status === 'Up')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $status }}</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">{{ $status }}</span>
                        @endif
                    </dd>
                </div>

                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">External IP Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                    </dd>
                </div>

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Default Gateway</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                    </dd>
                </div>

                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DNS Servers</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
                    </dd>
                </div>

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">MAC Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$wanPrefix}.MACAddress") : $getExactParam("{$wanPrefix}.MACAddress") }}
                    </dd>
                </div>

                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Uptime</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                        @php
                            $uptime = $isDevice2 ? $getExactParam("{$wanPrefix}.Uptime") : $getExactParam("{$wanPrefix}.Uptime");
                            if ($uptime !== '-' && is_numeric($uptime)) {
                                $days = floor($uptime / 86400);
                                $hours = floor(($uptime % 86400) / 3600);
                                $minutes = floor(($uptime % 3600) / 60);
                                $uptime = "{$days}d {$hours}h {$minutes}m";
                            }
                        @endphp
                        {{ $uptime }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- 2. LAN Information -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-green-50 dark:bg-green-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">LAN Information</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Local network configuration</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <dl>
                @php
                    $lanPrefix = $isDevice2 ? 'Device.IP.Interface.2' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                    $dhcpPrefix = $isDevice2 ? 'Device.DHCPv4.Server.Pool.1' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
                @endphp

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">LAN IP Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                    </dd>
                </div>

                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Subnet Mask</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                    </dd>
                </div>

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP Server</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2">
                        @php
                            $dhcpEnabled = $isDevice2 ? $getExactParam("{$dhcpPrefix}.Enable") : $getExactParam("{$lanPrefix}.DHCPServerEnable");
                        @endphp
                        @if($dhcpEnabled === 'true' || $dhcpEnabled === '1')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Disabled</span>
                        @endif
                    </dd>
                </div>

                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP Start Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                    </dd>
                </div>

                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP End Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors["text"] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- 3. WiFi Radio Status -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">WiFi Radio Status</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Wireless radio configuration and status</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} space-y-6 p-6">
            @php
                // Get all WiFi-related parameters
                $wifiParams = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%WiFi.Radio.%')
                          ->orWhere('name', 'LIKE', '%WiFi.SSID.%')
                          ->orWhere('name', 'LIKE', '%WLANConfiguration%');
                    })
                    ->get();

                // Organize by radio (1 = 2.4GHz, 2 = 5GHz typically)
                $radios = [];
                foreach ($wifiParams as $param) {
                    if (preg_match('/Radio\.(\d+)/', $param->name, $matches)) {
                        $radioNum = $matches[1];
                        if (!isset($radios[$radioNum])) {
                            $radios[$radioNum] = [];
                        }
                        $radios[$radioNum][$param->name] = $param->value;
                    } elseif (preg_match('/WLANConfiguration\.(\d+)/', $param->name, $matches)) {
                        $radioNum = $matches[1];
                        if (!isset($radios[$radioNum])) {
                            $radios[$radioNum] = [];
                        }
                        $radios[$radioNum][$param->name] = $param->value;
                    }
                }
            @endphp

            @forelse($radios as $radioNum => $radioData)
                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-{{ $colors['text'] }} mb-4">
                        Radio {{ $radioNum }}
                        @php
                            $freq = null;
                            foreach ($radioData as $key => $value) {
                                if (str_contains($key, 'OperatingFrequencyBand')) {
                                    $freq = $value;
                                    break;
                                } elseif (str_contains($key, 'Channel') && is_numeric($value)) {
                                    $freq = (int)$value <= 14 ? '2.4GHz' : '5GHz';
                                    break;
                                }
                            }
                        @endphp
                        @if($freq)
                            <span class="ml-2 text-sm font-normal text-gray-600 dark:text-{{ $colors['text-muted'] }}">({{ $freq }})</span>
                        @endif
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach($radioData as $key => $value)
                            @php
                                $label = '';
                                $showParam = false;

                                if (str_contains($key, '.Enable')) {
                                    $label = 'Status';
                                    $showParam = true;
                                } elseif (str_contains($key, '.SSID') && !str_contains($key, 'BSSID')) {
                                    $label = 'SSID';
                                    $showParam = true;
                                } elseif (str_contains($key, '.Channel')) {
                                    $label = 'Channel';
                                    $showParam = true;
                                } elseif (str_contains($key, 'OperatingFrequencyBand')) {
                                    $label = 'Frequency Band';
                                    $showParam = true;
                                } elseif (str_contains($key, 'TransmitPower')) {
                                    $label = 'Transmit Power';
                                    $showParam = true;
                                } elseif (str_contains($key, 'Standard')) {
                                    $label = 'Standard';
                                    $showParam = true;
                                }
                            @endphp

                            @if($showParam)
                                <div>
                                    <span class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $label }}:</span>
                                    <span class="ml-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}">
                                        @if($label === 'Status')
                                            @if($value === 'true' || $value === '1')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Disabled</span>
                                            @endif
                                        @else
                                            {{ $value }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} text-center py-4">No WiFi radio information available. Click "Refresh" to fetch WiFi parameters.</p>
            @endforelse
        </div>
    </div>

    <!-- 4. Connected Devices -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">Connected Devices</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Devices connected to this gateway</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            @php
                // Get all host table entries
                $hostParams = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%Hosts.Host.%')
                          ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
                    })
                    ->get();

                // Get WiFi AssociatedDevice parameters for signal strength and rates
                $wifiParams = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%AssociatedDevice.%')
                          ->orWhere('name', 'LIKE', '%WLANConfiguration.%AssociatedDevice%');
                    })
                    ->get();

                // Organize by host number
                $hosts = [];
                foreach ($hostParams as $param) {
                    if (preg_match('/Host\.(\d+)\.(.+)/', $param->name, $matches)) {
                        $hostNum = $matches[1];
                        $field = $matches[2];
                        if (!isset($hosts[$hostNum])) {
                            $hosts[$hostNum] = [];
                        }
                        $hosts[$hostNum][$field] = $param->value;
                    }
                }

                // Organize WiFi associated devices by MAC address
                $wifiDevices = [];
                foreach ($wifiParams as $param) {
                    if (preg_match('/AssociatedDevice\.(\d+)\.(.+)/', $param->name, $matches)) {
                        $deviceNum = $matches[1];
                        $field = $matches[2];
                        if (!isset($wifiDevices[$deviceNum])) {
                            $wifiDevices[$deviceNum] = [];
                        }
                        $wifiDevices[$deviceNum][$field] = $param->value;

                        if (preg_match('/WLANConfiguration\.(\d+)\.AssociatedDevice/', $param->name, $wlanMatches)) {
                            $wifiDevices[$deviceNum]['_wlan_config'] = $wlanMatches[1];
                        } elseif (preg_match('/WiFi\.AccessPoint\.(\d+)\.AssociatedDevice/', $param->name, $apMatches)) {
                            $wifiDevices[$deviceNum]['_access_point'] = $apMatches[1];
                        }
                    }
                }

                // Create a lookup table by MAC address for WiFi devices
                $wifiByMac = [];
                foreach ($wifiDevices as $wifiDevice) {
                    $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? $wifiDevice['MACAddress'] ?? null;
                    if ($mac) {
                        $wifiByMac[strtolower(str_replace([':', '-'], '', $mac))] = $wifiDevice;
                    }
                }

                // Helper function to detect device type based on hostname and interface
                $detectDeviceType = function($host, $wifiData) {
                    $hostname = strtolower($host['HostName'] ?? '');
                    $interface = strtolower($host['InterfaceType'] ?? '');

                    if (str_contains($hostname, 'iphone') || str_contains($hostname, 'android') || str_contains($hostname, 'samsung') || str_contains($hostname, 'pixel')) {
                        return ['type' => 'Mobile', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'];
                    } elseif (str_contains($hostname, 'ipad') || str_contains($hostname, 'tablet')) {
                        return ['type' => 'Tablet', 'icon' => 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'];
                    } elseif (str_contains($hostname, 'macbook') || str_contains($hostname, 'laptop') || str_contains($hostname, 'thinkpad')) {
                        return ['type' => 'Laptop', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'];
                    } elseif (str_contains($hostname, 'desktop') || str_contains($hostname, 'pc-')) {
                        return ['type' => 'Desktop', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'];
                    } elseif (str_contains($hostname, 'appletv') || str_contains($hostname, 'roku') || str_contains($hostname, 'chromecast') || str_contains($hostname, 'firetv')) {
                        return ['type' => 'Media', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'];
                    } elseif (str_contains($hostname, 'printer') || str_contains($hostname, 'canon') || str_contains($hostname, 'hp-')) {
                        return ['type' => 'Printer', 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'];
                    } elseif (str_contains($hostname, 'nest') || str_contains($hostname, 'thermostat') || str_contains($hostname, 'camera')) {
                        return ['type' => 'IoT', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'];
                    } elseif (str_contains($interface, 'ethernet') || str_contains($interface, 'eth')) {
                        return ['type' => 'Wired', 'icon' => 'M5 12h14M12 5l7 7-7 7'];
                    } elseif ($wifiData) {
                        return ['type' => 'WiFi Device', 'icon' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0'];
                    }

                    return ['type' => 'Unknown', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'];
                };

                // Helper function to get WiFi band from WLAN config or frequency
                $getWifiBand = function($wifiData) use ($device, $isDevice2) {
                    if (!$wifiData) return null;

                    $wlanConfig = $wifiData['_wlan_config'] ?? null;
                    $accessPoint = $wifiData['_access_point'] ?? null;

                    if ($wlanConfig) {
                        $bandParam = $device->parameters()
                            ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.OperatingFrequencyBand")
                            ->first();
                        if ($bandParam) {
                            return str_contains($bandParam->value, '2.4') ? '2.4GHz' : '5GHz';
                        }

                        $channelParam = $device->parameters()
                            ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.Channel")
                            ->first();
                        if ($channelParam) {
                            $channel = (int)$channelParam->value;
                            return ($channel >= 1 && $channel <= 14) ? '2.4GHz' : '5GHz';
                        }
                    }

                    if ($accessPoint && $isDevice2) {
                        $radioParam = $device->parameters()
                            ->where('name', 'LIKE', "Device.WiFi.Radio.%.OperatingFrequencyBand")
                            ->first();
                        if ($radioParam) {
                            return str_contains($radioParam->value, '2.4') ? '2.4GHz' : '5GHz';
                        }
                    }

                    return null;
                };

                // Filter out inactive hosts
                $hosts = array_filter($hosts, function($host) {
                    return isset($host['Active']) && ($host['Active'] === 'true' || $host['Active'] === '1');
                });
            @endphp

            @if(count($hosts) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">IP Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">MAC Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Interface</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Signal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Down/Up Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            @foreach($hosts as $host)
                            @php
                                $hostMac = $host['MACAddress'] ?? $host['PhysAddress'] ?? '';
                                $normalizedMac = strtolower(str_replace([':', '-'], '', $hostMac));
                                $wifiData = $wifiByMac[$normalizedMac] ?? null;

                                $signalStrength = null;
                                $signalClass = '';
                                $signalIcon = '';

                                if ($wifiData) {
                                    $signalStrength = $wifiData['SignalStrength'] ?? null;
                                }

                                if ($signalStrength === null && isset($host['X_CLEARACCESS_COM_WlanRssi'])) {
                                    $rssi = (int)$host['X_CLEARACCESS_COM_WlanRssi'];
                                    if ($rssi !== 0) {
                                        $signalStrength = $rssi;
                                    }
                                }

                                if ($signalStrength !== null) {
                                    $signal = (int)$signalStrength;
                                    if ($signal >= -50) {
                                        $signalClass = 'text-green-600';
                                        $signalIcon = 'Excellent';
                                    } elseif ($signal >= -60) {
                                        $signalClass = 'text-green-500';
                                        $signalIcon = 'Good';
                                    } elseif ($signal >= -70) {
                                        $signalClass = 'text-yellow-500';
                                        $signalIcon = 'Fair';
                                    } elseif ($signal >= -80) {
                                        $signalClass = 'text-orange-500';
                                        $signalIcon = 'Weak';
                                    } else {
                                        $signalClass = 'text-red-500';
                                        $signalIcon = 'Poor';
                                    }
                                }

                                $downRate = $wifiData['LastDataDownlinkRate'] ?? null;
                                $upRate = $wifiData['LastDataUplinkRate'] ?? null;

                                if ($downRate === null && isset($host['X_CLEARACCESS_COM_WlanTxRate'])) {
                                    $txRate = (int)$host['X_CLEARACCESS_COM_WlanTxRate'];
                                    if ($txRate > 0) {
                                        $downRate = $txRate;
                                    }
                                }
                                if ($upRate === null && isset($host['X_CLEARACCESS_COM_WlanRxRate'])) {
                                    $rxRate = (int)$host['X_CLEARACCESS_COM_WlanRxRate'];
                                    if ($rxRate > 0) {
                                        $upRate = $rxRate;
                                    }
                                }

                                $band = $getWifiBand($wifiData);
                                $deviceTypeInfo = $detectDeviceType($host, $wifiData);

                                $interfaceType = $host['InterfaceType'] ?? $host['AddressSource'] ?? '-';
                                if ($band) {
                                    $interfaceType = "WiFi ({$band})";
                                }
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $deviceTypeInfo['icon'] }}"></path>
                                        </svg>
                                        <span class="text-gray-900 dark:text-{{ $colors['text'] }}">{{ $host['HostName'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $host['IPAddress'] ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($hostMac)
                                        <x-mac-address :mac="$hostMac" />
                                    @else
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $deviceTypeInfo['type'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $interfaceType }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($signalStrength !== null)
                                        <div class="flex items-center space-x-2">
                                            <span class="{{ $signalClass }} font-mono">{{ $signalStrength }} dBm</span>
                                            <span class="{{ $signalClass }} text-xs">({{ $signalIcon }})</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($downRate || $upRate)
                                        <div class="text-gray-900 dark:text-{{ $colors['text'] }} font-mono text-xs">
                                            @if($downRate)
                                                <div class="flex items-center">
                                                    <span class="text-gray-500 mr-1">↓</span>
                                                    <span>{{ number_format($downRate / 1000, 1) }} Mbps</span>
                                                </div>
                                            @endif
                                            @if($upRate)
                                                <div class="flex items-center">
                                                    <span class="text-gray-500 mr-1">↑</span>
                                                    <span>{{ number_format($upRate / 1000, 1) }} Mbps</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-{{ $colors["text-muted"] }}">No connected devices found. Click "Refresh" to fetch host table information.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- 5. ACS Event Log -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 bg-red-50 dark:bg-red-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors["text"] }}">ACS Event Log</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Recent CWMP session events and activity</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Event Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Messages</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    @forelse($sessions->take(20) as $session)
                    <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }}">
                            {{ $session->started_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($session->inform_events)
                                @foreach($session->inform_events as $event)
                                    <span class="inline-block bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 rounded px-2 py-1 text-xs font-semibold mr-1 mb-1">
                                        {{ $event['code'] ?? 'Unknown' }}
                                    </span>
                                @endforeach
                            @else
                                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                            @if($session->ended_at)
                                Duration: {{ $session->started_at->diffInSeconds($session->ended_at) }}s
                            @else
                                <span class="text-yellow-600">In Progress</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $session->messages_exchanged }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No ACS events found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
