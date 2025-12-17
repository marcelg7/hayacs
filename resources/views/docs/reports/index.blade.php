@extends('docs.layout')

@section('docs-content')
<h1>Reports Overview</h1>

<p class="lead">
    Hay ACS provides various reports to help you monitor device health, identify issues, and track system activity.
</p>

<h2>Accessing Reports</h2>

<p>Click <strong>Reports</strong> in the main navigation to access the reports section.</p>

<h2>Available Reports</h2>

<div class="not-prose space-y-4 my-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Duplicate MAC Addresses</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Find devices reporting the same MAC address, which may indicate configuration issues or cloned devices.</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Device Status Summary</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overview of online/offline devices by manufacturer and model.</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Firmware Distribution</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">See which firmware versions are deployed across your device fleet.</p>
    </div>
</div>

<h2>Report Types</h2>

<h3>Operational Reports</h3>

<p>These reports help with day-to-day operations:</p>

<ul>
    <li><strong>Offline Devices</strong> - Devices that haven't communicated recently</li>
    <li><strong>Task Failures</strong> - Recent failed operations that may need attention</li>
    <li><strong>Unlinked Devices</strong> - Devices not associated with a subscriber</li>
</ul>

<h3>Inventory Reports</h3>

<p>These reports provide fleet-wide visibility:</p>

<ul>
    <li><strong>Device Counts</strong> - Total devices by manufacturer, model, status</li>
    <li><strong>Firmware Versions</strong> - Distribution of firmware across the fleet</li>
    <li><strong>Subscriber Coverage</strong> - Devices linked vs unlinked to subscribers</li>
</ul>

<h3>Diagnostic Reports</h3>

<p>These reports help identify potential issues:</p>

<ul>
    <li><strong>Duplicate MACs</strong> - Multiple devices with same MAC address</li>
    <li><strong>Configuration Anomalies</strong> - Devices with unusual settings</li>
</ul>

<h2>Using Reports</h2>

<h3>Filtering</h3>

<p>Most reports support filtering by:</p>

<ul>
    <li>Date range</li>
    <li>Manufacturer</li>
    <li>Model</li>
    <li>Status</li>
</ul>

<h3>Exporting</h3>

<p>Reports can be exported in various formats:</p>

<ul>
    <li><strong>CSV</strong> - For spreadsheet analysis</li>
    <li><strong>Print</strong> - For physical documentation</li>
</ul>

<h2>Report Documentation</h2>

<div class="not-prose space-y-4 my-6">
    <a href="{{ route('docs.show', ['section' => 'reports', 'page' => 'duplicate-macs']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Duplicate MAC Addresses</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Understanding and resolving duplicate MAC address issues.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'reports', 'page' => 'analytics']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Analytics Dashboard</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Real-time system analytics and performance metrics.</p>
    </a>
</div>
@endsection
