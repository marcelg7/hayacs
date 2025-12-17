@extends('docs.layout')

@section('docs-content')
<h1>Reboot & Factory Reset</h1>

<p class="lead">
    Hay ACS allows you to remotely reboot devices or perform factory resets when needed.
</p>

<h2>Reboot</h2>

<p>
    Rebooting a device restarts it without changing any settings. This is useful for resolving temporary issues.
</p>

<h3>When to Reboot</h3>

<ul>
    <li>Device is slow or unresponsive</li>
    <li>WiFi connectivity issues</li>
    <li>After configuration changes that require a restart</li>
    <li>To clear temporary memory issues</li>
</ul>

<h3>How to Reboot</h3>

<ol>
    <li>Navigate to the device dashboard</li>
    <li>Click the <strong>Reboot</strong> button</li>
    <li>Confirm when prompted</li>
</ol>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Service Interruption:</strong> Rebooting will disconnect all users from the network for 2-5 minutes. Warn the customer before rebooting.
        </p>
    </div>
</div>

<h3>Reboot Timeline</h3>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Device Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Typical Downtime</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaCenters</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-3 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaSpires</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-3 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Nokia Beacon 6</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3-5 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">SmartRG</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-4 minutes</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>What Happens During Reboot</h3>

<ol>
    <li>Hay ACS sends a Reboot command via TR-069</li>
    <li>Device acknowledges the command</li>
    <li>Device restarts (all connections dropped)</li>
    <li>Device boots up and reconnects to the network</li>
    <li>Device sends an Inform to Hay ACS (appears online again)</li>
</ol>

<h2>Factory Reset</h2>

<p>
    A factory reset erases all device settings and returns it to its original state. <strong>This is a destructive operation.</strong>
</p>

<div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div class="text-red-800 dark:text-red-200 text-sm">
            <p><strong>Warning: Data Loss!</strong></p>
            <p class="mt-1">Factory reset will erase:</p>
            <ul class="list-disc ml-5 mt-1">
                <li>WiFi names and passwords</li>
                <li>Port forwarding rules</li>
                <li>Custom DNS settings</li>
                <li>All other user configurations</li>
            </ul>
        </div>
    </div>
</div>

<h3>When to Factory Reset</h3>

<ul>
    <li>Device is being returned or repurposed</li>
    <li>Persistent issues that can't be resolved otherwise</li>
    <li>Device has unknown/forgotten admin password</li>
    <li>Preparing device for a new customer</li>
</ul>

<h3>How to Factory Reset</h3>

<ol>
    <li>Navigate to the device dashboard</li>
    <li>Click the <strong>Factory Reset</strong> button (red)</li>
    <li>Read the warning carefully</li>
    <li>Type "RESET" to confirm</li>
    <li>Click Confirm</li>
</ol>

<h3>Automatic Configuration Restore</h3>

<p>
    Hay ACS has an automatic restore feature that can recover device settings after a factory reset:
</p>

<ol>
    <li>When a device is first connected, Hay ACS creates a backup</li>
    <li>After factory reset, the device reconnects with default settings</li>
    <li>If a backup exists (older than 1 minute), Hay ACS automatically queues a restore</li>
    <li>Previous WiFi settings, port forwards, etc. are restored</li>
</ol>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Note:</strong> The automatic restore excludes ACS management server settings to prevent connectivity loops.
        </p>
    </div>
</div>

<h3>Parameters NOT Restored</h3>

<p>For safety, certain parameters are excluded from automatic restore:</p>

<ul>
    <li>ManagementServer URL and credentials (ACS connection settings)</li>
    <li>Connection request username/password</li>
</ul>

<h2>Before You Reset</h2>

<p>Consider these steps before performing a factory reset:</p>

<ol>
    <li><strong>Create a manual backup</strong> - Go to the Backups tab and create a named backup</li>
    <li><strong>Document current settings</strong> - Note WiFi passwords, port forwards, etc.</li>
    <li><strong>Notify the customer</strong> - Explain the service interruption and that settings will be restored</li>
    <li><strong>Verify backup exists</strong> - Check the Backups tab for recent backups</li>
</ol>

<h2>Factory Reset Timeline</h2>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Phase</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Reset</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">30 seconds</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device erases settings</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Reboot</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-5 minutes</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device restarts with defaults</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Reconnect</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">1-2 minutes</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device contacts Hay ACS</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Restore</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">1-5 minutes</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Backup settings applied</td>
            </tr>
        </tbody>
    </table>
</div>

<p><strong>Total time:</strong> Approximately 5-15 minutes for full reset and restore.</p>

<h2>Troubleshooting Reset Issues</h2>

<h3>Device doesn't come back online?</h3>

<ul>
    <li>Wait at least 10 minutes - some devices take longer</li>
    <li>Check if the device has power</li>
    <li>Verify the ONT/modem connection is active</li>
    <li>Device may need manual ACS URL configuration if it was previously on a different ACS</li>
</ul>

<h3>Settings not restored?</h3>

<ul>
    <li>Check the Tasks tab for restore task status</li>
    <li>Verify a backup existed before the reset</li>
    <li>SmartRG devices restore in chunks (may take multiple sessions)</li>
</ul>
@endsection
