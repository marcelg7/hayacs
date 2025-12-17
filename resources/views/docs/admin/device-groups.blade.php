@extends('docs.layout')

@section('docs-content')
<h1>Device Groups</h1>

<p class="lead">
    Device Groups allow you to organize devices for bulk operations and automated workflows. Groups can be static (manually assigned) or dynamic (rule-based membership).
</p>

<h2>Group Types</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Use Case</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Static</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Devices manually added to group</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Test groups, special handling devices</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Dynamic</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Membership determined by rules</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All devices of a model, firmware version</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Dynamic Group Rules</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Dynamic groups use rules to automatically include devices based on their attributes:
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rule Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Example</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">manufacturer</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by device manufacturer</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">ALCL, Calix, Sagemcom</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">product_class</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by product class</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Beacon G6, 844E-1</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">software_version</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by firmware version</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3FE49996IJKJ16</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">oui</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by OUI (first 6 hex chars of MAC)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">80AB4D (Nokia TR-098)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">data_model</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by TR-069 data model</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098, TR-181</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">device_type_id</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Match by device type</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">All Beacon G6 devices</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Creating a Device Group</h2>

<ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-6">
    <li>Navigate to <strong>Admin &rarr; Device Groups</strong></li>
    <li>Click <strong>Create Group</strong></li>
    <li>Enter a descriptive name (e.g., "Nokia Beacon G6 - All")</li>
    <li>Choose group type: Static or Dynamic</li>
    <li>For dynamic groups, add one or more rules</li>
    <li>Click <strong>Save</strong></li>
</ol>

<h2>Rule Operators</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">=</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Exact match</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">!=</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Not equal</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">LIKE</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Pattern match (use % as wildcard)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">NOT LIKE</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Pattern does not match</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Example Groups</h2>

<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">All Nokia Beacon G6 (TR-098)</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Matches all Nokia devices with OUI 80AB4D</p>
        <code class="text-sm bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">oui = 80AB4D</code>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Devices Needing Firmware Update</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Matches devices on older firmware</p>
        <code class="text-sm bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">software_version LIKE %IJKJ%</code>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">All Calix GigaSpire</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Matches GigaSpire models</p>
        <code class="text-sm bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">product_class LIKE %GS%</code>
    </div>
</div>

<h2>Viewing Group Members</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    Click on a group name to view its members. For dynamic groups, the member count updates automatically
    as devices connect and their attributes change.
</p>

<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-6">
    <div class="flex">
        <div class="flex-shrink-0">
            @include('docs.partials.icon', ['icon' => 'info'])
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Related: Workflows</h3>
            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                Device Groups are used with Workflows to perform automated bulk operations.
                See the <a href="{{ route('docs.show', 'admin/workflows') }}" class="underline">Workflows</a> documentation.
            </p>
        </div>
    </div>
</div>
@endsection
