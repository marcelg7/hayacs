@extends('docs.layout')

@section('docs-content')
<h1>Device Dashboard</h1>

<p class="lead">
    The device dashboard provides a comprehensive overview of a single device, including its status, connection information, and quick actions.
</p>

<h2>Accessing the Dashboard</h2>

<p>To view a device dashboard:</p>

<ol>
    <li>Search for the device by serial number, or</li>
    <li>Navigate to <strong>Devices</strong> and click on a device in the list</li>
</ol>

<h2>Dashboard Sections</h2>

<h3>Device Information</h3>

<p>The top of the dashboard shows key device details:</p>

<ul>
    <li><strong>Serial Number</strong> - Unique device identifier</li>
    <li><strong>Model</strong> - Device model (e.g., 844E-1, GS4220E)</li>
    <li><strong>Manufacturer</strong> - Device manufacturer</li>
    <li><strong>Firmware Version</strong> - Currently installed firmware</li>
    <li><strong>Online Status</strong> - Green (online) or red (offline)</li>
    <li><strong>Last Seen</strong> - When the device last communicated with Hay ACS</li>
</ul>

<h3>Subscriber Information</h3>

<p>If the device is linked to a subscriber, you'll see:</p>

<ul>
    <li>Customer name and account number</li>
    <li>Service type</li>
    <li>Link to view subscriber details</li>
</ul>

<h3>Connection Details</h3>

<p>Network information includes:</p>

<ul>
    <li><strong>WAN IP</strong> - Public IP address</li>
    <li><strong>Default Gateway</strong> - Upstream gateway</li>
    <li><strong>DNS Servers</strong> - Configured DNS</li>
    <li><strong>LAN IP</strong> - Local network address</li>
</ul>

<h3>WiFi Summary</h3>

<p>Quick view of WiFi configuration:</p>

<ul>
    <li>Primary SSID (2.4GHz and 5GHz)</li>
    <li>Enabled/disabled status</li>
    <li>Link to full WiFi configuration</li>
</ul>

<h3>Connected Devices</h3>

<p>List of devices currently connected to the network, showing:</p>

<ul>
    <li>Device name (if available)</li>
    <li>MAC address</li>
    <li>IP address</li>
    <li>Connection type (2.4GHz, 5GHz, Ethernet)</li>
</ul>

<h2>Quick Actions</h2>

<p>The dashboard provides buttons for common actions:</p>

<div class="not-prose space-y-3 my-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white">Refresh</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">Request latest data from the device</p>
            </div>
            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-sm">Safe</span>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white">GUI</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">Open the device's web interface</p>
            </div>
            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-sm">Safe</span>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white">Reboot</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">Restart the device (brief service interruption)</p>
            </div>
            <span class="px-3 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-sm">Caution</span>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white">Factory Reset</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">Reset to factory defaults (data loss!)</p>
            </div>
            <span class="px-3 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded text-sm">Danger</span>
        </div>
    </div>
</div>

<h2>Dashboard Tabs</h2>

<p>The device page has multiple tabs:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tab</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Dashboard</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Overview, status, quick actions</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Parameters</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Search and browse all device parameters</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Tasks</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">View task history and status</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">WiFi</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">WiFi configuration</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Backups</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Configuration backup and restore</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Events</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device event history</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Ports</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Port forwarding rules</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">WiFi Scan</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Neighboring network detection</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Speed Test</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Speed testing</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Troubleshooting</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Diagnostic tools</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Online/Offline Status</h2>

<p>Device status is determined by communication with Hay ACS:</p>

<ul>
    <li><strong>Online</strong> - Device has communicated within the expected interval</li>
    <li><strong>Offline</strong> - Device hasn't communicated recently</li>
</ul>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Note:</strong> A device shown as "offline" may still be providing internet service to the customer. The status only indicates whether the device has communicated with Hay ACS.
        </p>
    </div>
</div>

<h2>Mesh/Satellite Devices</h2>

<p>
    Mesh access points (like 804Mesh, Beacon 2) show limited information because they are managed through their parent router. For these devices:
</p>

<ul>
    <li>WiFi configuration is managed from the parent device</li>
    <li>Connected clients may appear on the parent device</li>
    <li>Fewer parameters are available (this is normal)</li>
</ul>
@endsection
