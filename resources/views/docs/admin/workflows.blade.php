@extends('docs.layout')

@section('docs-content')
<h1>Workflows</h1>

<p class="lead">
    Workflows automate bulk operations on device groups. When a workflow is triggered, it creates tasks for each device in the associated group.
</p>

<h2>Workflow Components</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Component</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device Group</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Which devices the workflow applies to</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Task Type</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">What operation to perform</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Schedule</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When to run (immediate, on connect, scheduled)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Parameters</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task-specific configuration</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Task Types</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Parameters</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">firmware_upgrade</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Download and install firmware</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Firmware file, device type</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">get_parameter_names</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Discover all device parameters (backup)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">None</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">set_parameter_values</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Set one or more parameter values</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Parameter name/value pairs</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">reboot</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Reboot devices</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">None</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">factory_reset</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Reset to factory defaults</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">None</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Schedule Options</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Run Immediately</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Tasks are created for all group members as soon as the workflow is saved.
            Connection requests are sent to trigger immediate execution.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">On Device Connect</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Tasks are created when devices join the group or when they connect via TR-069 Inform.
            Good for new device provisioning or firmware upgrades that should happen automatically.
        </p>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Scheduled</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Tasks are created at a specific date/time. Useful for maintenance windows
            or coordinated updates.
        </p>
    </div>
</div>

<h2>Execution Flow</h2>

<ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-6">
    <li>Workflow creates <strong>WorkflowExecution</strong> record for each device</li>
    <li>Task is created with status <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">pending</code></li>
    <li>Connection request sent to device (if online)</li>
    <li>Device connects and receives task</li>
    <li>Device executes task and reports result</li>
    <li>Task status updated to <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">completed</code> or <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">failed</code></li>
    <li>WorkflowExecution marked complete</li>
</ol>

<h2>Concurrency Control</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Workflows support concurrency limits to prevent overloading the system:
</p>

<ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400 mb-6">
    <li><strong>Max Concurrent</strong>: Maximum tasks running simultaneously</li>
    <li><strong>Rate Limit</strong>: Tasks per minute across the group</li>
    <li>Queued devices wait for running tasks to complete</li>
</ul>

<h2>Firmware Upgrade Workflows</h2>

<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            @include('docs.partials.icon', ['icon' => 'warning'])
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Intermediate Firmware</h3>
            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                Some devices require intermediate firmware versions. The system automatically detects
                when an intermediate upgrade is needed (e.g., Nokia IJKJ16 &rarr; IJLJ03 &rarr; IJMK14).
            </p>
        </div>
    </div>
</div>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Firmware upgrade task descriptions show version numbers:
</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded p-3 font-mono text-sm mb-6">
    Firmware upgrade: IJKJ16 &rarr; IJLJ03
</div>

<h2>Monitoring Workflow Progress</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    The workflow detail page shows:
</p>

<ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400 mb-6">
    <li>Overall progress (completed / total devices)</li>
    <li>Status breakdown (pending, queued, running, completed, failed)</li>
    <li>Individual device execution status</li>
    <li>Error messages for failed executions</li>
</ul>

<h2>Dependencies</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Workflows can depend on other workflows. A dependent workflow only runs after its
    prerequisite completes for that device.
</p>

<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Example: Backup Before Firmware</h3>
    <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
        <li>Workflow 1: "Initial Backup" (get_parameter_names)</li>
        <li>Workflow 2: "Firmware Upgrade" (depends on Workflow 1)</li>
        <li>Device only gets firmware after backup completes</li>
    </ol>
</div>
@endsection
