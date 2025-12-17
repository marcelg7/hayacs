@extends('docs.layout')

@section('docs-content')
<h1>Device Management Overview</h1>

<p class="lead">
    Hay ACS provides comprehensive tools for managing customer premises equipment (CPE). This section covers all device-related features.
</p>

<h2>What You Can Do</h2>

<p>With Hay ACS, you can:</p>

<div class="not-prose grid grid-cols-1 sm:grid-cols-2 gap-3 my-6">
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Monitor devices</strong> - Online status, connection details</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Configure WiFi</strong> - SSIDs, passwords, settings</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Run speed tests</strong> - Verify service performance</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Access device GUI</strong> - Remote web interface</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Reboot devices</strong> - Restart when needed</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>View parameters</strong> - Search all configuration</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Port forwarding</strong> - Configure NAT rules</span>
    </div>
    <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-gray-700 dark:text-gray-300"><strong>Backup/restore</strong> - Save and restore configs</span>
    </div>
</div>

<h2>Supported Devices</h2>

<p>Hay ACS supports devices from multiple manufacturers:</p>

<div class="not-prose grid grid-cols-1 md:grid-cols-2 gap-4 my-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Calix</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">GigaCenters (844E, 844G, 854G, 812G), GigaSpires (GS4220E), Mesh (804Mesh, GigaMesh)</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Nokia / Alcatel-Lucent</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Beacon 6, Beacon 2, Beacon 3.1</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">SmartRG / Sagemcom</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">SR505N, SR515ac, SR516ac</p>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 dark:text-white">Other</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Various TR-069 compliant devices</p>
    </div>
</div>

<h2>Device Types</h2>

<p>Devices fall into two main categories:</p>

<h3>Root Devices (Gateways/Routers)</h3>

<p>These are the main customer devices that connect to the WAN and route traffic. Examples:</p>

<ul>
    <li>Calix 844E-1, 844G-1, 854G-1</li>
    <li>Calix GS4220E (GigaSpire u6)</li>
    <li>Nokia Beacon 6</li>
    <li>SmartRG SR516ac</li>
</ul>

<h3>Satellite Devices (Mesh APs)</h3>

<p>These are mesh access points that extend WiFi coverage. They connect to a root device:</p>

<ul>
    <li>Calix 804Mesh, GigaMesh u4m</li>
    <li>Nokia Beacon 2, Beacon 3.1</li>
</ul>

<div class="info-box not-prose">
    <p class="text-gray-800 dark:text-gray-200 font-medium mb-1">About Satellite Devices</p>
    <p class="text-gray-600 dark:text-gray-400 text-sm">
        Satellite devices (mesh APs) have fewer configurable parameters than root devices. This is normal - WiFi configuration is typically managed from the root device.
    </p>
</div>

<h2>Documentation Sections</h2>

<div class="not-prose space-y-4 my-6">
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'device-list']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Device List & Search</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Finding devices, using filters, and understanding the device list.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'device-dashboard']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Device Dashboard</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Understanding the device overview and available actions.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'wifi-management']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">WiFi Configuration</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Changing WiFi names, passwords, and network settings.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'speed-test']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Speed Testing</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Running speed tests to verify service performance.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'remote-gui']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Remote GUI Access</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Accessing the device's web interface remotely.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'reboot-reset']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Reboot & Factory Reset</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Restarting devices and performing factory resets.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'troubleshooting']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Troubleshooting Tools</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Diagnostic features for resolving device issues.</p>
    </a>
</div>

<h2>Task System</h2>

<p>
    Most device operations in Hay ACS are performed through the task system. When you request an action (like rebooting a device), a task is created and sent to the device via TR-069.
</p>

<p>Tasks have the following statuses:</p>

<div class="not-prose flex flex-wrap gap-3 my-4">
    <div class="flex items-center">
        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pending</span>
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Waiting to send</span>
    </div>
    <div class="flex items-center">
        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Sent</span>
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Awaiting response</span>
    </div>
    <div class="flex items-center">
        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Success</span>
    </div>
    <div class="flex items-center">
        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Error occurred</span>
    </div>
    <div class="flex items-center">
        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Cancelled</span>
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Stopped</span>
    </div>
</div>

<p>
    You can view task status in real-time through the Task Manager component that appears when tasks are running.
</p>
@endsection
