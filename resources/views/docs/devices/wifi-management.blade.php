@extends('docs.layout')

@section('docs-content')
<h1>WiFi Configuration</h1>

<p class="lead">
    Hay ACS allows you to remotely configure WiFi settings including network names (SSIDs), passwords, and advanced options.
</p>

<h2>Accessing WiFi Settings</h2>

<ol>
    <li>Navigate to the device (search by serial number or browse the device list)</li>
    <li>Click on the <strong>WiFi</strong> tab</li>
</ol>

<h2>Standard WiFi Setup</h2>

<p>
    The "Standard WiFi Setup" section provides a simplified interface for common WiFi configuration tasks.
</p>

<h3>What Standard WiFi Setup Configures</h3>

<p>When you apply Standard WiFi Setup, the system creates:</p>

<ul>
    <li><strong>Primary Network</strong> - Band-steered SSID (same name on 2.4GHz and 5GHz, device automatically selects best band)</li>
    <li><strong>Dedicated 2.4GHz Network</strong> - Named "{SSID}-2.4GHz" for devices that need 2.4GHz only (smart home devices, older equipment)</li>
    <li><strong>Dedicated 5GHz Network</strong> - Named "{SSID}-5GHz" for high-performance connections</li>
    <li><strong>Guest Network</strong> (optional) - Named "{SSID}-Guest" with client isolation for visitor access</li>
</ul>

<h3>Using Standard WiFi Setup</h3>

<ol>
    <li>Enter the desired <strong>Primary SSID</strong> (network name)</li>
    <li>Enter the <strong>Primary Password</strong></li>
    <li>Optionally check <strong>Enable Guest Network</strong> and enter guest password</li>
    <li>Click <strong>Apply Standard WiFi Config</strong></li>
</ol>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Password visibility:</strong> Click the eye icon next to password fields to reveal the current password on Calix devices.
        </p>
    </div>
</div>

<h2>Individual Network Configuration</h2>

<p>
    Below the Standard WiFi Setup, you'll see individual network cards for each WiFi network. This allows you to:
</p>

<ul>
    <li>Change SSID or password for a single network</li>
    <li>Enable or disable specific networks</li>
    <li>View current settings</li>
</ul>

<h3>To Change WiFi Settings</h3>

<ol>
    <li>Find the network card (2.4GHz Primary, 5GHz Primary, etc.)</li>
    <li>Edit the SSID or password field</li>
    <li>Click <strong>Apply</strong> on that card</li>
</ol>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Important:</strong> Changing WiFi settings will temporarily disconnect all devices on that network. Warn the customer before making changes.
        </p>
    </div>
</div>

<h2>WiFi Task Processing Time</h2>

<p>Different device types process WiFi changes at different speeds:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Device Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Typical Time</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaCenters</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">10-30 seconds</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Fast response</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaSpires</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">10-30 seconds</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Fast response</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Nokia Beacon 6 (TR-181)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-3 minutes per radio</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device processes changes slowly but verifies automatically</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">SmartRG</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">15-60 seconds</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">One task per session</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Task Manager</h2>

<p>
    When you apply WiFi changes, the Task Manager appears in the bottom-right corner showing task progress:
</p>

<ul>
    <li><strong>Yellow/spinning</strong> - Task in progress</li>
    <li><strong>Green checkmark</strong> - Task completed successfully</li>
    <li><strong>Red X</strong> - Task failed (click for details)</li>
</ul>

<p>The page automatically refreshes when WiFi tasks complete.</p>

<h2>WiFi Password Guidelines</h2>

<p>For best compatibility:</p>

<ul>
    <li>Use 8-63 characters</li>
    <li>Avoid special characters that may confuse devices: <code>\ " '</code></li>
    <li>Simple alphanumeric passwords work best for customer ease of use</li>
</ul>

<h2>Troubleshooting WiFi Changes</h2>

<h3>Task stuck as "Sent"?</h3>

<p>For Nokia Beacon 6 devices, this is normal. The device processes WiFi changes slowly (2-3 minutes). The system will automatically verify the changes when the device responds.</p>

<h3>Task shows "Failed" but WiFi seems to work?</h3>

<p>Some tasks may timeout during verification but still apply successfully. Check the current WiFi settings to confirm.</p>

<h3>Password didn't change?</h3>

<p>Try the following:</p>

<ol>
    <li>Click <strong>Refresh</strong> to update the displayed values</li>
    <li>Check the Tasks tab for error details</li>
    <li>Verify the device is online</li>
    <li>Try the operation again</li>
</ol>

<h3>Customer can't connect after password change?</h3>

<ol>
    <li>Have them "forget" the old network on their device</li>
    <li>Reconnect using the new password</li>
    <li>Some devices cache credentials - may need to restart the customer's device</li>
</ol>

<h2>WiFi Scan (Interference Detection)</h2>

<p>
    The <strong>WiFi Scan</strong> tab shows nearby networks and potential interference. Use this to:
</p>

<ul>
    <li>Identify crowded WiFi channels</li>
    <li>Detect neighboring network signal strength</li>
    <li>Recommend better channel placement</li>
</ul>

<h2>Best Practices</h2>

<ul>
    <li>Always tell customers before changing WiFi - they'll be disconnected briefly</li>
    <li>Use the Standard WiFi Setup for consistent configuration</li>
    <li>Suggest simple, memorable passwords</li>
    <li>Consider using the dedicated 2.4GHz network for IoT devices</li>
    <li>Enable guest network for households with frequent visitors</li>
</ul>
@endsection
