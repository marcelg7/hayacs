@extends('docs.layout')

@section('docs-content')
<h1>Admin Tasks Page</h1>

<p class="lead">
    The Admin Tasks page provides a system-wide view of all tasks across all devices. Use this to monitor task health, identify stuck tasks, and audit user activity.
</p>

<h2>Accessing the Tasks Page</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Navigate to <strong>Admin &rarr; Tasks</strong> in the main navigation.
    This page is only visible to users with the Admin role.
</p>

<h2>Statistics Cards</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    The top of the page shows 8 summary statistics:
</p>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-gray-900 dark:text-white">Total</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">All tasks in system</div>
    </div>
    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">Pending</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Waiting to execute</div>
    </div>
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">Sent</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Sent to device</div>
    </div>
    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-green-600 dark:text-green-400">Completed</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Successfully finished</div>
    </div>
    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-red-600 dark:text-red-400">Failed</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Errors occurred</div>
    </div>
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">Cancelled</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Manually cancelled</div>
    </div>
    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">User</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Initiated by users</div>
    </div>
    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">ACS</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">System-initiated</div>
    </div>
</div>

<h2>Filters</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Filter tasks by multiple criteria:
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Filter</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Options</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Status</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Pending, Sent, Completed, Failed, Cancelled</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Task Type</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">reboot, get_params, set_parameter_values, download, etc.</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Initiator</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All, User-initiated only, ACS-initiated only</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Serial Number</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Search by device serial number</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Date Range</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">From date / To date</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Task Table Columns</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Column</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">ID</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task ID (click to view details)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Serial number (click to view device)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Type</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task type (reboot, get_params, etc.)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Description</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Human-readable description</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Status</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Color-coded status badge</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Initiated By</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">User name or "ACS"</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Created</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When task was created</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Actions</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">View details, Cancel (if pending/sent)</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Task Initiator Types</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="flex items-center space-x-2 mb-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <h3 class="font-semibold text-gray-900 dark:text-white">User-Initiated</h3>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Tasks created by a logged-in user, such as clicking Reboot, changing WiFi settings,
            or running a speed test. Shows the user's name who initiated the action.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="flex items-center space-x-2 mb-2">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <h3 class="font-semibold text-gray-900 dark:text-white">ACS-Initiated</h3>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Tasks created automatically by the system, such as provisioning rules,
            workflows, factory reset restores, or scheduled backups. Shows "ACS" as the initiator.
        </p>
    </div>
</div>

<h2>Cancelling Tasks</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    You can cancel tasks that haven't completed yet:
</p>

<ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400 mb-6">
    <li><strong>Single task</strong>: Click the Cancel button in the Actions column</li>
    <li><strong>Bulk cancel</strong>: Select multiple tasks and use bulk actions</li>
    <li>Only <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">pending</code> and <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">sent</code> tasks can be cancelled</li>
</ul>

<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            @include('docs.partials.icon', ['icon' => 'warning'])
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Sent Tasks</h3>
            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                Cancelling a "sent" task marks it as cancelled in the database, but the device
                may still execute it if the command was already delivered.
            </p>
        </div>
    </div>
</div>

<h2>Common Use Cases</h2>

<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Finding Stuck Tasks</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Filter by Status: "Sent" and sort by Created Date (oldest first).
            Tasks in "sent" status for more than 2-3 minutes may indicate connectivity issues.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Auditing User Actions</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Filter by Initiator: "User-initiated" to see all tasks created by staff.
            Useful for troubleshooting or reviewing who performed certain actions.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Reviewing Firmware Upgrades</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Filter by Type: "download" to see all firmware upgrade tasks.
            Check for failed downloads or devices that haven't reconnected after upgrade.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Device-Specific History</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Enter a serial number to see all tasks for a specific device.
            Helpful when troubleshooting a particular customer's equipment.
        </p>
    </div>
</div>

<h2>Task Timeouts</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    The system automatically times out stuck tasks based on task type:
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Task Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Timeout</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">download (firmware)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">20 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">upload</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">10 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">reboot, factory_reset</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">5 minutes</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">set_parameter_values</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3 minutes (WiFi gets verification)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Default</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2 minutes</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
