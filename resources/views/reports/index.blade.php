@extends('layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Reports</h1>
                @if(isset($summaries['cached_at']))
                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Cached {{ \Carbon\Carbon::parse($summaries['cached_at'])->diffForHumans() }}
                        <a href="{{ route('reports.refresh') }}" class="ml-2 text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300" title="Refresh now">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </a>
                    </div>
                @endif
            </div>
            <a href="{{ route('reports.export-all-devices') }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Export All Devices (CSV)
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Online Devices</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summaries['online_devices']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Offline Devices</p>
                        <p class="text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($summaries['offline_devices']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">30+ Days Inactive</p>
                        <p class="text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($summaries['inactive_30_days']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Devices</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summaries['total_devices']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Problem Reports Section -->
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Problem Detection Reports</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <!-- Offline Devices -->
            <a href="{{ route('reports.offline-devices') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-red-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Offline Devices</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Devices not currently connected</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['offline_devices'] > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['offline_devices']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Includes Wake Device action</p>
            </a>

            <!-- 30+ Days Inactive -->
            <a href="{{ route('reports.inactive-devices') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-yellow-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">30+ Day Inactive</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Equipment potentially disconnected</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['inactive_30_days'] > 0 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['inactive_30_days']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Equipment recovery candidates</p>
            </a>

            <!-- Excessive Informs -->
            <a href="{{ route('reports.excessive-informs') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-orange-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Excessive Informs</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Devices checking in too frequently</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['excessive_informs'] > 0 ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['excessive_informs']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Last 24 hours, >50 informs</p>
            </a>

            <!-- Devices Without Subscriber -->
            <a href="{{ route('reports.devices-without-subscriber') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">No Subscriber Link</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Orphaned devices without customer</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['no_subscriber'] > 0 ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['no_subscriber']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Needs subscriber linking</p>
            </a>

            <!-- Duplicate Serials -->
            <a href="{{ route('reports.duplicate-serials') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-pink-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Duplicate Serial Numbers</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Data integrity issue</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['duplicate_serials'] > 0 ? 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['duplicate_serials']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Merge or investigate</p>
            </a>

            <!-- Duplicate MACs -->
            <a href="{{ route('reports.duplicate-macs') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-indigo-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Duplicate MAC Addresses</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Possible cloning or data issue</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['duplicate_macs'] > 0 ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['duplicate_macs']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Security concern</p>
            </a>

            <!-- SmartRG on Non-DSL -->
            <a href="{{ route('reports.smartrg-on-non-dsl') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-l-4 border-amber-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">SmartRG on Non-DSL</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">DSL routers on Fibre/Cable</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summaries['smartrg_on_non_dsl'] > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : 'bg-green-100 text-green-800' }}">
                        {{ number_format($summaries['smartrg_on_non_dsl']) }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-3">Equipment mismatch</p>
            </a>
        </div>

        <!-- Network Reports Section -->
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Network & Connectivity Reports</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <!-- Connection Request Failures -->
            <a href="{{ route('reports.connection-request-failures') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-semibold text-gray-900 dark:text-white">Connection Request Issues</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Devices we can't reach on-demand</p>
                <p class="text-xs text-gray-400 mt-3">Diagnose CR problems</p>
            </a>

            <!-- NAT-ed Devices -->
            <a href="{{ route('reports.nat-devices') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-semibold text-gray-900 dark:text-white">NAT-ed Devices</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Devices behind NAT (private IP)</p>
                <p class="text-xs text-gray-400 mt-3">May need STUN setup</p>
            </a>

            <!-- STUN Devices -->
            <a href="{{ route('reports.stun-devices') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-semibold text-gray-900 dark:text-white">STUN Enabled Devices</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Devices using STUN for NAT traversal</p>
                <p class="text-xs text-gray-400 mt-3">UDP connection request capable</p>
            </a>
        </div>

        <!-- Inventory Reports Section -->
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Inventory & Fleet Reports</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Firmware Report -->
            <a href="{{ route('reports.firmware') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-semibold text-gray-900 dark:text-white">Firmware Versions</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Software versions across fleet</p>
                <p class="text-xs text-gray-400 mt-3">Identify outdated firmware</p>
            </a>

            <!-- Device Types Report -->
            <a href="{{ route('reports.device-types') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-semibold text-gray-900 dark:text-white">Device Types</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Fleet composition by model</p>
                <p class="text-xs text-gray-400 mt-3">Manufacturer breakdown</p>
            </a>

            <!-- Export All Devices -->
            <a href="{{ route('reports.export-all-devices') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 hover:shadow-lg transition-shadow border-2 border-dashed border-gray-300 dark:border-gray-600">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Export All Devices</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Download complete CSV</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
