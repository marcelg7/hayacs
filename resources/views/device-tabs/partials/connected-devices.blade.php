{{-- Connected Devices Partial --}}
@php
    // Get all host table entries
    $connHostParams = $device->parameters()
        ->where(function($q) {
            $q->where('name', 'LIKE', '%Hosts.Host.%')
              ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
        })
        ->get();

    // Get WiFi AssociatedDevice parameters for signal strength and rates
    $connWifiParams = $device->parameters()
        ->where(function($q) {
            $q->where('name', 'LIKE', '%AssociatedDevice.%')
              ->orWhere('name', 'LIKE', '%WLANConfiguration.%AssociatedDevice%');
        })
        ->get();

    // Organize by host number
    $connHosts = [];
    foreach ($connHostParams as $param) {
        if (preg_match('/Host\.(\d+)\.(.+)/', $param->name, $matches)) {
            $hostNum = $matches[1];
            $field = $matches[2];
            if (!isset($connHosts[$hostNum])) {
                $connHosts[$hostNum] = [];
            }
            $connHosts[$hostNum][$field] = $param->value;
        }
    }

    // Organize WiFi associated devices by MAC address
    $connWifiDevices = [];
    foreach ($connWifiParams as $param) {
        if (preg_match('/AssociatedDevice\.(\d+)\.(.+)/', $param->name, $matches)) {
            $deviceNum = $matches[1];
            $field = $matches[2];
            if (!isset($connWifiDevices[$deviceNum])) {
                $connWifiDevices[$deviceNum] = [];
            }
            $connWifiDevices[$deviceNum][$field] = $param->value;

            if (preg_match('/WLANConfiguration\.(\d+)\.AssociatedDevice/', $param->name, $wlanMatches)) {
                $connWifiDevices[$deviceNum]['_wlan_config'] = $wlanMatches[1];
            } elseif (preg_match('/WiFi\.AccessPoint\.(\d+)\.AssociatedDevice/', $param->name, $apMatches)) {
                $connWifiDevices[$deviceNum]['_access_point'] = $apMatches[1];
            }
        }
    }

    // Create a lookup table by MAC address for WiFi devices
    $connWifiByMac = [];
    foreach ($connWifiDevices as $wifiDevice) {
        $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? $wifiDevice['MACAddress'] ?? null;
        if ($mac) {
            $connWifiByMac[strtolower(str_replace([':', '-'], '', $mac))] = $wifiDevice;
        }
    }

    // Filter out inactive hosts
    $connHosts = array_filter($connHosts, function($host) {
        return isset($host['Active']) && ($host['Active'] === 'true' || $host['Active'] === '1');
    });
@endphp

