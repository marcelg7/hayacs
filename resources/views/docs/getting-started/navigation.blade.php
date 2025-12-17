@extends('docs.layout')

@section('docs-content')
<h1>Navigating the Interface</h1>

<p class="lead">
    Learn your way around Hay ACS. This guide covers the main navigation elements and how to quickly find what you need.
</p>

<h2>Top Navigation Bar</h2>

<p>
    The navigation bar at the top of every page provides quick access to all major sections of Hay ACS.
</p>

<h3>Main Menu Items</h3>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Menu Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Access</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Dashboard</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">System overview with device statistics and status summary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All users</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Devices</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">List of all managed devices with search and filters</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All users</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Analytics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Charts and statistics about device performance</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All users</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Docs</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">This documentation</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All users</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Subscribers</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Customer/subscriber lookup and device associations</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All users</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Reports</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device reports and data exports</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Groups</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device grouping for bulk operations</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Workflows</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Automated device operations</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Firmware</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Firmware file management</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Users</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">User account management</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Tasks</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">View all device tasks across the system</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Admin only</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Global Search</h2>

<p>
    The search bar in the top navigation allows you to quickly find devices by:
</p>

<ul>
    <li><strong>Serial number</strong> - Full or partial match</li>
    <li><strong>MAC address</strong> - Device MAC address</li>
    <li><strong>IP address</strong> - Current WAN or LAN IP</li>
    <li><strong>Subscriber name</strong> - Search linked subscriber records</li>
    <li><strong>SSID</strong> - WiFi network name</li>
</ul>

<p>
    Press <kbd>Enter</kbd> or click the search icon to view results. Matching devices will be shown with key details.
</p>

<h2>User Menu</h2>

<p>
    Click your name in the top-right corner to access:
</p>

<ul>
    <li><strong>Profile</strong> - Update your account settings</li>
    <li><strong>Log Out</strong> - Sign out of Hay ACS</li>
</ul>

<h2>Theme Toggle</h2>

<p>
    The sun/moon icon next to your profile name toggles between light and dark modes. Your preference is saved automatically.
</p>

<h2>Mobile Navigation</h2>

<p>
    On mobile devices, the navigation bar collapses into a hamburger menu (three horizontal lines). Tap it to expand the full menu.
</p>

<h2>Keyboard Shortcuts</h2>

<p>Some pages support keyboard shortcuts:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shortcut</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">/</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Focus the search bar</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">Esc</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Close modals and dialogs</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Device Dashboard Navigation</h2>

<p>
    When viewing a specific device, you'll see a tabbed interface with multiple sections:
</p>

<ul>
    <li><strong>Dashboard</strong> - Device overview, connection status, WiFi info</li>
    <li><strong>Parameters</strong> - Full parameter search and browsing</li>
    <li><strong>Tasks</strong> - Task history and status</li>
    <li><strong>WiFi</strong> - WiFi configuration management</li>
    <li><strong>Backups</strong> - Configuration backup and restore</li>
    <li><strong>Events</strong> - Device event history</li>
    <li><strong>Ports</strong> - Port forwarding management</li>
    <li><strong>WiFi Scan</strong> - Interference detection</li>
    <li><strong>Speed Test</strong> - Speed testing tools</li>
    <li><strong>Troubleshooting</strong> - Diagnostic tools</li>
</ul>

<p>
    Click any tab to switch between sections. The current tab is highlighted.
</p>

<h2>Breadcrumbs</h2>

<p>
    Many pages show breadcrumbs at the top to help you understand where you are and navigate back. For example:
</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 my-4 text-sm">
    <span class="text-gray-500 dark:text-gray-400">Devices</span>
    <span class="text-gray-400 dark:text-gray-500 mx-2">/</span>
    <span class="text-gray-900 dark:text-white">CXNK0083217F</span>
    <span class="text-gray-400 dark:text-gray-500 mx-2">/</span>
    <span class="text-gray-900 dark:text-white font-medium">WiFi</span>
</div>

<p>Click any breadcrumb segment to navigate to that page.</p>

<h2>Common Tasks Quick Reference</h2>

<div class="not-prose space-y-4 my-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Find a device by serial number</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Type the serial number in the global search bar and press Enter</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Change WiFi password</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Device > WiFi tab > Enter new password > Click Apply</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Reboot a device</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Device > Dashboard tab > Click Reboot button</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Run a speed test</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Device > Speed Test tab > Click Start Download Test</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Access device GUI</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Device > Dashboard tab > Click GUI button</p>
    </div>
</div>

<h2>Next Steps</h2>

<p>
    Now that you know your way around, explore the <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'index']) }}">Device Management</a> documentation to learn how to work with devices.
</p>
@endsection
