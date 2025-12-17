@extends('docs.layout')

@section('docs-content')
<h1>Task Types</h1>

<p class="lead">
    Tasks are TR-069 RPC operations sent to devices. This reference describes each task type
    and its behavior.
</p>

<h2>Parameter Operations</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">get_params</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Retrieves parameter values from the device. Used for reading WiFi settings,
            WAN info, device status, etc.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">GetParameterValues</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">2 minutes</span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">set_parameter_values</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Sets one or more parameter values on the device. Used for WiFi configuration,
            enabling/disabling features, etc.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">SetParameterValues</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">3 minutes (WiFi tasks get verification)</span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">get_parameter_names</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Discovers all available parameters on the device. Used for "Get Everything"
            backups and parameter exploration.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">GetParameterNames</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">2 minutes</span>
        </div>
    </div>
</div>

<h2>Object Management</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">add_object</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Creates a new object instance, such as a port forwarding rule or WiFi SSID.
            Returns the instance number for subsequent configuration.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">AddObject</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">3 minutes</span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">delete_object</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Removes an object instance, such as deleting a port forwarding rule.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">DeleteObject</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">3 minutes</span>
        </div>
    </div>
</div>

<h2>Device Control</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">reboot</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Reboots the device. Device will disconnect and reconnect after reboot completes.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">Reboot</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">5 minutes</span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">factory_reset</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Resets device to factory defaults. Device will send BOOTSTRAP event on reconnection.
            Previous configuration is automatically restored from backup.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">FactoryReset</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">5 minutes</span>
        </div>
    </div>
</div>

<h2>File Transfer</h2>

<div class="space-y-4 mb-6">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">download</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Instructs device to download a file from a URL. Primarily used for firmware upgrades.
            Device reports completion via TransferComplete.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">Download</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">20 minutes</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">File Types:</span>
            <span class="text-gray-600 dark:text-gray-400">1 Firmware Upgrade Image, 2 Web Content, 3 Vendor Config</span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-mono text-lg font-semibold text-gray-900 dark:text-white mb-2">upload</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Instructs device to upload a file to a URL. Used for configuration backup
            and log retrieval.
        </p>
        <div class="text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">TR-069 RPC:</span>
            <span class="font-mono text-gray-600 dark:text-gray-400">Upload</span>
        </div>
        <div class="text-sm mt-1">
            <span class="font-medium text-gray-700 dark:text-gray-300">Timeout:</span>
            <span class="text-gray-600 dark:text-gray-400">10 minutes</span>
        </div>
    </div>
</div>

<h2>Task Status Lifecycle</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300">pending</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task created, waiting for device to connect</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">sent</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">RPC sent to device, awaiting response</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">verifying</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">WiFi task timed out, verification in progress</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">completed</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task completed successfully</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300">failed</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task failed (error, timeout, SOAP fault)</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300">cancelled</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Task cancelled by user or system</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Speed Test Task</h2>

<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
        Speed tests use the <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">download</code> task type
        with a test file. Speed is calculated from file size and transfer duration.
    </p>
    <div class="text-sm mt-2">
        <span class="font-medium text-gray-700 dark:text-gray-300">Test File:</span>
        <span class="text-gray-600 dark:text-gray-400">10MB from speedtest.tele2.net</span>
    </div>
    <div class="text-sm mt-1">
        <span class="font-medium text-gray-700 dark:text-gray-300">Calculation:</span>
        <span class="font-mono text-gray-600 dark:text-gray-400">(file_size_bytes * 8) / duration_seconds / 1_000_000 = Mbps</span>
    </div>
</div>
@endsection
