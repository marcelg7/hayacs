{{-- Connected Devices Partial --}}
@php
    // Get all host table entries
    $connHostParams = $device->parameters()
        ->where(function($q) {
            $q->where('name', 'LIKE', '%Hosts.Host.%')
              ->orWhere('name', 'LIKE', '%LANDevice.1.Hosts.Host.%');
        })
        ->get();

    // Get WiFi AssociatedDevice parameters for signal strength and rates (Calix/SmartRG)
    $connWifiParams = $device->parameters()
        ->where(function($q) {
            $q->where('name', 'LIKE', '%AssociatedDevice.%')
              ->orWhere('name', 'LIKE', '%WLANConfiguration.%AssociatedDevice%');
        })
        ->get();

    // Nokia: Get STA (station) data from DataElements.Network.Device.*.Radio.*.BSS.*.STA.*
    // This contains signal and rate data for clients connected via WiFi
    $nokiaStaByMac = [];
    if ($device->isNokia()) {
        $staParams = $device->parameters()
            ->where('name', 'LIKE', '%DataElements.Network.Device.%.Radio.%.BSS.%.STA.%')
            ->get();

        // Organize by STA path to group MAC, signal, and rates together
        $staData = [];
        foreach ($staParams as $param) {
            // Extract Device.{d}.Radio.{r}.BSS.{b}.STA.{s} path
            if (preg_match('/Device\.(\d+)\.Radio\.(\d+)\.BSS\.(\d+)\.STA\.(\d+)\.(.+)/', $param->name, $m)) {
                $staKey = "D{$m[1]}R{$m[2]}B{$m[3]}S{$m[4]}";
                $field = $m[5];
                if (!isset($staData[$staKey])) {
                    $staData[$staKey] = ['device' => $m[1], 'radio' => $m[2]];
                }
                $staData[$staKey][$field] = $param->value;
            }
        }

        // Build lookup by MAC address (keep entry with best data - non-zero signal/rates)
        foreach ($staData as $sta) {
            $mac = $sta['MACAddress'] ?? null;
            if ($mac) {
                $macNorm = strtolower(str_replace([':', '-'], '', $mac));
                $newEntry = [
                    'LastDataDownlinkRate' => $sta['LastDataDownlinkRate'] ?? null,
                    'LastDataUplinkRate' => $sta['LastDataUplinkRate'] ?? null,
                    'SignalStrength' => $sta['SignalStrength'] ?? null,
                    'X_ALU-COM_EWMA_SignalStrength' => $sta['X_ALU-COM_EWMA_SignalStrength'] ?? null,
                    'device' => $sta['device'], // 1 = gateway, 2+ = satellite
                    'radio' => $sta['radio'], // 1 = 2.4GHz, 2 = 5GHz typically
                ];

                // Only overwrite if no existing entry OR new entry has actual data (non-zero signal/rate)
                $hasData = ($newEntry['SignalStrength'] > 0) || ($newEntry['LastDataDownlinkRate'] > 0);
                if (!isset($nokiaStaByMac[$macNorm]) || $hasData) {
                    $nokiaStaByMac[$macNorm] = $newEntry;
                }
            }
        }
    }

    // Build mesh AP mapping (Calix uses MAC-based, Nokia uses BeaconInfo index)
    $meshApMap = [];
    $meshApMacs = []; // MACs of mesh APs to filter from host list
    $nokiaBeaconMap = []; // Nokia: beacon index => device info

    if ($device->isCalix()) {
        // Calix: Find mesh devices by GatewayInfo.SerialNumber
        $meshDevices = \App\Models\Device::where('product_class', 'LIKE', '%Mesh%')
            ->whereHas('parameters', function($q) use ($device) {
                $q->where('name', 'LIKE', '%GatewayInfo.SerialNumber')
                  ->where('value', $device->serial_number);
            })
            ->get();

        foreach ($meshDevices as $meshDev) {
            // Get mesh AP's MAC from WapHostInfo or WANEthernetInterfaceConfig
            $meshMacParam = $meshDev->parameters()->where('name', 'LIKE', '%WapHostInfo.MACAddress')->first();
            if (!$meshMacParam) {
                $meshMacParam = $meshDev->parameters()->where('name', 'LIKE', '%WANEthernetInterfaceConfig.MACAddress')->first();
            }
            if ($meshMacParam && $meshMacParam->value) {
                $mac = strtolower($meshMacParam->value);
                $meshApMap[$mac] = [
                    'id' => $meshDev->id,
                    'serial' => $meshDev->serial_number,
                    'type' => $meshDev->product_class,
                    'name' => $meshDev->display_name ?? $meshDev->serial_number,
                ];
                $meshApMacs[] = strtolower(str_replace([':', '-'], '', $mac));
            }
        }

        // GigaSpire also stores satellite info directly in ExosMesh.Wap.{i}
        $wapParams = $device->parameters()
            ->where('name', 'LIKE', '%ExosMesh.Wap.%')
            ->get();

        $wapData = [];
        foreach ($wapParams as $param) {
            if (preg_match('/Wap\.(\d+)\.(.+)/', $param->name, $m)) {
                $idx = $m[1];
                $field = $m[2];
                if (!isset($wapData[$idx])) $wapData[$idx] = [];
                $wapData[$idx][$field] = $param->value;
            }
        }

        foreach ($wapData as $idx => $info) {
            $wapMac = strtolower($info['WapMac'] ?? '');
            $wapSerial = $info['WapSerialNumber'] ?? null;
            if ($wapMac && $wapMac !== 'n/a' && !isset($meshApMap[$wapMac])) {
                // Try to find device by serial
                $wapDevice = $wapSerial ? \App\Models\Device::where('serial_number', $wapSerial)->first() : null;
                $meshApMap[$wapMac] = [
                    'id' => $wapDevice?->id,
                    'serial' => $wapSerial ?? 'Unknown',
                    'type' => $wapDevice?->product_class ?? 'Satellite',
                    'name' => $info['WapLocation'] ?? ($wapSerial ? substr($wapSerial, -6) : 'Satellite'),
                ];
                $meshApMacs[] = strtolower(str_replace([':', '-'], '', $wapMac));
            }
        }
    } elseif ($device->isNokia()) {
        // Nokia: Build beacon index map from X_ALU-COM_BeaconInfo.Beacon.N
        $beaconParams = $device->parameters()
            ->where('name', 'LIKE', '%X_ALU-COM_BeaconInfo.Beacon.%')
            ->get();

        $beaconData = [];
        foreach ($beaconParams as $param) {
            if (preg_match('/BeaconInfo\.Beacon\.(\d+)\.(.+)/', $param->name, $m)) {
                $idx = $m[1];
                $field = $m[2];
                if (!isset($beaconData[$idx])) $beaconData[$idx] = [];
                $beaconData[$idx][$field] = $param->value;
            }
        }

        foreach ($beaconData as $idx => $info) {
            $serial = $info['SerialNumber'] ?? null;
            $mac = strtolower($info['MACAddress'] ?? '');
            if ($serial) {
                // Try to find the actual device record
                $beaconDevice = \App\Models\Device::where('serial_number', $serial)->first();
                $nokiaBeaconMap[$idx] = [
                    'id' => $beaconDevice?->id,
                    'serial' => $serial,
                    'mac' => $mac,
                    'type' => $beaconDevice?->product_class ?? 'Beacon',
                    'status' => $info['Status'] ?? 'Unknown',
                    'backhaul' => $info['BackhaulStatus'] ?? 'Unknown',
                ];
                if ($mac) {
                    $meshApMacs[] = strtolower(str_replace([':', '-'], '', $mac));
                }
            }
        }
    }

    // Get gateway's LAN MAC address
    // For GigaSpire, use ExosMesh.WapHostInfo.MACAddress (this is the AP MAC)
    $gatewayLanMac = $device->parameters()
        ->where('name', 'LIKE', '%ExosMesh.WapHostInfo.MACAddress')
        ->first();
    // Fallback to standard LANEthernetInterfaceConfig for other devices
    if (!$gatewayLanMac || !$gatewayLanMac->value) {
        $gatewayLanMac = $device->parameters()
            ->where('name', 'LIKE', '%LANEthernetInterfaceConfig.%.MACAddress%')
            ->first();
    }
    $gatewayMac = $gatewayLanMac ? strtolower($gatewayLanMac->value) : '';

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

    // Filter out inactive hosts and mesh APs themselves
    $connHosts = array_filter($connHosts, function($host) use ($meshApMacs) {
        // Must be active
        if (!isset($host['Active']) || ($host['Active'] !== 'true' && $host['Active'] !== '1')) {
            return false;
        }
        // Filter out mesh APs (they appear as hosts but shouldn't be shown as "connected devices")
        $hostMac = $host['MACAddress'] ?? '';
        $normalizedMac = strtolower(str_replace([':', '-'], '', $hostMac));
        if (in_array($normalizedMac, $meshApMacs)) {
            return false;
        }
        return true;
    });
