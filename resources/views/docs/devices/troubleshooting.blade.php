@extends('docs.layout')

@section('docs-content')
<h1>Troubleshooting Tools</h1>

<p class="lead">
    Hay ACS provides several diagnostic tools to help identify and resolve device issues.
</p>

<h2>Troubleshooting Tab</h2>

<p>
    The <strong>Troubleshooting</strong> tab on the device dashboard provides quick access to diagnostic information and actions.
</p>

<h3>Available Tools</h3>

<div class="not-prose space-y-4 my-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Ping Test</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Test connectivity to external hosts from the device.</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Traceroute</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Trace the network path from the device to a destination.</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">DNS Lookup</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Test DNS resolution from the device.</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Connection Status</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View WAN connection details and status.</p>
    </div>
</div>

<h2>Common Issues and Solutions</h2>

<h3>Device Shows Offline</h3>

<p><strong>Possible causes:</strong></p>

<ul>
    <li>Device is powered off or disconnected</li>
    <li>WAN connection is down</li>
    <li>NAT/firewall blocking TR-069 traffic</li>
    <li>Device periodic inform interval has passed</li>
</ul>

<p><strong>Troubleshooting steps:</strong></p>

<ol>
    <li>Check when the device was last seen (Last Seen timestamp)</li>
    <li>Verify the device has power and network connection</li>
    <li>If recently offline, wait for the next periodic inform (up to 10 minutes)</li>
    <li>Try sending a Connection Request (click "Connect Now" if available)</li>
</ol>

<h3>WiFi Changes Not Applying</h3>

<p><strong>Possible causes:</strong></p>

<ul>
    <li>Device is offline</li>
    <li>Task is still processing</li>
    <li>Device-specific processing time (Nokia: 2-3 minutes per radio)</li>
</ul>

<p><strong>Troubleshooting steps:</strong></p>

<ol>
    <li>Check the Task Manager in the bottom-right corner</li>
    <li>View the Tasks tab for task status and any error messages</li>
    <li>For Nokia devices, wait 3-5 minutes - they process WiFi changes slowly</li>
    <li>Click "Refresh" to get updated device parameters</li>
</ol>

<h3>Speed Test Shows Low Speed</h3>

<p><strong>Possible causes:</strong></p>

<ul>
    <li>Network congestion at time of test</li>
    <li>Backhaul or upstream network issues</li>
    <li>Device processing load</li>
    <li>Speed test server issues</li>
</ul>

<p><strong>Troubleshooting steps:</strong></p>

<ol>
    <li>Run the test multiple times at different times</li>
    <li>Check for high CPU/memory usage on the device</li>
    <li>Verify the customer's service tier in billing</li>
    <li>Compare with historical speed test results</li>
</ol>

<h3>Remote GUI Won't Load</h3>

<p><strong>Possible causes:</strong></p>

<ul>
    <li>Device is offline</li>
    <li>Remote access not enabled on device</li>
    <li>Firewall blocking the management port</li>
    <li>Browser blocking self-signed certificate</li>
</ul>

<p><strong>Troubleshooting steps:</strong></p>

<ol>
    <li>Verify the device is online</li>
    <li>Click "Close Remote Access" then "GUI" to reset the session</li>
    <li>Accept the browser's certificate warning if prompted</li>
    <li>Try a different browser</li>
</ol>

<h2>Using the Parameters Tab</h2>

<p>
    The <strong>Parameters</strong> tab provides access to all device configuration values. This is useful for advanced troubleshooting.
</p>

<h3>Searching Parameters</h3>

<ol>
    <li>Go to the device's Parameters tab</li>
    <li>Type in the search box (searches both names and values)</li>
    <li>Results appear as you type (300ms debounce)</li>
</ol>

<h3>Common Parameters to Check</h3>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Issue</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Parameters to Check</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">WAN Connection</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-sm">ExternalIPAddress, ConnectionStatus, DefaultGateway</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">WiFi Status</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-sm">SSID, Enable, Channel, RadioEnabled</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Firmware</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-sm">SoftwareVersion, ModelName</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Uptime</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-sm">UpTime, DeviceUpTime</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>Exporting Parameters</h3>

<p>
    Click <strong>Export CSV</strong> to download all device parameters. This is useful for:
</p>

<ul>
    <li>Comparing configurations between devices</li>
    <li>Documentation and record-keeping</li>
    <li>Sharing with vendor support</li>
</ul>

<h2>Checking Task History</h2>

<p>
    The <strong>Tasks</strong> tab shows all operations performed on the device.
</p>

<h3>Task Statuses</h3>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Meaning</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm">Pending</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Waiting to be sent to device</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 rounded text-sm">Sent</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Sent to device, waiting for response</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded text-sm">Verifying</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Verifying changes were applied (WiFi tasks)</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded text-sm">Completed</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Successfully completed</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-sm">Failed</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task failed (click for details)</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm">Cancelled</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task was cancelled before completion</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>Understanding Failed Tasks</h3>

<p>Click on a failed task to see the error message. Common errors include:</p>

<ul>
    <li><strong>Device not responding</strong> - Device went offline during task</li>
    <li><strong>Invalid parameter</strong> - Parameter name or value not supported</li>
    <li><strong>Timeout</strong> - Device took too long to respond</li>
    <li><strong>SOAP Fault</strong> - Device reported an error (check fault code)</li>
</ul>

<h2>Events Tab</h2>

<p>
    The <strong>Events</strong> tab shows device events such as:
</p>

<ul>
    <li>Device connections (BOOTSTRAP, PERIODIC)</li>
    <li>Value changes</li>
    <li>Connection requests</li>
    <li>Firmware updates</li>
</ul>

<p>
    Review events to understand device behavior and identify when issues started.
</p>

<h2>WiFi Scan Tab</h2>

<p>
    The <strong>WiFi Scan</strong> tab shows neighboring WiFi networks. Use this to:
</p>

<ul>
    <li>Identify channel congestion</li>
    <li>Detect interfering networks</li>
    <li>Recommend better channel placement</li>
    <li>Document the RF environment</li>
</ul>

<h2>Getting Help</h2>

<p>If you can't resolve an issue:</p>

<ol>
    <li>Document the problem with screenshots and task IDs</li>
    <li>Export device parameters for reference</li>
    <li>Note the device serial number and model</li>
    <li>Contact your system administrator</li>
</ol>
@endsection
