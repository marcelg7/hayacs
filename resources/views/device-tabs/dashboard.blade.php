{{-- Dashboard Tab (includes Troubleshooting) --}}
<div x-show="activeTab === 'dashboard'" x-cloak>
    @php
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'TR-181';

        // Use centralized manufacturer detection from Device model
        $isNokia = $device->isNokia();
        $isTr098Nokia = !$isDevice2 && $isNokia;

        // Check if this is a GigaMesh device - uses ExosMesh.WapHostInfo for WAN info
        $isGigaMesh = $device->isMeshDevice() && (
            stripos($device->product_class ?? '', 'gigamesh') !== false ||
            stripos($device->product_class ?? '', 'u4m') !== false ||
            stripos($device->product_class ?? '', 'gm1028') !== false
        );

        // Check if this is an 804Mesh device (standalone WiFi AP, clients tracked via AssociatedDevice)
        $is804Mesh = stripos($device->product_class ?? '', '804mesh') !== false ||
            stripos($device->product_class ?? '', '804Mesh') !== false;

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
        } elseif ($isGigaMesh) {
            // GigaMesh uses ExosMesh.WapHostInfo for mesh backhaul connection info
            $wanPrefix = 'InternetGatewayDevice.X_000631_Device.ExosMesh.WapHostInfo';
            $pppPrefix = null; // No PPP on mesh devices
        } else {
            // First, try to find the ACTIVE WAN connection by looking for ConnectionStatus=Connected
            // This is more reliable than ExternalIPAddress because devices may have multiple connections
            // but only one is the active/connected one with full parameters
            $wanConnectedParam = $device->parameters()
                ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ConnectionStatus')
                ->where('value', 'Connected')
                ->first();

            if ($wanConnectedParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanConnectedParam->name, $matches)) {
                $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
            } else {
                // Fallback: look for any connection with ExternalIPAddress
                $wanIpParam = $device->parameters()
                    ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
                    ->first();

                if ($wanIpParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanIpParam->name, $matches)) {
                    $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
                } else {
                    $wanPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
                }
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
        } elseif ($isGigaMesh) {
            // GigaMesh: WapHostInfo has ConnectionStatus
            $status = $getExactParam("{$wanPrefix}.ConnectionStatus");
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
            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">Click <strong>Get Everything</strong> (above) to fetch all device parameters including WAN, LAN, WiFi, and connected devices.</p>
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

    {{-- Mesh Topology Section - Shows for Nokia gateways and mesh APs --}}
    @if($device->isNokiaGateway() || $device->isNokiaMeshAP())
    @php
        $meshTopology = $device->getMeshTopology();
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }} flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                Mesh Network
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                @if($meshTopology['is_gateway'])
                    Connected mesh access points
                @else
                    Parent gateway connection
                @endif
            </p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            @if($meshTopology['is_gateway'])
                {{-- Gateway view: Show connected mesh APs --}}
                @if(count($meshTopology['children']) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Last Seen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            @foreach($meshTopology['children'] as $child)
                            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $child['serial_number'] }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-300">
                                        {{ $child['display_name'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($child['online'])
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Online</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Offline</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                                    {{ $child['last_inform'] ? \Carbon\Carbon::parse($child['last_inform'])->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <a href="{{ route('device.show', $child['id']) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                        View
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    {{-- Check for embedded satellites in Host table (Nokia TR-098 style) --}}
                    @php
                        $embeddedSatellites = $device->getNokiaBeaconSatellites();
                    @endphp
                    @if(count($embeddedSatellites) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">IP Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">MAC Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Backhaul</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                                @foreach($embeddedSatellites as $satellite)
                                @php
                                    $satMac = strtoupper($satellite['mac'] ?? '');
                                    $satMacLast4 = $satMac ? substr(str_replace(':', '', $satMac), -4) : '';
                                    $satDisplayName = ($satellite['model'] ?? 'Beacon') . ($satMacLast4 ? "-{$satMacLast4}" : '');
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                            </svg>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">{{ $satDisplayName }}</div>
                                                <div class="text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">{{ $satellite['name'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-{{ $colors['text'] }}">{{ $satellite['ip'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($satMac)
                                            <x-mac-address :mac="$satMac" />
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($satellite['active'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                        @php
                                            // Convert Nokia raw signal (0-255) to dBm
                                            $backhaulSignalDbm = null;
                                            if (!empty($satellite['signal_strength'])) {
                                                $rawSig = (int)$satellite['signal_strength'];
                                                if ($rawSig > 0) {
                                                    $backhaulSignalDbm = (int)(-110 + ($rawSig * 110 / 255));
                                                }
                                            }
                                        @endphp
                                        @if($satellite['backhaul'] === 'Wi-Fi' || $satellite['backhaul'] === 'WiFi')
                                            <span class="text-blue-600">{{ $satellite['backhaul'] }}</span>
                                            @if($backhaulSignalDbm !== null && $backhaulSignalDbm < 0)
                                                <span class="ml-1 text-gray-500">({{ $backhaulSignalDbm }} dBm)</span>
                                            @endif
                                        @elseif($satellite['backhaul'] === 'Ethernet')
                                            <span class="text-green-600">{{ $satellite['backhaul'] }}</span>
                                        @else
                                            {{ $satellite['backhaul'] ?? 'Unknown' }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="px-4 py-6 text-center text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                        <p class="mt-2">No mesh access points connected to this gateway</p>
                        <p class="text-sm text-gray-400 mt-1">Mesh APs will appear here once they inform</p>
                    </div>
                    @endif
                @endif
            @else
                {{-- Mesh AP view: Show parent gateway --}}
                <dl>
                    @if($meshTopology['parent'])
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Parent Gateway</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            <a href="{{ route('device.show', $meshTopology['parent']['id']) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                {{ $meshTopology['parent']['serial_number'] }}
                            </a>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-300">
                                {{ $meshTopology['parent']['display_name'] }}
                            </span>
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Gateway Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            @if($meshTopology['parent']['online'])
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Online</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Offline</span>
                            @endif
                        </dd>
                    </div>
                    @if($meshTopology['parent']['subscriber'])
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Subscriber</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            {{ $meshTopology['parent']['subscriber'] }}
                        </dd>
                    </div>
                    @endif
                    @else
                    <div class="px-4 py-6 text-center text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414"></path>
                        </svg>
                        <p class="mt-2">Parent gateway not identified</p>
                        <p class="text-sm text-gray-400 mt-1">Gateway link will appear after device informs with DataElements</p>
                    </div>
                    @endif
                </dl>
            @endif
        </div>
    </div>
    @endif

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
        <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg" x-data="{
            showLanEditModal: false,
            lanConfig: {
                lan_ip: '{{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}',
                subnet_mask: '{{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.SubnetMask") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceSubnetMask") }}',
                dhcp_start: '{{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}',
                dhcp_end: '{{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}'
            },
            originalConfig: {},
            errors: {},
            saving: false,

            openEditModal() {
                this.originalConfig = { ...this.lanConfig };
                this.errors = {};
                this.showLanEditModal = true;
            },

            closeEditModal() {
                this.lanConfig = { ...this.originalConfig };
                this.errors = {};
                this.showLanEditModal = false;
            },

            validateIp(ip) {
                if (!ip || ip === '-') return true;
                const pattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
                return pattern.test(ip);
            },

            validateForm() {
                this.errors = {};
                let valid = true;

                if (this.lanConfig.lan_ip && this.lanConfig.lan_ip !== '-' && !this.validateIp(this.lanConfig.lan_ip)) {
                    this.errors.lan_ip = 'Invalid IP address format';
                    valid = false;
                }
                if (this.lanConfig.dhcp_start && this.lanConfig.dhcp_start !== '-' && !this.validateIp(this.lanConfig.dhcp_start)) {
                    this.errors.dhcp_start = 'Invalid IP address format';
                    valid = false;
                }
                if (this.lanConfig.dhcp_end && this.lanConfig.dhcp_end !== '-' && !this.validateIp(this.lanConfig.dhcp_end)) {
                    this.errors.dhcp_end = 'Invalid IP address format';
                    valid = false;
                }

                return valid;
            },

            async saveLanConfig() {
                if (!this.validateForm()) return;

                this.saving = true;
                this.errors = {};

                try {
                    const payload = {};
                    if (this.lanConfig.lan_ip !== this.originalConfig.lan_ip && this.lanConfig.lan_ip !== '-') {
                        payload.lan_ip = this.lanConfig.lan_ip;
                    }
                    if (this.lanConfig.dhcp_start !== this.originalConfig.dhcp_start && this.lanConfig.dhcp_start !== '-') {
                        payload.dhcp_start = this.lanConfig.dhcp_start;
                    }
                    if (this.lanConfig.dhcp_end !== this.originalConfig.dhcp_end && this.lanConfig.dhcp_end !== '-') {
                        payload.dhcp_end = this.lanConfig.dhcp_end;
                    }

                    if (Object.keys(payload).length === 0) {
                        this.showLanEditModal = false;
                        return;
                    }

                    const response = await fetch('/api/devices/{{ $device->id }}/lan-config', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data.errors) {
                            this.errors = data.errors;
                        } else {
                            this.errors.general = data.message || 'Failed to update LAN configuration';
                        }
                        return;
                    }

                    this.showLanEditModal = false;
                    if (data.task && data.task.id) {
                        startTaskTracking('Updating LAN Configuration (reboot will follow)...', data.task.id);
                    }
                } catch (error) {
                    console.error('Error updating LAN config:', error);
                    this.errors.general = 'Network error: ' + error.message;
                } finally {
                    this.saving = false;
                }
            }
        }">
            <div class="px-4 py-5 sm:px-6 bg-green-50 dark:bg-green-900/20 flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">LAN</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Local network configuration</p>
                </div>
                <button @click="openEditModal()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 dark:text-green-200 dark:bg-green-800 dark:hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </button>
            </div>
            <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                <dl>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">LAN IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono" x-text="lanConfig.lan_ip">
                            {{ $isDevice2 ? $getExactParam("{$lanPrefix}.IPv4Address.1.IPAddress") : $getExactParam("{$lanPrefix}.IPInterface.1.IPInterfaceIPAddress") }}
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Subnet Mask</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono" x-text="lanConfig.subnet_mask">
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
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono" x-text="lanConfig.dhcp_start">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP End</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono" x-text="lanConfig.dhcp_end">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- LAN Edit Modal -->
            <div x-show="showLanEditModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4" @click.self="closeEditModal()">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit LAN Configuration</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update LAN IP and DHCP settings</p>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <!-- General Error -->
                        <div x-show="errors.general" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
                            <p class="text-sm text-red-600 dark:text-red-400" x-text="errors.general"></p>
                        </div>

                        <!-- LAN IP Address -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">LAN IP Address</label>
                            <input type="text" x-model="lanConfig.lan_ip" placeholder="192.168.1.1"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm font-mono"
                                :class="{'border-red-500': errors.lan_ip}">
                            <p x-show="errors.lan_ip" class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="errors.lan_ip"></p>
                        </div>

                        <!-- DHCP Start -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">DHCP Start</label>
                            <input type="text" x-model="lanConfig.dhcp_start" placeholder="192.168.1.2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm font-mono"
                                :class="{'border-red-500': errors.dhcp_start}">
                            <p x-show="errors.dhcp_start" class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="errors.dhcp_start"></p>
                        </div>

                        <!-- DHCP End -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">DHCP End</label>
                            <input type="text" x-model="lanConfig.dhcp_end" placeholder="192.168.1.254"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm font-mono"
                                :class="{'border-red-500': errors.dhcp_end}">
                            <p x-show="errors.dhcp_end" class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="errors.dhcp_end"></p>
                        </div>

                        <!-- Info box -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-3">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                <strong>Note:</strong> DHCP range must be within the same subnet as the LAN IP.
                                The LAN IP cannot be within the DHCP range.
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                        <button @click="closeEditModal()" type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Cancel
                        </button>
                        <button @click="saveLanConfig()" type="button" :disabled="saving"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!saving">Save Changes</span>
                            <span x-show="saving" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Credentials Section -->
    @php
        // Determine admin credential parameters based on device type
        // Nokia TR-098 uses X_Authentication.Account (passwords are WRITE-ONLY, return empty)
        // Calix/other devices use User.1 for customer admin
        if ($device->isNokia()) {
            if ($isDevice2) {
                // Nokia TR-181: User.1 = admin, User.2 = superadmin
                $adminUsernameParam = 'Device.Users.User.1.Username';
                $adminPasswordParam = 'Device.Users.User.1.Password';
                $nokiaTr098WriteOnly = false;
            } else {
                // Nokia TR-098: X_Authentication.Account = customer admin
                // WARNING: Password is WRITE-ONLY - returns empty when read
                $adminUsernameParam = 'InternetGatewayDevice.X_Authentication.Account.UserName';
                $adminPasswordParam = 'InternetGatewayDevice.X_Authentication.Account.Password';
                $nokiaTr098WriteOnly = true;
            }
        } else {
            $adminUsernameParam = 'InternetGatewayDevice.User.1.Username';
            $adminPasswordParam = 'InternetGatewayDevice.User.1.Password';
            $nokiaTr098WriteOnly = false;
        }
        $adminUsername = $getExactParam($adminUsernameParam);
        $adminPassword = $getExactParam($adminPasswordParam);
    @endphp
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6" x-data="{
        showPassword: false,
        showResetModal: false,
        customPassword: '',
        useCustomPassword: false,
        resetting: false,
        newPassword: null,
        adminPassword: '{{ $adminPassword !== '-' ? addslashes($adminPassword) : '' }}',

        async resetPassword() {
            this.resetting = true;
            try {
                const payload = {};
                if (this.useCustomPassword && this.customPassword) {
                    payload.password = this.customPassword;
                }

                const response = await fetch('/api/devices/{{ $device->id }}/admin-credentials/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    alert(data.message || 'Failed to reset password');
                    return;
                }

                this.newPassword = data.new_password;
                this.adminPassword = data.new_password;
                this.showResetModal = false;
                this.customPassword = '';
                this.useCustomPassword = false;

                if (data.task && data.task.id) {
                    startTaskTracking('Resetting Admin Password...', data.task.id);
                }
            } catch (error) {
                console.error('Error resetting password:', error);
                alert('Network error: ' + error.message);
            } finally {
                this.resetting = false;
            }
        }
    }">
        <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20 flex justify-between items-center">
            <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }} flex items-center">
                    <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                    Admin Credentials
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Customer login for device GUI</p>
            </div>
            <button @click="showResetModal = true" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 dark:text-yellow-200 dark:bg-yellow-800 dark:hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Reset Password
            </button>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <dl>
                <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Username</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                        {{ $adminUsername !== '-' ? $adminUsername : 'admin' }}
                    </dd>
                </div>
                <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Password</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono flex items-center">
                        <template x-if="adminPassword">
                            <span>
                                <span x-show="!showPassword">••••••••</span>
                                <span x-show="showPassword" x-text="adminPassword"></span>
                                <button @click="showPassword = !showPassword" class="ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                </button>
                                <button @click="navigator.clipboard.writeText(adminPassword)" class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Copy to clipboard">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </span>
                        </template>
                        <template x-if="!adminPassword">
                            @if(isset($nokiaTr098WriteOnly) && $nokiaTr098WriteOnly)
                            <span class="text-gray-400 dark:text-gray-500 italic">Write-only (use Reset Password to set)</span>
                            @else
                            <span class="text-gray-400 dark:text-gray-500 italic">Not available - run Get Everything</span>
                            @endif
                        </template>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Reset Password Modal -->
        <div x-show="showResetModal" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4" @click.self="showResetModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reset Admin Password</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set a new password for the customer's device login</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <!-- Option: Random or Custom -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" x-model="useCustomPassword" class="rounded border-gray-300 text-yellow-600 shadow-sm focus:border-yellow-500 focus:ring focus:ring-yellow-500 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Use custom password</span>
                        </label>
                    </div>

                    <!-- Custom Password Input -->
                    <div x-show="useCustomPassword">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Custom Password</label>
                        <input type="text" x-model="customPassword" placeholder="Enter new password (min 6 chars)"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm font-mono">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Password must be 6-32 characters</p>
                    </div>

                    <!-- Info -->
                    <div x-show="!useCustomPassword" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-3">
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            A random 8-character password will be generated. You can provide this to the customer.
                        </p>
                    </div>

                    <!-- New password display (after reset) -->
                    <div x-show="newPassword" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-3">
                        <p class="text-sm text-green-700 dark:text-green-300 mb-2">New password set:</p>
                        <p class="font-mono text-lg text-green-800 dark:text-green-200" x-text="newPassword"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <button @click="showResetModal = false; newPassword = null; customPassword = ''; useCustomPassword = false;" type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                        Close
                    </button>
                    <button @click="resetPassword()" type="button" :disabled="resetting || (useCustomPassword && (!customPassword || customPassword.length < 6))"
                        class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 border border-transparent rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!resetting">Reset Password</span>
                        <span x-show="resetting" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Resetting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mesh Network Section (only shown for mesh devices or gateways with satellites) -->
    <!-- Note: Nokia gateways/mesh APs are handled by the section above using getMeshTopology() -->
    @php
        $isMeshDevice = $device->isMeshDevice();
        $meshSatellites = $device->getMeshSatellites(); // Calix satellites (Device models)
        $nokiaSatellites = $device->getNokiaBeaconSatellites(); // Nokia satellites (array of info)
        // Don't show this section for Nokia gateway/mesh APs as they're handled above
        $isNokiaMeshHandledAbove = $device->isNokiaGateway() || $device->isNokiaMeshAP();
        $hasMeshInfo = !$isNokiaMeshHandledAbove && ($isMeshDevice || $meshSatellites->count() > 0 || count($nokiaSatellites) > 0);
    @endphp

    @if($hasMeshInfo)
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                <svg class="inline-block w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                Mesh Network
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                @if($isMeshDevice)
                    This is a mesh access point
                @else
                    Mesh satellites connected to this gateway
                @endif
            </p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            @if($isMeshDevice)
                @php
                    $meshInfo = $device->getMeshInfo();
                    $gateway = $device->getMeshGateway();
                @endphp
                <dl>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Role</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                {{ $meshInfo['role'] ?? 'Mesh AP' }}
                            </span>
                        </dd>
                    </div>
                    @if($gateway)
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Gateway</dt>
                        <dd class="mt-1 text-sm sm:mt-0 sm:col-span-2">
                            <a href="{{ route('device.show', $gateway->id) }}" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 font-medium">
                                {{ $gateway->subscriber?->name ?? $gateway->serial_number }}
                            </a>
                            <span class="text-gray-500 dark:text-{{ $colors['text-muted'] }} text-xs ml-2">({{ $gateway->display_name }})</span>
                        </dd>
                    </div>
                    @endif
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Backhaul</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                            @if($meshInfo['backhaul'] === 'WiFi')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $meshInfo['signal_strength'] !== null && $meshInfo['signal_strength'] >= -60 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($meshInfo['signal_strength'] !== null && $meshInfo['signal_strength'] >= -70 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                    WiFi
                                </span>
                                @if($meshInfo['signal_strength'] !== null)
                                    <span class="ml-2 font-mono text-sm {{ $meshInfo['signal_strength'] >= -60 ? 'text-green-600' : ($meshInfo['signal_strength'] >= -70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $meshInfo['signal_strength'] }} dBm ({{ $meshInfo['signal_quality'] }})
                                    </span>
                                @endif
                            @elseif($meshInfo['backhaul'] === 'Ethernet')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    Ethernet
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">{{ $meshInfo['backhaul'] ?? 'Unknown' }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif

            @if($meshSatellites->count() > 0)
                <div class="px-4 py-3 {{ $isMeshDevice ? 'border-t border-gray-200 dark:border-' . $colors['border'] : '' }}">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">
                        Connected Mesh Satellites ({{ $meshSatellites->count() }})
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Device</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Model</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Backhaul</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Signal</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                                @foreach($meshSatellites as $satellite)
                                    @php
                                        $satMeshInfo = $satellite->getMeshInfo();
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                        <td class="px-4 py-2 text-sm">
                                            <a href="{{ route('device.show', $satellite->id) }}" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 font-medium">
                                                {{ $satellite->subscriber?->name ?? $satellite->serial_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                            {{ $satellite->display_name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            @if(($satMeshInfo['backhaul'] ?? null) === 'WiFi')
                                                <span class="px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">WiFi</span>
                                            @elseif(($satMeshInfo['backhaul'] ?? null) === 'Ethernet')
                                                <span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">Ethernet</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            @if(($satMeshInfo['backhaul'] ?? null) === 'WiFi' && ($satMeshInfo['signal_strength'] ?? null) !== null)
                                                <span class="font-mono {{ $satMeshInfo['signal_strength'] >= -60 ? 'text-green-600' : ($satMeshInfo['signal_strength'] >= -70 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $satMeshInfo['signal_strength'] }} dBm
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            @if($satellite->online)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Online</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Offline</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Nokia Beacon Satellites (embedded in gateway parameters, not separate devices) --}}
            @if(count($nokiaSatellites) > 0)
                <div class="px-4 py-3 {{ ($isMeshDevice || $meshSatellites->count() > 0) ? 'border-t border-gray-200 dark:border-' . $colors['border'] : '' }}">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">
                        Nokia Beacon Satellites ({{ count($nokiaSatellites) }})
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Device</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Model</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">IP Address</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">MAC Address</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                                @foreach($nokiaSatellites as $sat)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                            {{ $sat['name'] }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                            {{ $sat['model'] }}
                                        </td>
                                        <td class="px-4 py-2 text-sm font-mono text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                            {{ $sat['ip'] ?: '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm font-mono text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                            <x-mac-address :mac="$sat['mac']" />
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            @if($sat['active'])
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Connected Devices Section -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow sm:rounded-lg mb-6"
         x-data="{
             refreshingSignal: false,
             taskId: null,
             pollInterval: null,
             async startRefresh() {
                 this.refreshingSignal = true;
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
                         this.taskId = data.task.id;
                         $dispatch('show-toast', { message: 'Signal refresh started (Task #' + data.task.id + '). Page will refresh when complete.', type: 'success' });
                         this.startPolling();
                     } else {
                         $dispatch('show-toast', { message: data.message || 'Failed to refresh', type: 'error' });
                         this.refreshingSignal = false;
                     }
                 } catch (e) {
                     $dispatch('show-toast', { message: 'Network error', type: 'error' });
                     this.refreshingSignal = false;
                 }
             },
             startPolling() {
                 this.pollInterval = setInterval(async () => {
                     try {
                         const response = await fetch('/api/tasks/' + this.taskId, {
                             credentials: 'include'
                         });
                         const data = await response.json();
                         if (data.status === 'completed' || data.status === 'failed' || data.status === 'cancelled') {
                             clearInterval(this.pollInterval);
                             if (data.status === 'completed') {
                                 $dispatch('show-toast', { message: 'Signal data updated. Refreshing page...', type: 'success' });
                                 setTimeout(() => window.location.reload(), 1000);
                             } else {
                                 $dispatch('show-toast', { message: 'Task ' + data.status, type: 'error' });
                                 this.refreshingSignal = false;
                             }
                         }
                     } catch (e) {
                         // Keep polling on error
                     }
                 }, 2000);
                 // Stop polling after 2 minutes max
                 setTimeout(() => {
                     if (this.pollInterval) {
                         clearInterval(this.pollInterval);
                         this.refreshingSignal = false;
                     }
                 }, 120000);
             }
         }">
        <div class="px-4 py-5 sm:px-6 bg-yellow-50 dark:bg-yellow-900/20">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Connected Devices</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Devices connected to this gateway</p>
                </div>
                <button type="button"
                        @click="startRefresh()"
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

                // Nokia: Build STA data lookup for signal/rates (DataElements.Network.Device.*.Radio.*.BSS.*.STA.*)
                $nokiaStaByMac = [];
                if ($isNokia) {
                    $staParams = $device->parameters()
                        ->where('name', 'LIKE', '%DataElements.Network.Device.%.Radio.%.BSS.%.STA.%')
                        ->get();

                    $staData = [];
                    foreach ($staParams as $param) {
                        if (preg_match('/Device\.(\d+)\.Radio\.(\d+)\.BSS\.(\d+)\.STA\.(\d+)\.(.+)/', $param->name, $m)) {
                            $staKey = "D{$m[1]}R{$m[2]}B{$m[3]}S{$m[4]}";
                            $field = $m[5];
                            if (!isset($staData[$staKey])) {
                                $staData[$staKey] = ['device' => $m[1], 'radio' => $m[2]];
                            }
                            $staData[$staKey][$field] = $param->value;
                        }
                    }

                    // Build lookup by MAC (keep entry with best data)
                    foreach ($staData as $sta) {
                        $mac = $sta['MACAddress'] ?? null;
                        if ($mac) {
                            $macNorm = strtolower(str_replace([':', '-'], '', $mac));
                            $newEntry = [
                                'LastDataDownlinkRate' => $sta['LastDataDownlinkRate'] ?? null,
                                'LastDataUplinkRate' => $sta['LastDataUplinkRate'] ?? null,
                                'SignalStrength' => $sta['SignalStrength'] ?? null,
                                'X_ALU-COM_EWMA_SignalStrength' => $sta['X_ALU-COM_EWMA_SignalStrength'] ?? null,
                                'device' => $sta['device'],
                                'radio' => $sta['radio'],
                            ];
                            $hasData = ($newEntry['SignalStrength'] > 0) || ($newEntry['LastDataDownlinkRate'] > 0);
                            if (!isset($nokiaStaByMac[$macNorm]) || $hasData) {
                                $nokiaStaByMac[$macNorm] = $newEntry;
                            }
                        }
                    }
                }

                // Nokia: Build satellite AP lookup by Device.{n} number for friendly "Connected To" names
                $nokiaSatelliteByDeviceNum = [];
                $nokiaSatelliteByMac = [];  // MAC-based lookup for Connected Devices backhaul info
                if ($isNokia) {
                    // Get satellites from Host table (model detection via hostname)
                    $satellites = $device->getNokiaBeaconSatellites();

                    // Get Device.{n}.ID params to map MAC to device number
                    $deviceIdParams = $device->parameters()
                        ->where('name', 'LIKE', 'InternetGatewayDevice.DataElements.Network.Device.%.ID')
                        ->where('name', 'NOT LIKE', '%.Radio.%')  // Exclude Radio IDs
                        ->get();

                    // Map MAC → Device number
                    $macToDeviceNum = [];
                    foreach ($deviceIdParams as $param) {
                        if (preg_match('/Device\.(\d+)\.ID$/', $param->name, $m)) {
                            $mac = strtolower(str_replace([':', '-'], '', $param->value));
                            $macToDeviceNum[$mac] = (int)$m[1];
                        }
                    }

                    // For each satellite, find its device number and store friendly name + backhaul info
                    foreach ($satellites as $sat) {
                        $satMac = strtolower(str_replace([':', '-'], '', $sat['mac'] ?? ''));
                        if ($satMac) {
                            $macLast4 = strtoupper(substr(str_replace([':', '-'], '', $sat['mac']), -4));
                            $model = $sat['model'] ?? 'Beacon';

                            // Store satellite info by MAC for Connected Devices lookup
                            $nokiaSatelliteByMac[$satMac] = [
                                'name' => "{$model}-{$macLast4}",
                                'model' => $model,
                                'mac' => $sat['mac'],
                                'ip' => $sat['ip'] ?? null,
                                'backhaul' => $sat['backhaul'] ?? null,
                                'signal_strength' => $sat['signal_strength'] ?? null,
                            ];

                            // Also store by device number if we have the mapping
                            if (isset($macToDeviceNum[$satMac])) {
                                $deviceNum = $macToDeviceNum[$satMac];
                                $nokiaSatelliteByDeviceNum[$deviceNum] = $nokiaSatelliteByMac[$satMac];
                            }
                        }
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
                    } elseif (str_contains($hostname, 'beacon') || str_contains($hostname, 'nokia wifi') || str_contains($hostname, '804mesh') || str_contains($hostname, 'gigamesh')) {
                        // Mesh AP / WiFi Extender - detected by hostname patterns
                        return ['type' => 'WiFi AP', 'icon' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0'];
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

                // For 804Mesh devices, build hosts from AssociatedDevice (the Hosts table has bogus data)
                if ($is804Mesh) {
                    $hosts = [];
                    foreach ($wifiDevices as $idx => $wifiDevice) {
                        $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? '';
                        if (!$mac) continue;

                        // Convert AssociatedDevice entry to host format
                        $hosts[$idx] = [
                            'Active' => '1',
                            'MACAddress' => $mac,
                            'IPAddress' => $wifiDevice['AssociatedDeviceIPAddress'] ?? '',
                            'HostName' => $wifiDevice['HostName'] ?? 'WiFi Client',
                            'InterfaceType' => 'WiFi',
                            // Pass through signal/rate data
                            'X_000631_SignalStrength' => $wifiDevice['X_000631_SignalStrength'] ?? null,
                            'X_CLEARACCESS_COM_WlanRssi' => $wifiDevice['X_000631_Metrics.RSSIUpstream'] ?? null,
                            'X_CLEARACCESS_COM_WlanTxRate' => $wifiDevice['X_000631_Metrics.PhyRateTx'] ?? null,
                            'X_CLEARACCESS_COM_WlanRxRate' => $wifiDevice['X_000631_Metrics.PhyRateRx'] ?? null,
                        ];
                    }
                }

                // Build mesh AP map for "Connected To" column (Calix GigaSpire)
                $meshApMap = [];
                $gatewayMac = '';

                if ($device->isCalix()) {
                    // Get main router MAC from ExosMesh.WapHostInfo.MACAddress
                    $gatewayMacParam = $device->parameters()
                        ->where('name', 'LIKE', '%ExosMesh.WapHostInfo.MACAddress')
                        ->first();
                    if ($gatewayMacParam && $gatewayMacParam->value) {
                        $gatewayMac = strtolower($gatewayMacParam->value);
                    }

                    // Get mesh satellite info from ExosMesh.Wap.{i}
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
                        if ($wapMac && $wapMac !== 'n/a') {
                            $wapDevice = $wapSerial ? \App\Models\Device::where('serial_number', $wapSerial)->first() : null;
                            $meshApMap[$wapMac] = [
                                'id' => $wapDevice?->id,
                                'serial' => $wapSerial ?? 'Unknown',
                                'name' => $info['WapLocation'] ?? ($wapSerial ? substr($wapSerial, -6) : 'Satellite'),
                            ];
                        }
                    }
                }

                // For gateway devices (ONT/844E), also fetch clients from sibling 804Mesh devices
                $sibling804MeshDevices = [];
                $isGateway = stripos($device->product_class ?? '', 'ont') !== false ||
                    stripos($device->product_class ?? '', '844') !== false ||
                    stripos($device->product_class ?? '', '854') !== false;

                if ($isGateway && $device->subscriber_id) {
                    $sibling804MeshDevices = \App\Models\Device::where('subscriber_id', $device->subscriber_id)
                        ->where('id', '!=', $device->id)
                        ->where(function($q) {
                            $q->where('product_class', 'LIKE', '%804%')
                              ->orWhere('product_class', 'LIKE', '%Mesh%');
                        })
                        ->with(['parameters' => function($q) {
                            $q->where(function($q2) {
                                $q2->where('name', 'LIKE', '%AssociatedDevice%')
                                   ->orWhere('name', 'LIKE', '%WLANConfiguration%MACAddress');
                            });
                        }])
                        ->get();

                    // Add 804Mesh clients to hosts array with "Connected To" showing which AP
                    foreach ($sibling804MeshDevices as $meshDevice) {
                        $meshMacParam = $meshDevice->parameters->first(fn($p) => str_contains($p->name, 'WLANConfiguration') && str_contains($p->name, 'MACAddress') && !str_contains($p->name, 'AssociatedDevice'));
                        $meshMac = $meshMacParam ? strtolower($meshMacParam->value) : '';
                        $meshName = $meshDevice->serial_number ? substr($meshDevice->serial_number, -6) : 'AP';

                        // Parse AssociatedDevice entries for this 804Mesh
                        $meshWifiDevices = [];
                        foreach ($meshDevice->parameters as $param) {
                            if (preg_match('/AssociatedDevice\.(\d+)\.(.+)/', $param->name, $m)) {
                                $num = $m[1];
                                $field = $m[2];
                                if (!isset($meshWifiDevices[$num])) $meshWifiDevices[$num] = [];
                                $meshWifiDevices[$num][$field] = $param->value;
                            }
                        }

                        // Add each client to hosts
                        foreach ($meshWifiDevices as $idx => $wifiDevice) {
                            $mac = $wifiDevice['AssociatedDeviceMACAddress'] ?? '';
                            if (!$mac) continue;

                            $hostKey = '804mesh_' . $meshDevice->id . '_' . $idx;
                            $hosts[$hostKey] = [
                                'Active' => '1',
                                'MACAddress' => $mac,
                                'IPAddress' => $wifiDevice['AssociatedDeviceIPAddress'] ?? '',
                                'HostName' => $wifiDevice['HostName'] ?? 'WiFi Client',
                                'InterfaceType' => 'WiFi',
                                'X_000631_SignalStrength' => $wifiDevice['X_000631_SignalStrength'] ?? null,
                                'X_CLEARACCESS_COM_WlanRssi' => $wifiDevice['X_000631_Metrics.RSSIUpstream'] ?? null,
                                'X_CLEARACCESS_COM_WlanTxRate' => $wifiDevice['X_000631_Metrics.PhyRateTx'] ?? null,
                                'X_CLEARACCESS_COM_WlanRxRate' => $wifiDevice['X_000631_Metrics.PhyRateRx'] ?? null,
                                // Mark which 804Mesh this client is connected to
                                '_804mesh_device_id' => $meshDevice->id,
                                '_804mesh_name' => $meshName,
                                '_804mesh_serial' => $meshDevice->serial_number,
                            ];
                        }
                    }
                }
            @endphp

            @if(count($hosts) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Device</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">IP Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">MAC Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Connected To</th>
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
                                $nokiaSta = $nokiaStaByMac[$normalizedMac] ?? null;

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

                                // Nokia STA data fallback for signal strength
                                // Run if signal is null OR invalid (0 or positive - valid dBm is always negative)
                                if (($signalStrength === null || (int)$signalStrength >= 0) && $nokiaSta) {
                                    // Nokia DataElements uses 0-255 scale (0=-110dBm, 255=0dBm)
                                    $rawSignal = $nokiaSta['SignalStrength'] ?? $nokiaSta['X_ALU-COM_EWMA_SignalStrength'] ?? null;
                                    if ($rawSignal !== null && (int)$rawSignal > 0) {
                                        // Linear conversion: dBm = -110 + (raw * 110 / 255)
                                        $signalStrength = (int)(-110 + ((int)$rawSignal * 110 / 255));
                                    }
                                }

                                if ($signalStrength === null && isset($host['X_CLEARACCESS_COM_WlanRssi'])) {
                                    $rssi = (int)$host['X_CLEARACCESS_COM_WlanRssi'];
                                    if ($rssi !== 0) {
                                        $signalStrength = $rssi;
                                    }
                                }

                                // Only show signal if it's a valid negative dBm value (0 or positive means no data)
                                if ($signalStrength !== null && (int)$signalStrength < 0) {
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
                                $downRate = null;
                                $upRate = null;
                                if ($wifiData) {
                                    $downRate = $wifiData['LastDataDownlinkRate']
                                        ?? $wifiData['X_000631_LastDataDownlinkRate']
                                        ?? $wifiData['X_000631_Metrics.PhyRateTx']
                                        ?? null;
                                    $upRate = $wifiData['LastDataUplinkRate']
                                        ?? $wifiData['X_000631_LastDataUplinkRate']
                                        ?? $wifiData['X_000631_Metrics.PhyRateRx']
                                        ?? null;
                                }

                                // Nokia STA data fallback for rates (values in kbps)
                                // Use Nokia STA if rate is null or 0 (WLAN AssociatedDevice rates are often stale zeros)
                                if (($downRate === null || (int)$downRate <= 0) && $nokiaSta && !empty($nokiaSta['LastDataDownlinkRate'])) {
                                    $downRate = (int)$nokiaSta['LastDataDownlinkRate'];
                                }
                                if (($upRate === null || (int)$upRate <= 0) && $nokiaSta && !empty($nokiaSta['LastDataUplinkRate'])) {
                                    $upRate = (int)$nokiaSta['LastDataUplinkRate'];
                                }

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

                                // MAC-based fallback: if type is Unknown but MAC matches a known satellite, it's a WiFi AP
                                if ($deviceTypeInfo['type'] === 'Unknown' && isset($nokiaSatelliteByMac[$normalizedMac])) {
                                    $deviceTypeInfo = ['type' => 'WiFi AP', 'icon' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0'];
                                }

                                // Nokia: detect band from STA radio number (Radio 1 = 2.4GHz, Radio 2 = 5GHz typically)
                                $nokiaBand = null;
                                if ($nokiaSta && isset($nokiaSta['radio'])) {
                                    $nokiaBand = ((int)$nokiaSta['radio'] === 1) ? '2.4GHz' : '5GHz';
                                }

                                $interfaceType = $host['InterfaceType'] ?? $host['AddressSource'] ?? null;
                                if ($band) {
                                    $interfaceType = "WiFi ({$band})";
                                } elseif ($nokiaBand) {
                                    // Use Nokia radio info to determine band
                                    $interfaceType = "WiFi ({$nokiaBand})";
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

                                // Determine "Connected To" AP (Calix GigaSpire uses X_000631_AccessPoint)
                                $connectedTo = null;
                                $connectedToLink = null;
                                $accessPointMac = strtolower($host['X_000631_AccessPoint'] ?? '');

                                // Check if this client came from a sibling 804Mesh device
                                if (isset($host['_804mesh_device_id'])) {
                                    $connectedTo = '804Mesh (' . $host['_804mesh_name'] . ')';
                                    $connectedToLink = route('device.show', $host['_804mesh_device_id']);
                                } elseif ($is804Mesh) {
                                    // On 804Mesh device itself, all clients are connected to this AP
                                    $connectedTo = 'This AP';
                                } elseif ($device->isCalix() && $accessPointMac) {
                                    if ($accessPointMac === $gatewayMac) {
                                        $connectedTo = 'Gateway';
                                    } elseif (isset($meshApMap[$accessPointMac])) {
                                        $meshInfo = $meshApMap[$accessPointMac];
                                        $connectedTo = 'Satellite (' . $meshInfo['name'] . ')';
                                        if ($meshInfo['id']) {
                                            $connectedToLink = route('device.show', $meshInfo['id']);
                                        }
                                    } else {
                                        $connectedTo = 'AP ' . substr($accessPointMac, -8);
                                    }
                                } elseif ($device->isCalix() && $gatewayMac) {
                                    // No X_000631_AccessPoint means Ethernet or directly connected to gateway
                                    $connectedTo = str_contains(strtolower($interfaceType), 'ethernet') ? 'Gateway (Ethernet)' : 'Gateway';
                                } elseif ($isNokia && $nokiaSta) {
                                    // Nokia: Determine which network device the client is connected to
                                    $deviceNum = (int)($nokiaSta['device'] ?? 1);
                                    if ($deviceNum === 1) {
                                        $connectedTo = 'Gateway';
                                    } elseif (isset($nokiaSatelliteByDeviceNum[$deviceNum])) {
                                        // Use friendly name like "Beacon 3.1-2731"
                                        $connectedTo = $nokiaSatelliteByDeviceNum[$deviceNum]['name'];
                                    } else {
                                        // Fallback if satellite not in lookup
                                        $connectedTo = "Beacon AP #{$deviceNum}";
                                    }
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
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($connectedTo)
                                        @if($connectedToLink)
                                            <a href="{{ $connectedToLink }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $connectedTo }}</a>
                                        @else
                                            <span class="text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $connectedTo }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400 dark:text-{{ $colors['text-muted'] }}">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">{{ $deviceTypeInfo['type'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">
                                    @php
                                        // Check if this host is a mesh AP satellite - show backhaul info
                                        $hostSatellite = $nokiaSatelliteByMac[$normalizedMac] ?? null;
                                    @endphp
                                    @if($hostSatellite && $hostSatellite['backhaul'])
                                        @php
                                            $backhaulType = $hostSatellite['backhaul'];
                                            $backhaulSignal = null;
                                            if (($backhaulType === 'Wi-Fi' || $backhaulType === 'WiFi') && !empty($hostSatellite['signal_strength'])) {
                                                $rawSig = (int)$hostSatellite['signal_strength'];
                                                if ($rawSig > 0) {
                                                    $backhaulSignal = (int)(-110 + ($rawSig * 110 / 255));
                                                }
                                            }
                                        @endphp
                                        Backhaul:
                                        @if($backhaulType === 'Wi-Fi' || $backhaulType === 'WiFi')
                                            <span class="text-blue-600">Wi-Fi</span>
                                            @if($backhaulSignal !== null && $backhaulSignal < 0)
                                                <span class="text-gray-500">({{ $backhaulSignal }} dBm)</span>
                                            @endif
                                        @elseif($backhaulType === 'Ethernet')
                                            <span class="text-green-600">Ethernet</span>
                                        @else
                                            {{ $backhaulType }}
                                        @endif
                                    @else
                                        {{ $interfaceType }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($signalStrength !== null && (int)$signalStrength < 0)
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
