@extends('docs.layout')

@section('docs-content')
<h1>Device List & Search</h1>

<p class="lead">
    Find and manage devices using the device list, filters, and search functionality.
</p>

<h2>Accessing the Device List</h2>

<p>Click <strong>Devices</strong> in the main navigation to view all devices in the system.</p>

<h2>Quick Search</h2>

<p>
    The fastest way to find a device is using the <strong>global search bar</strong> in the navigation header. You can search by:
</p>

<ul>
    <li><strong>Serial Number</strong> - Full or partial (e.g., "CXNK0083" or "80AB4D30B35C")</li>
    <li><strong>WAN IP Address</strong> - Current public IP</li>
    <li><strong>Subscriber Name</strong> - Customer name if device is linked</li>
    <li><strong>SSID</strong> - WiFi network name</li>
</ul>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Tip:</strong> Press <kbd class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs">/</kbd> to focus the search bar from anywhere in the application.
        </p>
    </div>
</div>

<h2>Device List Columns</h2>

<p>The device list displays the following information:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Column</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Status</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Green dot = online, Red dot = offline</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Serial Number</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Unique device identifier (click to view details)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Model</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device model (844E-1, GS4220E, Beacon 6, etc.)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Manufacturer</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Calix, Nokia, SmartRG, etc.</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">WAN IP</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Current public IP address</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Subscriber</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Linked customer name (if available)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Last Seen</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When the device last communicated with Hay ACS</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Filtering Devices</h2>

<p>Use the filter options above the device list to narrow results:</p>

<h3>Status Filter</h3>

<ul>
    <li><strong>All</strong> - Show all devices</li>
    <li><strong>Online</strong> - Only devices currently communicating</li>
    <li><strong>Offline</strong> - Devices that haven't communicated recently</li>
</ul>

<h3>Manufacturer Filter</h3>

<p>Filter by device manufacturer:</p>

<ul>
    <li>Calix</li>
    <li>Nokia / Alcatel-Lucent</li>
    <li>SmartRG / Sagemcom</li>
</ul>

<h3>Model Filter</h3>

<p>Filter by specific device model (e.g., 844E-1, GS4220E, Beacon 6).</p>

<h3>Subscriber Filter</h3>

<ul>
    <li><strong>All</strong> - Show all devices</li>
    <li><strong>Linked</strong> - Only devices linked to a subscriber</li>
    <li><strong>Unlinked</strong> - Devices not yet linked to a subscriber</li>
</ul>

<h2>Sorting</h2>

<p>Click any column header to sort by that column. Click again to reverse the sort order.</p>

<p>Default sort: <strong>Last Seen</strong> (most recent first)</p>

<h2>Pagination</h2>

<p>
    The device list shows 25 devices per page by default. Use the pagination controls at the bottom to navigate through pages.
</p>

<h2>Bulk Actions</h2>

<p>Select multiple devices using the checkboxes to perform bulk operations:</p>

<ul>
    <li><strong>Refresh Selected</strong> - Request latest data from multiple devices</li>
    <li><strong>Export Selected</strong> - Download device information as CSV</li>
</ul>

<h2>Device Types</h2>

<h3>Root Devices (Gateways)</h3>

<p>Main customer devices that connect to the WAN:</p>

<ul>
    <li>Calix 844E, 844G, 854G, 812G</li>
    <li>Calix GS4220E (GigaSpire)</li>
    <li>Nokia Beacon 6</li>
    <li>SmartRG SR516ac</li>
</ul>

<h3>Satellite Devices (Mesh APs)</h3>

<p>Mesh access points that extend WiFi coverage:</p>

<ul>
    <li>Calix 804Mesh, GigaMesh</li>
    <li>Nokia Beacon 2, Beacon 3.1</li>
</ul>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Note:</strong> Satellite devices show limited information because they're managed through their parent router.
        </p>
    </div>
</div>

<h2>Understanding Online/Offline Status</h2>

<p>Device status is determined by TR-069 communication with Hay ACS:</p>

<ul>
    <li><strong>Online</strong> - Device has communicated within its expected interval (typically 10 minutes)</li>
    <li><strong>Offline</strong> - Device hasn't communicated recently</li>
</ul>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Important:</strong> An "offline" device may still be providing internet service to the customer. The status only indicates TR-069 communication with Hay ACS, not the device's actual internet connectivity.
        </p>
    </div>
</div>
@endsection
