@extends('docs.layout')

@section('docs-content')
<h1>Firmware Management</h1>

<p class="lead">
    Upload, organize, and deploy firmware to devices. Each device type has its own firmware library, and one firmware version is marked as "active" for automatic deployments.
</p>

<h2>Firmware Library</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Navigate to <strong>Admin &rarr; Device Types</strong>, then click on a device type to manage its firmware.
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Version</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Human-readable version (e.g., "v25.03")</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">File Name</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Internal filename, often contains version code (e.g., IJMK14)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">File Size</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Size in bytes (sent to device in Download RPC)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Download URL</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Optional external URL; otherwise served from local storage</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Is Active</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Mark as default for automatic workflows</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Uploading Firmware</h2>

<ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-6">
    <li>Navigate to the device type's firmware section</li>
    <li>Click <strong>Upload Firmware</strong></li>
    <li>Select the firmware file (.bin, .img, etc.)</li>
    <li>Enter version number and release notes</li>
    <li>Click <strong>Upload</strong></li>
    <li>File is stored in <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">storage/app/firmware/</code></li>
</ol>

<h2>Firmware Version Codes</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Nokia Beacon G6 firmware uses specific version codes in the filename:
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Version Code</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Release</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">IJMK14</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">v25.03 (March 2025)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Current target</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">IJLJ03</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">v24.02 (Feb 2024)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Intermediate for IJKJ16</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">IJKJ16</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">v23.xx (Older)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Requires intermediate upgrade</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Intermediate Firmware Upgrades</h2>

<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            @include('docs.partials.icon', ['icon' => 'warning'])
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Upgrade Path Requirements</h3>
            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                Some devices cannot upgrade directly to the latest firmware. The ACS automatically detects
                when an intermediate version is required.
            </p>
        </div>
    </div>
</div>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    <strong>Example: Nokia Beacon G6</strong>
</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded p-4 mb-6">
    <div class="flex items-center space-x-2 text-sm">
        <span class="font-mono bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300 px-2 py-1 rounded">IJKJ16</span>
        <span class="text-gray-500">&rarr;</span>
        <span class="font-mono bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300 px-2 py-1 rounded">IJLJ03</span>
        <span class="text-gray-500">&rarr;</span>
        <span class="font-mono bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300 px-2 py-1 rounded">IJMK14</span>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
        Devices on IJKJ16 must first upgrade to IJLJ03 before reaching the target IJMK14.
    </p>
</div>

<h2>Deploying Firmware</h2>

<h3 class="text-lg font-medium text-gray-900 dark:text-white mt-6 mb-3">Single Device</h3>

<ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-4">
    <li>Open the device dashboard</li>
    <li>Go to the <strong>Dashboard</strong> tab</li>
    <li>Click <strong>Upgrade Firmware</strong> in Quick Actions</li>
    <li>Select firmware version</li>
    <li>Confirm and deploy</li>
</ol>

<h3 class="text-lg font-medium text-gray-900 dark:text-white mt-6 mb-3">Bulk Deployment (Workflow)</h3>

<ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-4">
    <li>Create a Device Group matching target devices</li>
    <li>Create a Workflow with type <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">firmware_upgrade</code></li>
    <li>Select the firmware to deploy</li>
    <li>Choose schedule (immediate, on connect, or scheduled)</li>
    <li>Save workflow</li>
</ol>

<h2>TR-069 Download RPC</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Firmware is delivered using the TR-069 Download RPC:
</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded p-3 font-mono text-sm overflow-x-auto mb-6">
<pre>&lt;Download&gt;
  &lt;FileType&gt;1 Firmware Upgrade Image&lt;/FileType&gt;
  &lt;URL&gt;http://hayacs.hay.net/storage/firmware/...&lt;/URL&gt;
  &lt;FileSize&gt;48500632&lt;/FileSize&gt;
  &lt;TargetFileName&gt;FE49996IJMK14-beacon-g6&lt;/TargetFileName&gt;
&lt;/Download&gt;</pre>
</div>

<h2>Upgrade Timeline</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Device</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Download</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Install + Reboot</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Nokia Beacon G6</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-5 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3-5 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">5-10 min</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix 844E</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">1-2 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-3 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3-5 min</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaSpire</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">1-2 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">2-3 min</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3-5 min</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Monitoring Upgrades</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Track firmware upgrade progress:
</p>

<ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400 mb-6">
    <li><strong>Device Tasks tab</strong>: Shows download task status</li>
    <li><strong>TransferComplete</strong>: Sent by device when download finishes</li>
    <li><strong>Boot event</strong>: Device reconnects after reboot with new firmware</li>
    <li><strong>Software Version</strong>: Updated in device dashboard</li>
</ul>

<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-6">
    <div class="flex">
        <div class="flex-shrink-0">
            @include('docs.partials.icon', ['icon' => 'info'])
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Task Timeout</h3>
            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                Firmware download tasks have a 20-minute timeout to accommodate large files and
                slow connections. If a task times out, check the device's connection status.
            </p>
        </div>
    </div>
</div>
@endsection