<div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Connected Devices</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Active devices on the network</p>
    </div>
    <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
        @if(count($connHosts) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Interface</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Signal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Down / Up Rate</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        @foreach($connHosts as $connHost)
                        @php
                            // Look up WiFi data by MAC address
                            $connHostMac = $connHost['MACAddress'] ?? $connHost['PhysAddress'] ?? '';
                            $connNormalizedMac = strtolower(str_replace([':', '-'], '', $connHostMac));
                            $connWifiData = $connWifiByMac[$connNormalizedMac] ?? null;

                            // Detect device type
                            $connHostname = strtolower($connHost['HostName'] ?? '');
                            $connInterface = strtolower($connHost['InterfaceType'] ?? '');

                            $connIcon = '?';
                            if (str_contains($connHostname, 'iphone') || str_contains($connHostname, 'android') || str_contains($connHostname, 'samsung')) {
                                $connIcon = 'Phone';
                            } elseif (str_contains($connHostname, 'ipad') || str_contains($connHostname, 'tablet')) {
                                $connIcon = 'Tablet';
                            } elseif (str_contains($connHostname, 'macbook') || str_contains($connHostname, 'laptop')) {
                                $connIcon = 'Laptop';
                            } elseif (str_contains($connHostname, 'desktop') || str_contains($connHostname, 'pc-')) {
                                $connIcon = 'Desktop';
                            } elseif (str_contains($connHostname, 'appletv') || str_contains($connHostname, 'roku') || str_contains($connHostname, 'chromecast')) {
                                $connIcon = 'TV';
                            } elseif (str_contains($connInterface, 'ethernet') || str_contains($connInterface, 'eth')) {
                                $connIcon = 'Wired';
                            } elseif ($connWifiData) {
                                $connIcon = 'WiFi';
                            }

                            // Get signal strength
                            $connSignalStrength = null;
                            $connSignalClass = '';
                            $connSignalLabel = '';

                            if ($connWifiData) {
                                // Check standard SignalStrength first, then Calix vendor extension (X_000631_)
                                $connSignalStrength = $connWifiData['SignalStrength']
                                    ?? $connWifiData['X_000631_SignalStrength']
                                    ?? $connWifiData['X_000631_Metrics.RSSIUpstream']
                                    ?? null;
                            }

                            if ($connSignalStrength === null && isset($connHost['X_CLEARACCESS_COM_WlanRssi'])) {
                                $rssi = (int)$connHost['X_CLEARACCESS_COM_WlanRssi'];
                                if ($rssi !== 0) {
                                    $connSignalStrength = $rssi;
                                }
                            }

                            if ($connSignalStrength !== null) {
                                $signal = (int)$connSignalStrength;
                                if ($signal >= -50) {
                                    $connSignalClass = 'text-green-600';
                                    $connSignalLabel = 'Excellent';
                                } elseif ($signal >= -60) {
                                    $connSignalClass = 'text-green-500';
                                    $connSignalLabel = 'Good';
                                } elseif ($signal >= -70) {
                                    $connSignalClass = 'text-yellow-500';
                                    $connSignalLabel = 'Fair';
                                } elseif ($signal >= -80) {
                                    $connSignalClass = 'text-orange-500';
                                    $connSignalLabel = 'Weak';
                                } else {
                                    $connSignalClass = 'text-red-500';
                                    $connSignalLabel = 'Poor';
                                }
                            }

                            // Get rates - check standard params first, then Calix vendor extension (X_000631_)
                            $connDownRate = $connWifiData['LastDataDownlinkRate']
                                ?? $connWifiData['X_000631_LastDataDownlinkRate']
                                ?? $connWifiData['X_000631_Metrics.PhyRateTx']
                                ?? null;

                            $connUpRate = $connWifiData['LastDataUplinkRate']
                                ?? $connWifiData['X_000631_LastDataUplinkRate']
                                ?? $connWifiData['X_000631_Metrics.PhyRateRx']
                                ?? null;

                            if ($connDownRate === null && isset($connHost['X_CLEARACCESS_COM_WlanTxRate'])) {
                                $txRate = (int)$connHost['X_CLEARACCESS_COM_WlanTxRate'];
                                if ($txRate > 0) {
                                    $connDownRate = $txRate;
                                }
                            }

                            // Get band for interface display
                            $connBand = null;
                            if ($connWifiData) {
                                $wlanConfig = $connWifiData['_wlan_config'] ?? null;
                                if ($wlanConfig) {
                                    $bandParam = $device->parameters()
                                        ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.OperatingFrequencyBand")
                                        ->first();
                                    if ($bandParam) {
                                        $connBand = str_contains($bandParam->value, '2.4') ? '2.4GHz' : '5GHz';
                                    } else {
                                        $channelParam = $device->parameters()
                                            ->where('name', 'LIKE', "%WLANConfiguration.{$wlanConfig}.Channel")
                                            ->first();
                                        if ($channelParam) {
                                            $channel = (int)$channelParam->value;
                                            $connBand = ($channel >= 1 && $channel <= 14) ? '2.4GHz' : '5GHz';
                                        }
                                    }
                                }
                            }

                            $connInterfaceType = $connHost['InterfaceType'] ?? $connHost['AddressSource'] ?? '-';
                            if ($connBand) {
                                $connInterfaceType = "WiFi ({$connBand})";
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center">
                                    <span class="mr-2 text-gray-400 dark:text-{{ $colors['text-muted'] }} text-xs">[{{ $connIcon }}]</span>
                                    <span class="text-gray-900 dark:text-{{ $colors['text'] }}">{{ $connHost['HostName'] ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-{{ $colors['text'] }} font-mono">{{ $connHost['IPAddress'] ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $connInterfaceType }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($connSignalStrength !== null)
                                    <div class="flex items-center space-x-1">
                                        <span class="{{ $connSignalClass }} font-mono">{{ $connSignalStrength }} dBm</span>
                                        <span class="{{ $connSignalClass }} text-xs">({{ $connSignalLabel }})</span>
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($connDownRate || $connUpRate)
                                    <span class="text-gray-900 dark:text-{{ $colors['text'] }} font-mono text-xs">
                                        @if($connDownRate)
                                            <span class="text-green-600 dark:text-green-400" title="Download">{{ number_format($connDownRate / 1000, 0) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                        <span class="text-gray-400 mx-1">/</span>
                                        @if($connUpRate)
                                            <span class="text-blue-600 dark:text-blue-400" title="Upload">{{ number_format($connUpRate / 1000, 0) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                        <span class="text-gray-500 text-xs ml-1">Mbps</span>
                                    </span>
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
            <div class="px-6 py-4 text-center text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                No connected devices found.
            </div>
        @endif
    </div>
</div>
