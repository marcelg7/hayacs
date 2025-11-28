{{-- WiFi SSID Card Partial (TR-098 legacy) --}}
@php
    $ssidName = $config['SSID'] ?? '';
    $enabled = ($config['Enable'] ?? '0') === '1';
    $channel = $config['Channel'] ?? '';
    $autoChannel = ($config['AutoChannelEnable'] ?? '0') === '1';
    $ssidBroadcast = ($config['SSIDAdvertisementEnabled'] ?? '1') === '1';
    $bandwidth = $config['X_000631_OperatingChannelBandwidth'] ?? '';
    $autoBandwidth = empty($bandwidth) || $bandwidth === 'Auto';
    $password = $config['X_000631_KeyPassphrase'] ?? '';

    // Determine card styling based on enabled state
    $cardBorder = $enabled ? ($band === '2.4GHz' ? 'border-green-300 dark:border-green-700' : 'border-purple-300 dark:border-purple-700') : 'border-gray-200 dark:border-gray-600';
    $cardBg = $enabled ? 'bg-white dark:bg-gray-800' : 'bg-gray-100 dark:bg-gray-700/50';
@endphp

<div class="border {{ $cardBorder }} rounded-lg p-4 {{ $cardBg }} flex flex-col transition-colors">
    <form @submit.prevent="async (e) => {
        const formData = new FormData(e.target);
        const data = {};

        const checkboxNames = new Set();
        for (const element of e.target.elements) {
            if (element.type === 'checkbox' && element.name) {
                checkboxNames.add(element.name);
            }
        }

        for (let [key, value] of formData.entries()) {
            if (key !== '_token') {
                if (checkboxNames.has(key)) {
                    data[key] = value === '1';
                } else if (value === '') {
                    data[key] = undefined;
                } else {
                    data[key] = value;
                }
            }
        }

        taskLoading = true;
        taskMessage = 'Updating WiFi Configuration...';

        try {
            const response = await fetch('/api/devices/{{ $device->id }}/wifi-config', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.task && result.task.id) {
                startTaskTracking('Updating WiFi Configuration...', result.task.id);
            } else {
                taskLoading = false;
                alert('Configuration updated, but no task ID returned');
            }
        } catch (error) {
            taskLoading = false;
            alert('Error updating configuration: ' + error);
        }
    }" class="space-y-2 h-full" x-data="{
        autoChannel: true,
        autoChannelBandwidth: true
    }">
        @csrf
        <input type="hidden" name="instance" value="{{ $config['instance'] }}">
        <input type="hidden" name="security_type" value="wpa2">

        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2 min-w-0 flex-1">
                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $enabled ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-{{ $colors['text'] }} truncate" title="{{ $ssidName ?: 'SSID ' . $config['instance'] }}">
                    {{ $ssidName ?: 'SSID ' . $config['instance'] }}
                </h4>
            </div>
            <label class="flex items-center flex-shrink-0 ml-2">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                       class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500">
            </label>
        </div>

        <div class="space-y-2 flex-1">
            <!-- SSID Name -->
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-{{ $colors['text-muted'] }}">Network Name</label>
                <input type="text" name="ssid" value="{{ $ssidName }}" maxlength="32"
                       class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-{{ $colors['text-muted'] }}">Password</label>
                <input type="text" name="password" value=""
                       minlength="8" maxlength="63" placeholder="Leave blank to keep current"
                       class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Options row -->
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <label class="flex items-center">
                    <input type="hidden" name="auto_channel" value="0">
                    <input type="checkbox" name="auto_channel" value="1"
                           x-model="autoChannel"
                           class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                    <span class="ml-1.5 text-gray-700 dark:text-{{ $colors['text'] }}">Auto Ch</span>
                </label>
                <label class="flex items-center">
                    <input type="hidden" name="auto_channel_bandwidth" value="0">
                    <input type="checkbox" name="auto_channel_bandwidth" value="1"
                           x-model="autoChannelBandwidth"
                           class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                    <span class="ml-1.5 text-gray-700 dark:text-{{ $colors['text'] }}">Auto BW</span>
                </label>
                <label class="flex items-center">
                    <input type="hidden" name="ssid_broadcast" value="0">
                    <input type="checkbox" name="ssid_broadcast" value="1" {{ $ssidBroadcast ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                    <span class="ml-1.5 text-gray-700 dark:text-{{ $colors['text'] }}">Broadcast</span>
                </label>
            </div>

            <!-- Manual Channel (hidden if auto) -->
            <div x-show="!autoChannel" x-collapse class="mt-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-{{ $colors['text-muted'] }}">Channel</label>
                @if($band === '2.4GHz')
                    <input type="number" name="channel" value="{{ $channel ?: '6' }}" min="1" max="11"
                           class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @else
                    <select name="channel" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="36" {{ $channel === '36' ? 'selected' : '' }}>36</option>
                        <option value="40" {{ $channel === '40' ? 'selected' : '' }}>40</option>
                        <option value="44" {{ $channel === '44' ? 'selected' : '' }}>44</option>
                        <option value="48" {{ $channel === '48' ? 'selected' : '' }}>48</option>
                        <option value="149" {{ $channel === '149' ? 'selected' : '' }}>149</option>
                        <option value="153" {{ $channel === '153' ? 'selected' : '' }}>153</option>
                        <option value="157" {{ $channel === '157' ? 'selected' : '' }}>157</option>
                        <option value="161" {{ $channel === '161' || empty($channel) ? 'selected' : '' }}>161</option>
                        <option value="165" {{ $channel === '165' ? 'selected' : '' }}>165</option>
                    </select>
                @endif
            </div>

            <!-- Manual Bandwidth (hidden if auto) -->
            <div x-show="!autoChannelBandwidth" x-collapse class="mt-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-{{ $colors['text-muted'] }}">Bandwidth</label>
                <select name="channel_bandwidth" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="20MHz" {{ $bandwidth === '20MHz' ? 'selected' : '' }}>20 MHz</option>
                    <option value="40MHz" {{ $bandwidth === '40MHz' ? 'selected' : '' }}>40 MHz</option>
                    @if($band === '5GHz')
                        <option value="80MHz" {{ $bandwidth === '80MHz' ? 'selected' : '' }}>80 MHz</option>
                    @endif
                </select>
            </div>
        </div>

        <div class="mt-auto pt-2 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-1.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Save
            </button>
        </div>
    </form>
</div>
