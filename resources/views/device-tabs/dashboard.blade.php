{{-- Dashboard Tab --}}
<div x-show="activeTab === 'dashboard'" x-cloak>
    <!-- WAN & LAN Section (Side by Side) -->
    @php
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        $getExactParam = function($name) use ($device) {
            $param = $device->parameters()->where('name', $name)->first();
            return $param ? $param->value : '-';
        };

        // Dynamically discover WAN prefix
        $wanIpParam = $device->parameters()
            ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
            ->first();

        if ($wanIpParam && preg_match('/InternetGatewayDevice\.WANDevice\.(\d+)\.WANConnectionDevice\.(\d+)\.WANIPConnection\.(\d+)\./', $wanIpParam->name, $matches)) {
            $wanPrefix = "InternetGatewayDevice.WANDevice.{$matches[1]}.WANConnectionDevice.{$matches[2]}.WANIPConnection.{$matches[3]}";
        } else {
            $wanPrefix = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
        }

        $status = $isDevice2 ? $getExactParam("Device.IP.Interface.1.Status") : $getExactParam("{$wanPrefix}.ConnectionStatus");
        $lanPrefix = $isDevice2 ? 'Device.IP.Interface.2' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        $dhcpPrefix = $isDevice2 ? 'Device.DHCPv4.Server.Pool.1' : 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        $dhcpEnabled = $isDevice2 ? $getExactParam("{$dhcpPrefix}.Enable") : $getExactParam("{$lanPrefix}.DHCPServerEnable");
    @endphp

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
                            {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.IPAddress") : $getExactParam("{$wanPrefix}.ExternalIPAddress") }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Default Gateway</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono">
                            {{ $isDevice2 ? $getExactParam("Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress") : $getExactParam("{$wanPrefix}.DefaultGateway") }}
                        </dd>
                    </div>
                    <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DNS Servers</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono text-xs">
                            {{ $isDevice2 ? $getExactParam("Device.IP.Interface.1.IPv4Address.1.DNSServers") : $getExactParam("{$wanPrefix}.DNSServers") }}
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
                        <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">DHCP Range</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 font-mono text-xs">
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MinAddress") : $getExactParam("{$lanPrefix}.MinAddress") }}
                            -
                            {{ $isDevice2 ? $getExactParam("{$dhcpPrefix}.MaxAddress") : $getExactParam("{$lanPrefix}.MaxAddress") }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- WiFi Section (Full Width) -->
    @php
        $wifiParams = $device->parameters()
            ->where(function($q) {
                $q->where('name', 'LIKE', '%WiFi.Radio%')
                  ->orWhere('name', 'LIKE', '%WiFi.SSID%')
                  ->orWhere('name', 'LIKE', '%WLANConfiguration%');
            })
            ->get();

        // Organize by radio/SSID
        $dashboardRadios = [];
        foreach ($wifiParams as $param) {
            if (preg_match('/Radio\.(\d+)/', $param->name, $matches) || preg_match('/WLANConfiguration\.(\d+)/', $param->name, $matches)) {
                $radioNum = $matches[1];
                if (!isset($dashboardRadios[$radioNum])) {
                    $dashboardRadios[$radioNum] = [];
                }
                $dashboardRadios[$radioNum][$param->name] = $param->value;
            }
        }
    @endphp

    @if(count($dashboardRadios) > 0)
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 bg-purple-50 dark:bg-purple-900/20">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">WiFi Networks</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-{{ $colors['text-muted'] }}">Wireless network status</p>
        </div>
        <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }} p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($dashboardRadios as $radioNum => $radioData)
                    @php
                        $ssid = '';
                        $enabled = false;
                        $channel = '-';
                        $band = '-';

                        foreach ($radioData as $key => $value) {
                            if (str_contains($key, 'SSID') && !str_contains($key, 'Enable')) {
                                $ssid = $value;
                            } elseif (str_contains($key, 'Enable')) {
                                $enabled = ($value === 'true' || $value === '1');
                            } elseif (str_contains($key, 'Channel')) {
                                $channel = $value;
                            } elseif (str_contains($key, 'OperatingFrequencyBand')) {
                                $band = $value;
                            } elseif (str_contains($key, 'Channel') && is_numeric($value)) {
                                $channel = $value;
                                $band = (int)$value <= 14 ? '2.4GHz' : '5GHz';
                            }
                        }
                    @endphp

                    <button @click="activeTab = 'wifi'" class="w-full bg-gray-50 dark:bg-{{ $colors['bg'] }} hover:bg-gray-100 dark:hover:bg-{{ $colors['card'] }} rounded-lg p-4 border {{ $enabled ? 'border-green-200 hover:border-green-300' : 'border-gray-200 dark:border-' . $colors['border'] . ' hover:border-gray-300' }} transition-colors text-left">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">{{ $ssid ?: "Radio $radioNum" }}</h4>
                            @if($enabled)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Disabled</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }} space-y-1">
                            <div>Band: <span class="font-mono">{{ $band }}</span></div>
                            <div>Channel: <span class="font-mono">{{ $channel }}</span></div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">Click to configure</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Connected Devices Section -->
    @include('device-tabs.partials.connected-devices', ['device' => $device, 'colors' => $colors])

    <!-- Recent Tasks Section -->
    <div class="mt-6 bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Task Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }} uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-{{ $colors['card'] }} divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        @foreach($recentTasks as $task)
                            <tr class="hover:bg-gray-50 dark:hover:bg-{{ $colors['bg'] }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-{{ $colors['text'] }}">
                                    {{ ucwords(str_replace('_', ' ', $task->task_type)) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
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
</div>
