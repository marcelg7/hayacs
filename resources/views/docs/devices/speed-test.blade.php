@extends('docs.layout')

@section('docs-content')
<h1>Speed Testing</h1>

<p class="lead">
    Hay ACS can run speed tests directly from the customer's device to verify service performance.
</p>

<h2>How Speed Tests Work</h2>

<p>
    Unlike browser-based speed tests (like speedtest.net), Hay ACS speed tests run directly on the CPE device. This measures the actual connection capability without being affected by:
</p>

<ul>
    <li>Customer's computer performance</li>
    <li>WiFi connection quality</li>
    <li>Browser limitations</li>
</ul>

<p>
    The device downloads a test file from a speed test server and reports the results.
</p>

<h2>Running a Speed Test</h2>

<ol>
    <li>Navigate to the device dashboard</li>
    <li>Click the <strong>Speed Test</strong> tab</li>
    <li>Click <strong>Start Download Test</strong></li>
</ol>

<p>
    The test typically takes 10-30 seconds. Results will appear on the page when complete.
</p>

<h2>Understanding Results</h2>

<h3>Download Speed</h3>

<p>
    The download speed is calculated from the file transfer and displayed in <strong>Mbps</strong> (megabits per second).
</p>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Note:</strong> Results measure the connection to the speed test server, which may differ slightly from speeds to other internet destinations.
        </p>
    </div>
</div>

<h3>Upload Speed</h3>

<p>
    Upload speed testing is not available on all devices. Calix devices do not support the upload test via TR-069.
</p>

<h2>Speed Test History</h2>

<p>
    Previous speed test results are stored and displayed on the Speed Test tab, showing:
</p>

<ul>
    <li>Date and time of test</li>
    <li>Download speed achieved</li>
    <li>Test duration</li>
</ul>

<h2>Device Support</h2>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Device Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Download Test</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Upload Test</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaCenters</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">Supported</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-500">Not supported</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaSpires</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">Supported</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-500">Not supported</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Nokia Beacon 6</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">Supported</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-500">Varies</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">SmartRG</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">Supported</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-500">Varies</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>When to Use Speed Tests</h2>

<p>Speed tests are useful for:</p>

<ul>
    <li><strong>Service verification</strong> - Confirm the customer is getting their subscribed speed</li>
    <li><strong>Troubleshooting slow service</strong> - Identify if the issue is the connection or the customer's equipment</li>
    <li><strong>Before/after comparisons</strong> - Test before and after changes to verify improvements</li>
    <li><strong>Customer education</strong> - Show customers their actual service speed</li>
</ul>

<h2>Interpreting Results</h2>

<h3>Speed is as expected</h3>

<p>If the speed matches the customer's service tier, the broadband connection is working correctly. If they're experiencing slowness, investigate:</p>

<ul>
    <li>WiFi interference</li>
    <li>Customer's device limitations</li>
    <li>Distance from router</li>
    <li>Number of concurrent users</li>
</ul>

<h3>Speed is lower than expected</h3>

<p>If the speed is significantly below the service tier:</p>

<ul>
    <li>Check for network issues (high latency, packet loss)</li>
    <li>Verify the service provisioning</li>
    <li>Check for device firmware issues</li>
    <li>Run the test multiple times to confirm</li>
</ul>

<h2>Tips for Accurate Results</h2>

<ul>
    <li>Run tests during low-usage periods when possible</li>
    <li>Run multiple tests and average the results</li>
    <li>Remember that other devices on the network may affect results</li>
    <li>DSL connections may show lower speeds during peak hours</li>
</ul>
@endsection
