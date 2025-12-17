@extends('docs.layout')

@section('docs-content')
<h1>Duplicate MAC Addresses Report</h1>

<p class="lead">
    This report identifies devices that share the same MAC address, which may indicate configuration issues, cloned devices, or data quality problems.
</p>

<h2>What is a Duplicate MAC?</h2>

<p>
    A MAC address (Media Access Control address) should be unique to each network interface. When multiple devices report the same MAC address, it can cause:
</p>

<ul>
    <li>Network connectivity issues</li>
    <li>DHCP conflicts</li>
    <li>Routing problems</li>
    <li>Security concerns</li>
</ul>

<h2>Accessing the Report</h2>

<ol>
    <li>Click <strong>Reports</strong> in the navigation</li>
    <li>Select <strong>Duplicate MAC Addresses</strong></li>
</ol>

<h2>Understanding the Report</h2>

<p>The report shows:</p>

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
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">MAC Address</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">The duplicate MAC address found</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Manufacturer (OUI)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Hardware manufacturer based on MAC prefix</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device Count</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Number of devices reporting this MAC</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Devices</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">List of device serial numbers with this MAC</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Common Causes</h2>

<h3>1. Connected Device Appears on Multiple Routers</h3>

<p>
    The most common cause: a customer's phone, laptop, or other device has connected to multiple routers over time. Each router reports the device's MAC address in its connected devices list.
</p>

<p><strong>This is usually NOT a problem.</strong> It simply means the device has been seen on different networks.</p>

<h3>2. Mesh Network Satellites</h3>

<p>
    Mesh access points (like 804Mesh or Beacon 2) may report the same connected devices as their parent router. This is expected behavior.
</p>

<h3>3. Device Replacement</h3>

<p>
    When a router is replaced, connected devices may appear on both the old and new router until the old device data expires.
</p>

<h3>4. Configuration Cloning</h3>

<p>
    If a device configuration was cloned (e.g., during provisioning), MAC addresses may have been inadvertently copied. This is rare but can happen with certain provisioning tools.
</p>

<h3>5. Data Quality Issues</h3>

<p>
    Sometimes duplicate MACs result from data import issues or synchronization problems.
</p>

<h2>When to Investigate</h2>

<p>Most duplicate MACs are benign. Investigate when:</p>

<ul>
    <li>The duplicate MAC is a <strong>WAN MAC</strong> (the router's own address)</li>
    <li>Customers report connectivity issues</li>
    <li>The same MAC appears on geographically distant devices</li>
    <li>The device count is unusually high (10+ devices)</li>
</ul>

<h2>Resolution Steps</h2>

<h3>For WAN MAC Duplicates</h3>

<ol>
    <li>Click on each device serial number to view details</li>
    <li>Verify the physical devices are different units</li>
    <li>If truly duplicate, contact manufacturer - may be factory defect</li>
    <li>Consider replacing one of the devices</li>
</ol>

<h3>For Connected Device Duplicates</h3>

<ol>
    <li>Usually no action needed</li>
    <li>If causing issues, have customer "forget" old networks on their device</li>
    <li>Refresh device data to get current connected devices</li>
</ol>

<h3>For Configuration Issues</h3>

<ol>
    <li>Factory reset the affected device</li>
    <li>Re-provision with fresh configuration</li>
    <li>Verify MAC address is now unique</li>
</ol>

<h2>Filtering the Report</h2>

<p>Use the filters to narrow results:</p>

<ul>
    <li><strong>MAC Type</strong> - Filter by WAN, LAN, or WiFi MAC addresses</li>
    <li><strong>Manufacturer</strong> - Show only specific device manufacturers</li>
    <li><strong>Minimum Count</strong> - Show only MACs appearing on X+ devices</li>
</ul>

<h2>Best Practices</h2>

<ul>
    <li>Review this report monthly for potential issues</li>
    <li>Focus on WAN MAC duplicates as they're more likely to cause problems</li>
    <li>Document known-good duplicates (like mesh networks) to avoid repeated investigation</li>
    <li>Use "Refresh" on devices to get current MAC data</li>
</ul>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Tip:</strong> MAC addresses starting with certain prefixes are "locally administered" and more likely to be duplicates. These include addresses where the second character is 2, 6, A, or E (e.g., x2:xx:xx:xx:xx:xx).
        </p>
    </div>
</div>
@endsection
