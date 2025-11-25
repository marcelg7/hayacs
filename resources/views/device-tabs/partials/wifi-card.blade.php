{{-- WiFi SSID Card Partial --}}
<div class="border border-gray-200 dark:border-{{ $colors['border'] }} rounded-lg p-4 bg-gray-50 dark:bg-{{ $colors['bg'] }} flex flex-col">
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
    }" class="space-y-3 h-full" x-data="{
        autoChannel: {{ ($config['AutoChannelEnable'] ?? '0') === '1' ? 'true' : 'false' }},
        autoChannelBandwidth: {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === 'Auto' || empty($config['X_000631_OperatingChannelBandwidth'] ?? '') ? 'true' : 'false' }}
    }">
        @csrf
        <input type="hidden" name="instance" value="{{ $config['instance'] }}">
        <input type="hidden" name="security_type" value="wpa2">

        <div class="flex items-center justify-between mb-3">
            <h4 class="text-md font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">SSID {{ $config['instance'] }}</h4>
            <label class="flex items-center">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" {{ ($config['Enable'] ?? '0') === '1' ? 'checked' : '' }}
                       class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Enabled</span>
            </label>
        </div>

        <div class="space-y-3 flex-1">
            <!-- SSID Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">SSID Name</label>
                <input type="text" name="ssid" value="{{ $config['SSID'] ?? '' }}" maxlength="32"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-1">
                    WiFi Password (WPA2-AES)
                    @if(!empty($config['X_000631_KeyPassphrase']))
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">Password Set</span>
                    @else
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">No Password</span>
                    @endif
                </label>
                <input type="text" name="password" value="{{ $config['X_000631_KeyPassphrase'] ?? '' }}"
                       minlength="8" maxlength="63" placeholder="Enter new password (min 8 characters)"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                    @if(!empty($config['X_000631_KeyPassphrase']) && $config['X_000631_KeyPassphrase'] === '********')
                        <span class="text-green-600 font-medium">Current password: ********</span> (masked) - Leave blank to keep
                    @elseif(!empty($config['X_000631_KeyPassphrase']))
                        Current password shown - Leave blank to keep
                    @else
                        No password currently set - Enter a password (min 8 characters)
                    @endif
                </p>
            </div>

            <!-- Auto Channel -->
            <div>
                <label class="flex items-center">
                    <input type="hidden" name="auto_channel" value="0">
                    <input type="checkbox" name="auto_channel" value="1"
                           x-model="autoChannel"
                           class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Auto Channel</span>
                </label>
                <div x-show="!autoChannel" class="mt-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Manual Channel</label>
                    @if($band === '2.4GHz')
                        <input type="number" name="channel" value="{{ $config['Channel'] ?? '11' }}" min="1" max="11"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @else
                        <select name="channel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="36" {{ ($config['Channel'] ?? '') === '36' ? 'selected' : '' }}>36</option>
                            <option value="40" {{ ($config['Channel'] ?? '') === '40' ? 'selected' : '' }}>40</option>
                            <option value="44" {{ ($config['Channel'] ?? '') === '44' ? 'selected' : '' }}>44</option>
                            <option value="48" {{ ($config['Channel'] ?? '') === '48' ? 'selected' : '' }}>48</option>
                            <option value="149" {{ ($config['Channel'] ?? '') === '149' ? 'selected' : '' }}>149</option>
                            <option value="153" {{ ($config['Channel'] ?? '') === '153' ? 'selected' : '' }}>153</option>
                            <option value="157" {{ ($config['Channel'] ?? '') === '157' ? 'selected' : '' }}>157</option>
                            <option value="161" {{ ($config['Channel'] ?? '161') === '161' ? 'selected' : '' }}>161</option>
                            <option value="165" {{ ($config['Channel'] ?? '') === '165' ? 'selected' : '' }}>165</option>
                        </select>
                    @endif
                </div>
            </div>

            <!-- Channel Bandwidth -->
            <div>
                <label class="flex items-center">
                    <input type="hidden" name="auto_channel_bandwidth" value="0">
                    <input type="checkbox" name="auto_channel_bandwidth" value="1"
                           x-model="autoChannelBandwidth"
                           class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Auto Channel Bandwidth</span>
                </label>
                <div x-show="!autoChannelBandwidth" class="mt-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Channel Bandwidth</label>
                    <select name="channel_bandwidth" class="mt-1 block w-full rounded-md border-gray-300 dark:border-{{ $colors['border'] }} dark:bg-{{ $colors['card'] }} dark:text-{{ $colors['text'] }} shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="20MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '20MHz') === '20MHz' ? 'selected' : '' }}>20 MHz</option>
                        <option value="40MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '') === '40MHz' ? 'selected' : '' }}>40 MHz</option>
                        @if($band === '5GHz')
                            <option value="80MHz" {{ ($config['X_000631_OperatingChannelBandwidth'] ?? '80MHz') === '80MHz' ? 'selected' : '' }}>80 MHz</option>
                        @endif
                    </select>
                </div>
            </div>

            <!-- SSID Broadcast -->
            <div class="flex items-center">
                <input type="hidden" name="ssid_broadcast" value="0">
                <input type="checkbox" name="ssid_broadcast" value="1" {{ ($config['SSIDAdvertisementEnabled'] ?? '1') === '1' ? 'checked' : '' }}
                       class="rounded border-gray-300 dark:border-{{ $colors['border'] }} text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }}">Broadcast SSID</span>
            </div>
        </div>

        <div class="mt-auto pt-3 space-y-2 border-t border-gray-200 dark:border-{{ $colors['border'] }}">
            <div class="text-xs text-gray-600 dark:text-{{ $colors['text-muted'] }} text-center">
                <span class="font-medium">Status:</span> {{ $config['Status'] ?? 'Unknown' }}<br>
                <span class="font-medium">Standard:</span> {{ $config['Standard'] ?? '-' }}
            </div>
            <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-{{ $colors['btn-primary'] }}-600 hover:bg-{{ $colors['btn-primary'] }}-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save
            </button>
        </div>
    </form>
</div>