@endphp

<div class="bg-white dark:bg-{{ $colors['card'] }} shadow sm:rounded-lg overflow-x-auto"
     x-data="{ refreshingSignal: false, refreshError: null }">
    <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Connected Devices</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Active devices on the network</p>
            </div>
            <button type="button"
                    @click="async () => {
                        refreshingSignal = true;
                        refreshError = null;
                        try {
                            const response = await fetch('/api/devices/{{ $device->id }}/connected-devices/refresh', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                credentials: 'include'
                            });
                            const data = await response.json();
                            if (response.ok) {
                                $dispatch('show-toast', { message: 'Signal data refresh queued (Task #' + data.task.id + ')', type: 'success' });
                            } else {
                                refreshError = data.message || 'Failed to refresh';
                                $dispatch('show-toast', { message: refreshError, type: 'error' });
                            }
                        } catch (e) {
                            refreshError = 'Network error';
                            $dispatch('show-toast', { message: 'Network error', type: 'error' });
                        } finally {
                            refreshingSignal = false;
                        }
                    }"
                    :disabled="refreshingSignal"
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Refresh signal strength and rate data from device">
                <svg x-show="!refreshingSignal" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg x-show="refreshingSignal" x-cloak class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="refreshingSignal ? 'Refreshing...' : 'Refresh Signal'"></span>
            </button>
        </div>
    </div>
    <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
        @if(count($connHosts) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                    <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">MAC Address</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Connected To</th>
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

                            // Nokia: Also check STA data for signal/rate info
                            $connNokiaSta = $nokiaStaByMac[$connNormalizedMac] ?? null;

                            // Determine "Connected To" - which AP is this device connected through
                            $connConnectedTo = null;
                            $connConnectedToLink = null;
                            $connConnectedToType = 'gateway'; // 'gateway' or 'mesh'

                            // Calix uses X_000631_AccessPoint (MAC address)
                            $connAccessPointMac = strtolower($connHost['X_000631_AccessPoint'] ?? '');
                            // Nokia uses X_ALU-COM_IsBeacon (beacon index number)
                            $connIsBeacon = $connHost['X_ALU-COM_IsBeacon'] ?? null;

                            // Nokia: Use STA device field or beacon map to determine connected AP
                            if ($connNokiaSta && isset($connNokiaSta['device'])) {
                                $staDeviceNum = (int)$connNokiaSta['device'];
                                if ($staDeviceNum === 1) {
                                    // Connected to gateway (Device.1)
                                    $connConnectedTo = 'Gateway';
                                    $connConnectedToType = 'gateway';
                                } else {
                                    // Connected to satellite (Device.2+)
                                    // Try to find satellite info from beacon map
                                    $satelliteFound = false;
                                    foreach ($nokiaBeaconMap as $beaconInfo) {
                                        // Beacon map may have satellite details
                                        if (!$satelliteFound) {
                                            $connConnectedTo = 'Satellite';
                                            $connConnectedToType = 'mesh';
                                            if ($beaconInfo['id']) {
                                                $connConnectedTo = $beaconInfo['type'] . ' (' . substr($beaconInfo['serial'], -6) . ')';
                                                $connConnectedToLink = route('devices.show', $beaconInfo['id']);
                                            }
                                            $satelliteFound = true;
                                        }
                                    }
                                    if (!$satelliteFound) {
                                        $connConnectedTo = 'Satellite';
                                        $connConnectedToType = 'mesh';
                                    }
                                }
                            } elseif (!empty($nokiaBeaconMap) && $connIsBeacon !== null) {
                                // Nokia: Fallback to beacon index lookup
                                if (isset($nokiaBeaconMap[$connIsBeacon])) {
                                    $beaconInfo = $nokiaBeaconMap[$connIsBeacon];
                                    $connConnectedTo = $beaconInfo['type'] . ' (' . substr($beaconInfo['serial'], -6) . ')';
                                    if ($beaconInfo['id']) {
                                        $connConnectedToLink = route('devices.show', $beaconInfo['id']);
                                    }
                                    $connConnectedToType = 'mesh';
                                } else {
                                    // Unknown beacon index - might be gateway (index 0 or not in list)
                                    $connConnectedTo = 'Gateway';
                                    $connConnectedToType = 'gateway';
                                }
                            } elseif (empty($connAccessPointMac) || $connAccessPointMac === $gatewayMac) {
                                $connConnectedTo = 'Gateway';
                                $connConnectedToType = 'gateway';
                            } elseif (isset($meshApMap[$connAccessPointMac])) {
                                // Calix: Use MAC to find connected AP
                                $meshInfo = $meshApMap[$connAccessPointMac];
                                $connConnectedTo = $meshInfo['type'] . ' (' . substr($meshInfo['serial'], -6) . ')';
                                $connConnectedToLink = route('devices.show', $meshInfo['id']);
                                $connConnectedToType = 'mesh';
                            } else {
                                // Unknown AP - show MAC
                                $connConnectedTo = 'AP ' . substr($connAccessPointMac, -8);
                                $connConnectedToType = 'unknown';
                            }

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

                            // Nokia: Check STA data for signal strength (0-255 scale needs conversion to dBm)
                            if ($connSignalStrength === null && $connNokiaSta) {
                                $rawSignal = $connNokiaSta['SignalStrength']
                                    ?? $connNokiaSta['X_ALU-COM_EWMA_SignalStrength']
                                    ?? null;
                                if ($rawSignal !== null && (int)$rawSignal > 0) {
                                    // Linear conversion: dBm = -110 + (raw * 110 / 255)
                                    $connSignalStrength = (int)(-110 + ((int)$rawSignal * 110 / 255));
                                }
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

                            // Nokia: Check STA data for rates
                            if ($connDownRate === null && $connNokiaSta) {
                                $connDownRate = $connNokiaSta['LastDataDownlinkRate'] ?? null;
                            }
                            if ($connUpRate === null && $connNokiaSta) {
                                $connUpRate = $connNokiaSta['LastDataUplinkRate'] ?? null;
                            }

                            if ($connDownRate === null && isset($connHost['X_CLEARACCESS_COM_WlanTxRate'])) {
                                $txRate = (int)$connHost['X_CLEARACCESS_COM_WlanTxRate'];
                                if ($txRate > 0) {
                                    $connDownRate = $txRate;
                                }
                            }

                            // Get band for interface display
                            $connBand = null;

                            // Nokia: Use radio number from STA data (1 = 2.4GHz, 2 = 5GHz)
                            if ($connNokiaSta && isset($connNokiaSta['radio'])) {
                                $connBand = ((int)$connNokiaSta['radio'] === 1) ? '2.4GHz' : '5GHz';
                            } elseif ($connWifiData) {
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
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($connHostMac)
                                    <x-mac-address :mac="$connHostMac" />
                                @else
                                    <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($connConnectedToLink)
                                    <a href="{{ $connConnectedToLink }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        <span class="inline-flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                                            {{ $connConnectedTo }}
                                        </span>
                                    </a>
                                @elseif($connConnectedToType === 'gateway')
                                    <span class="inline-flex items-center text-green-600 dark:text-green-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                                        {{ $connConnectedTo }}
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $connConnectedTo ?? '-' }}</span>
                                @endif
                            </td>
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
