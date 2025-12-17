@extends('docs.layout')

@section('docs-content')
<h1>Supported Device Types</h1>

<p class="lead">
    Hay ACS supports TR-069/CWMP-compliant devices from multiple manufacturers.
    This reference lists all supported device types and their capabilities.
</p>

<h2>Nokia/Alcatel-Lucent</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">OUI</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Beacon G6</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/Gateway</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">80AB4D</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Beacon G6 (TR-181)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/Gateway</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-181</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">0C7C28</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Beacon 2</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Mesh AP</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">80AB4D</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Beacon 3.1</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Mesh AP</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">80AB4D</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Calix</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">844E-1 (GigaCenter)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/ONT</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Enterprise model</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">844G-1 (GigaCenter)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/ONT</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Residential model</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">854G-1 (GigaCenter)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/ONT</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">WiFi 6 model</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">GS4220E (GigaSpire u6)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Router/Gateway</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">WiFi 6E model</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">804Mesh</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Mesh AP</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Satellite node</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">GigaMesh u4m</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Mesh AP</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">GigaSpire satellite</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Sagemcom (SmartRG Branded)</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Model</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">SR505N</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">DSL Router</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">802.11n</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">SR515ac</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">DSL Router</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">802.11ac</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">SR516ac</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">DSL Router</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">802.11ac Wave 2</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Data Model Differences</h2>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Feature</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">TR-098</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">TR-181</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Root Object</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">InternetGatewayDevice.</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">Device.</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">WiFi Path</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">LANDevice.1.WLANConfiguration.</td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">WiFi.SSID. / WiFi.Radio.</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Get Everything</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Single partial path query (~10s)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Chunked queries (~30s)</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>OUI Reference</h2>

<p class="text-gray-600 dark:text-gray-400 mb-4">
    OUI (Organizationally Unique Identifier) is the first 6 characters of a MAC address,
    identifying the manufacturer. Common OUIs in this deployment:
</p>

<div class="overflow-x-auto mb-6">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">OUI</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Manufacturer</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">80AB4D</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Nokia</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-098 mode</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">0C7C28</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Nokia</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-181 mode</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">D0768F</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Calix</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Primary OUI</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">000631</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Calix</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Older devices</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
