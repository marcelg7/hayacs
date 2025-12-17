@extends('docs.layout')

@section('docs-content')
<h1>Analytics Dashboard</h1>

<p class="lead">
    The Analytics dashboard provides real-time insights into system performance, device status, and operational metrics.
</p>

<h2>Accessing Analytics</h2>

<p>Click <strong>Analytics</strong> in the main navigation to view the dashboard.</p>

<h2>Dashboard Sections</h2>

<h3>Device Statistics</h3>

<p>Overview of your device fleet:</p>

<ul>
    <li><strong>Total Devices</strong> - All devices registered in Hay ACS</li>
    <li><strong>Online Now</strong> - Devices currently communicating</li>
    <li><strong>Offline</strong> - Devices not communicating</li>
    <li><strong>Online Rate</strong> - Percentage of devices online</li>
</ul>

<h3>Device Distribution</h3>

<p>Breakdown of devices by:</p>

<ul>
    <li><strong>Manufacturer</strong> - Calix, Nokia, SmartRG, etc.</li>
    <li><strong>Model</strong> - Specific device models</li>
    <li><strong>Status</strong> - Online vs offline</li>
</ul>

<h3>Task Performance</h3>

<p>Metrics on TR-069 operations:</p>

<ul>
    <li><strong>Tasks Today</strong> - Operations performed today</li>
    <li><strong>Success Rate</strong> - Percentage of tasks completed successfully</li>
    <li><strong>Average Duration</strong> - How long tasks typically take</li>
    <li><strong>Failure Rate</strong> - Tasks that failed</li>
</ul>

<h3>Speed Test Results</h3>

<p>Aggregated speed test data:</p>

<ul>
    <li><strong>Tests Run</strong> - Total speed tests performed</li>
    <li><strong>Average Speed</strong> - Mean download speed across tests</li>
    <li><strong>Speed Distribution</strong> - Breakdown by speed tier</li>
</ul>

<h2>Time Range Selection</h2>

<p>Use the time range selector to view data for:</p>

<ul>
    <li>Today</li>
    <li>Last 7 Days</li>
    <li>Last 30 Days</li>
    <li>Custom Range</li>
</ul>

<h2>Charts and Visualizations</h2>

<h3>Device Status Over Time</h3>

<p>
    Line chart showing online/offline device counts over the selected time period. Useful for identifying outages or trends.
</p>

<h3>Task Volume Chart</h3>

<p>
    Bar chart showing task volume by day. Helps identify busy periods and capacity planning.
</p>

<h3>Manufacturer Distribution</h3>

<p>
    Pie chart showing the breakdown of devices by manufacturer in your fleet.
</p>

<h2>Key Metrics</h2>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Metric</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Good</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Warning</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action Needed</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Online Rate</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">&gt; 90%</td>
                <td class="px-4 py-3 text-yellow-600 dark:text-yellow-400">80-90%</td>
                <td class="px-4 py-3 text-red-600 dark:text-red-400">&lt; 80%</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Task Success Rate</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">&gt; 95%</td>
                <td class="px-4 py-3 text-yellow-600 dark:text-yellow-400">85-95%</td>
                <td class="px-4 py-3 text-red-600 dark:text-red-400">&lt; 85%</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Average Task Time</td>
                <td class="px-4 py-3 text-green-600 dark:text-green-400">&lt; 30s</td>
                <td class="px-4 py-3 text-yellow-600 dark:text-yellow-400">30-60s</td>
                <td class="px-4 py-3 text-red-600 dark:text-red-400">&gt; 60s</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Using Analytics for Troubleshooting</h2>

<h3>Sudden Drop in Online Devices</h3>

<p>If you see a sudden drop in online devices:</p>

<ol>
    <li>Check for network outages in specific areas</li>
    <li>Review if a firmware update was pushed</li>
    <li>Check the Events log for unusual activity</li>
    <li>Verify the ACS server is functioning properly</li>
</ol>

<h3>High Task Failure Rate</h3>

<p>If task success rate drops:</p>

<ol>
    <li>Check which task types are failing</li>
    <li>Review error messages in the Tasks admin view</li>
    <li>Verify device connectivity</li>
    <li>Check for firmware issues on specific models</li>
</ol>

<h3>Low Speed Test Results</h3>

<p>If average speeds are below expected:</p>

<ol>
    <li>Review individual test results for outliers</li>
    <li>Check if specific models or areas are affected</li>
    <li>Verify network capacity and backhaul</li>
    <li>Run tests at different times of day</li>
</ol>

<h2>Exporting Data</h2>

<p>
    Analytics data can be exported for further analysis. Click the export button on any chart or table to download in CSV format.
</p>

<h2>Refresh Rate</h2>

<p>
    The analytics dashboard updates automatically every 5 minutes. Click the refresh button to update immediately.
</p>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Tip:</strong> The Analytics dashboard is optimized for performance, with queries averaging under 100ms even with thousands of devices.
        </p>
    </div>
</div>
@endsection
