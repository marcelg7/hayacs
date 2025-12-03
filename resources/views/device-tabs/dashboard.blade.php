{{-- Dashboard Tab (includes Troubleshooting) --}}
<div x-show="activeTab === 'dashboard'" x-cloak>
    @php
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        // Use centralized manufacturer detection from Device model
        $isNokia = $device->isNokia();
        $isTr098Nokia = !$isDevice2 && $isNokia;

        // Helper function to get parameter value with LIKE
        $getParam = function($pattern) use ($device) {
            $param = $device->parameters()->where('name', 'LIKE', "%{$pattern}%")->first();
            return $param ? $param->value : '-';
        };

        // Helper to get exact parameter
        $getExactParam = function($name) use ($device) {
            $param = $device->parameters()->where('name', $name)->first();
            return $param ? $param->value : '-';
        };

        // Dynamically discover WAN prefix
        if ($isDevice2) {
            // For TR-181, find the WAN interface by looking for the one with a public IP or Name="WAN"
            // Nokia Beacon G6 uses Interface.2 for WAN, Interface.1 for LAN
            $wanInterfaceNum = 2; // Default for Nokia
            $lanInterfaceNum = 1;

            // Check interface names to be sure
            $interface1Name = $getExactParam('Device.IP.Interface.1.Name');
            $interface2Name = $getExactParam('Device.IP.Interface.2.Name');

            if ($interface1Name === 'WAN' || str_contains($interface1Name, 'WAN')) {
                $wanInterfaceNum = 1;
                $lanInterfaceNum = 2;
            } elseif ($interface2Name === 'WAN' || str_contains($interface2Name, 'WAN') || $interface1Name === 'LAN') {
                $wanInterfaceNum = 2;
                $lanInterfaceNum = 1;
            }

            $wanPrefix = "Device.IP.Interface.{$wanInterfaceNum}";
            $lanPrefix = "Device.IP.Interface.{$lanInterfaceNum}";
            $pppPrefix = 'Device.PPP.Interface.1';
        } else {
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
        }

        // Connection status - TR-098 Nokia doesn't have ConnectionStatus in WANIPConnection
        if ($isDevice2) {
            $status = $getExactParam("{$wanPrefix}.Status");
        } elseif ($isTr098Nokia) {
            // Nokia TR-098: Check if we have an external IP - that means connected
            $extIp = $getExactParam("{$wanPrefix}.ExternalIPAddress");
            $status = ($extIp !== '-' && $extIp !== '' && $extIp !== '0.0.0.0') ? 'Connected' : 'Disconnected';
        } else {
            $status = $getExactParam("{$wanPrefix}.ConnectionStatus");
        }
        // $lanPrefix already set above for TR-181, only set for TR-098
        if (!$isDevice2) {
            $lanPrefix = 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        }
        $dhcpPrefix = $isDevice2 ? 'Device.DHCPv4.Server.Pool.1' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        $dhcpEnabled = $isDevice2 ? $getExactParam("{$dhcpPrefix}.Enable") : $getExactParam("{$lanPrefix}.DHCPServerEnable");
    @endphp

    <!-- Refresh Button -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300">Dashboard Information</h3>
            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">Click refresh to fetch the latest WAN, LAN, WiFi, and connected device information from the device.</p>
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
                    startTaskTracking('Refreshing Dashboard Info...', result.task.id);
                } else {
                    alert('Refresh started, but no task ID returned');
                }
            } catch (error) {
                alert('Error refreshing dashboard info: ' + error);
            }
        }">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors["btn-primary"] }}-600 hover:bg-{{ $colors["btn-primary"] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </form>
    </div>

    <!-- WAN & LAN Section (Side by Side) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- WAN/Internet Section -->
        <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-blue-50 dark:bg-blue-900/20">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Internet (WAN)</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">WAN connection details</p>
            </div>
            <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                <dl>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Connection Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            @if($status === 'Connected' || $status === 'Up')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $status }}</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">{{ $status }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">External IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$wanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Default Gateway</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            @php
                                // TR-181: Try Nokia-specific path first, then standard path
                                $gateway = '-';
                                if ($isDevice2) {
                                    $gateway = $getExactParam("{$wanPrefix}.IPv4Address.1.X_ALU-COM_DefaultGateway");
                                    if ($gateway === '-') {
                                        $gateway = $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress");
                                    }
                                } elseif ($isTr098Nokia) {
                                    // Nokia TR-098: Try standard TR-098 path first
                                    $gateway = $getExactParam("{$wanPrefix}.DefaultGateway");
                                    // Then try TR-181 paths (some Nokia devices store gateway in Device.* namespace)
                                    if ($gateway === '-') {
                                        $gateway = $getExactParam("Device.IP.Interface.2.IPv4Address.1.X_ALU-COM_DefaultGateway");
                                    }
                                    if ($gateway === '-') {
                                        $gateway = $getExactParam("Device.IP.Interface.1.IPv4Address.1.X_ALU-COM_DefaultGateway");
                                    }
                                    // Fall back to other TR-098 paths
                                    if ($gateway === '-') {
                                        $gateway = $getExactParam("{$wanPrefix}.X_ALU-COM_DefaultGateway");
                                    }
                                    if ($gateway === '-') {
                                        $gateway = 'N/A (not exposed)';
                                    }
                                } else {
                                    $gateway = $getExactParam("{$wanPrefix}.DefaultGateway");
                                }
                            @endphp
                            {{ $gateway }}
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DNS Servers</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono text-xs">
                            @php
                                // TR-181: DNS is usually in DHCPv4.Client for DHCP-assigned
                                $dns = '-';
                                if ($isDevice2) {
                                    $dns = $getExactParam("Device.DHCPv4.Client.1.DNSServers");
                                    if ($dns === '-') {
                                        $dns = $getExactParam("{$wanPrefix}.IPv4Address.1.DNSServers");
                                    }
                                } elseif ($isTr098Nokia) {
                                    // Nokia TR-098: Try standard TR-098 path first
                                    $dns = $getExactParam("{$wanPrefix}.DNSServers");
                                    // Then try TR-181 paths (some Nokia devices store DNS in Device.* namespace)
                                    if ($dns === '-' || $dns === '') {
                                        $dns1 = $getExactParam("Device.DNS.Relay.Forwarding.1.DNSServer");
                                        $dns2 = $getExactParam("Device.DNS.Relay.Forwarding.2.DNSServer");
                                        if ($dns1 !== '-' && $dns2 !== '-') {
                                            $dns = $dns1 . ',' . $dns2;
                                        } elseif ($dns1 !== '-') {
                                            $dns = $dns1;
                                        } elseif ($dns2 !== '-') {
                                            $dns = $dns2;
                                        }
                                    }
                                    // Fall back to other TR-098 paths
                                    if ($dns === '-' || $dns === '') {
                                        $dns = $getExactParam("InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DNSServers");
                                    }
                                    if ($dns === '-' || $dns === '') {
                                        $dns = 'N/A (not exposed)';
                                    }
                                } else {
                                    $dns = $getExactParam("{$wanPrefix}.DNSServers");
                                }
                                // Format DNS with spaces after commas
                                $dnsFormatted = str_replace(',', ', ', $dns);
                                // Check if using Hay Protected DNS (163.182.253.99 and 163.182.253.101)
                                $isHayProtectedDns = str_contains($dns, '163.182.253.99') && str_contains($dns, '163.182.253.101');
                                // Check if using Hay Raw Unprotected DNS (23.155.128.198 and 23.155.128.199)
                                $isHayRawDns = str_contains($dns, '23.155.128.198') && str_contains($dns, '23.155.128.199');
                            @endphp
                            {{ $dnsFormatted }}
                            @if($isHayProtectedDns)
                                <br><span class="mt-1 inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Hay Protected DNS</span>
                            @elseif($isHayRawDns)
                                <br><span class="mt-1 inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Hay Raw Unprotected DNS</span>
                            @endif
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">MAC Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            @php
                                // TR-181: MAC is on Ethernet.Link for WAN
                                $wanMac = '-';
                                if ($isDevice2) {
                                    // Get the LowerLayers to find the Ethernet.Link
                                    $lowerLayers = $getExactParam("{$wanPrefix}.LowerLayers");
                                    if ($lowerLayers !== '-' && str_contains($lowerLayers, 'Ethernet.Link')) {
                                        $wanMac = $getExactParam("{$lowerLayers}.MACAddress");
                                    }
                                    if ($wanMac === '-') {
                                        $wanMac = $getExactParam("Device.Ethernet.Link.2.MACAddress");
                                    }
                                } else {
                                    $wanMac = $getExactParam("{$wanPrefix}.MACAddress");
                                }
                            @endphp
                            {{ $wanMac }}
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Uptime</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            @php
                                // TR-181: Try Nokia-specific uptime path first
                                if ($isDevice2) {
                                    $uptime = $getExactParam("{$wanPrefix}.X_ALU-COM_Uptime");
                                    if ($uptime === '-' || $uptime === '0') {
                                        $uptime = $getExactParam("{$wanPrefix}.Uptime");
                                    }
                                } elseif ($isTr098Nokia) {
                                    // Nokia TR-098: Uptime is at DeviceInfo.UpTime (not under WAN)
                                    $uptime = $getExactParam("InternetGatewayDevice.DeviceInfo.UpTime");
                                } else {
                                    $uptime = $getExactParam("{$wanPrefix}.Uptime");
                                }
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

        <!-- LAN Section -->
        <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-green-50 dark:bg-green-900/20">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">LAN</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Local network configuration</p>
            </div>
            <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                <dl>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">LAN IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Subnet Mask</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP Server</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            @if($dhcpEnabled === 'true' || $dhcpEnabled === '1')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Disabled</span>
                            @endif
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP Start</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP End</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- WiFi Radio Status -->
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

    @if(count($radios) > 0)
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20 flex items-center justify-between">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">WiFi Networks</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Wireless radio configuration and status</p>
            </div>
            <button @click="activeTab = 'wifi'" class="text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium">
                Configure WiFi &rarr;
            </button>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($radios as $radioNum => $radioData)
                    @php
                        $ssid = '';
                        $enabled = false;
                        $channel = '-';
                        $band = '-';
                        $standard = '-';
                        $txPower = '-';

                        foreach ($radioData as $key => $value) {
                            if (str_contains($key, '.SSID') && !str_contains($key, 'BSSID') && !str_contains($key, 'Enable')) {
                                $ssid = $value;
                            } elseif (str_contains($key, '.Enable')) {
                                $enabled = ($value === 'true' || $value === '1');
                            } elseif (str_contains($key, '.Channel')) {
                                $channel = $value;
                            } elseif (str_contains($key, 'OperatingFrequencyBand')) {
                                $band = $value;
                            } elseif (str_contains($key, 'TransmitPower')) {
                                $txPower = $value;
                            } elseif (str_contains($key, 'Standard') || str_contains($key, 'OperatingStandards')) {
                                $standard = $value;
                            }
                        }

                        // Detect band from channel if not found
                        if ($band === '-' && is_numeric($channel)) {
                            $band = (int)$channel <= 14 ? '2.4GHz' : '5GHz';
                        }
                    @endphp

                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-4 border {{ $enabled ? 'border-green-200 dark:border-green-800' : 'border-gray-200 dark:border-' . $colors['border'] }}">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">
                                {{ $ssid ?: "Radio $radioNum" }}
                            </h4>
                            @if($enabled)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Disabled</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Band:</span>
                                <span class="ml-1 text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $band }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Channel:</span>
                                <span class="ml-1 text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $channel }}</span>
                            </div>
                            @if($standard !== '-')
                            <div>
                                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">Standard:</span>
                                <span class="ml-1 text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $standard }}</span>
                            </div>
                            @endif
                            @if($txPower !== '-')
                            <div>
                                <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">TX Power:</span>
                                <span class="ml-1 text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $txPower }}%</span>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Connected Devices Section -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Connected Devices</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Devices connected to this gateway</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            @php
                // Detect if limited Host data (Nokia TR-098 only has basic fields)
                $hasLimitedHostData = $isTr098Nokia;

                // Get all host table entries
                $hostParams = $device->parameters()
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%Hosts.Host.%')
                          ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
                    })
                    ->get();

                // Get WiFi AssociatedDevice parameters for signal strength and rates
                $wifiAssocParams = $device->parameters()
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
                foreach ($wifiAssocParams as $param) {
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
                                    // Check standard SignalStrength first, then Calix vendor extension (X_000631_)
                                    $signalStrength = $wifiData['SignalStrength']
                                        ?? $wifiData['X_000631_SignalStrength']
                                        ?? $wifiData['X_000631_Metrics.RSSIUpstream']
                                        ?? null;
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

                                // Check standard rates first, then Calix vendor extension (X_000631_)
                                $downRate = $wifiData['LastDataDownlinkRate']
                                    ?? $wifiData['X_000631_LastDataDownlinkRate']
                                    ?? $wifiData['X_000631_Metrics.PhyRateTx']
                                    ?? null;
                                $upRate = $wifiData['LastDataUplinkRate']
                                    ?? $wifiData['X_000631_LastDataUplinkRate']
                                    ?? $wifiData['X_000631_Metrics.PhyRateRx']
                                    ?? null;

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

                                $interfaceType = $host['InterfaceType'] ?? $host['AddressSource'] ?? null;
                                if ($band) {
                                    $interfaceType = "WiFi ({$band})";
                                } elseif ($hasLimitedHostData) {
                                    // Nokia TR-098 doesn't expose InterfaceType in Host table
                                    // Try to detect based on hostname for Beacon APs
                                    $hostname = strtolower($host['HostName'] ?? '');
                                    if (str_contains($hostname, 'nokia') || str_contains($hostname, 'beacon')) {
                                        $interfaceType = 'WiFi (Beacon AP)';
                                    } else {
                                        $interfaceType = 'WiFi/Ethernet';
                                    }
                                } elseif ($interfaceType === null) {
                                    $interfaceType = '-';
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
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $hostMac }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $deviceTypeInfo['type'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $interfaceType }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($signalStrength !== null)
                                        <div class="flex items-center space-x-2">
                                            <span class="{{ $signalClass }} font-mono">{{ $signalStrength }} dBm</span>
                                            <span class="{{ $signalClass }} text-xs">({{ $signalIcon }})</span>
                                        </div>
                                    @elseif($hasLimitedHostData)
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }} text-xs" title="Not available via TR-069">N/A</span>
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
                                    @elseif($hasLimitedHostData)
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }} text-xs" title="Not available via TR-069">N/A</span>
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
                    <p class="text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No connected devices found. Click "Refresh" to fetch host table information.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Tasks & ACS Event Log (Side by Side) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Tasks Section -->
        <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Recent Tasks</h3>
            </div>
            <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                @php
                    $recentTasks = $device->tasks()->orderBy('created_at', 'desc')->limit(5)->get();
                @endphp

                @if($recentTasks->count() > 0)
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Task Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            @foreach($recentTasks as $task)
                                <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                        {{ ucwords(str_replace('_', ' ', $task->task_type)) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                        @if($task->status === 'completed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                                        @elseif($task->status === 'failed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                        @elseif($task->status === 'cancelled')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Cancelled</span>
                                        @elseif($task->status === 'pending')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">{{ ucfirst($task->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                        {{ $task->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        No tasks yet.
                    </div>
                @endif
            </div>
        </div>

        <!-- ACS Event Log -->
        <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 bg-red-50 dark:bg-red-900/20">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">ACS Event Log</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Recent CWMP session events</p>
            </div>
            <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Timestamp</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Event</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Msgs</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        @forelse($sessions->take(8) as $session)
                        <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-900 dark:text-{{ $colors['text'] }}">
                                {{ $session->started_at->format('m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($session->inform_events)
                                    @foreach(array_slice($session->inform_events, 0, 2) as $event)
                                        <span class="inline-block bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 rounded px-1 py-0.5 text-xs font-semibold mr-1">
                                            {{ $event['code'] ?? 'Unknown' }}
                                        </span>
                                    @endforeach
                                    @if(count($session->inform_events) > 2)
                                        <span class="text-gray-400 text-xs">+{{ count($session->inform_events) - 2 }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $session->messages_exchanged }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">No ACS events found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
