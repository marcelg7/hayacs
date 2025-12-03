@extends('layouts.app')

@section('title', 'Create Workflow')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('workflows.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Workflows</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Create Workflow</h1>

        <form action="{{ route('workflows.store') }}" method="POST" x-data="workflowForm()">
            @csrf

            <div class="space-y-6">
                {{-- Basic Info --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="device_group_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Device Group *</label>
                        <select name="device_group_id" id="device_group_id" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a group...</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ (old('device_group_id', $selectedGroupId) == $group->id) ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workflow Name *</label>
                        <input type="text" name="name" id="name" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            value="{{ old('name') }}"
                            placeholder="e.g., Upgrade to Firmware 25.03">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="2"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">{{ old('description') }}</textarea>
                </div>

                {{-- Task Configuration --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Task Configuration</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="task_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Task Type *</label>
                            <select name="task_type" id="task_type" required x-model="taskType"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @foreach($taskTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="depends_on_workflow_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Depends On (Optional)</label>
                            <select name="depends_on_workflow_id" id="depends_on_workflow_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">No dependency</option>
                                @foreach($availableWorkflows as $wf)
                                    <option value="{{ $wf->id }}">{{ $wf->deviceGroup->name }}: {{ $wf->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">This workflow will only run after the dependency completes for each device</p>
                        </div>
                    </div>

                    {{-- Firmware Upgrade Parameters --}}
                    <div x-show="taskType === 'firmware_upgrade'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                        {{-- Device Type Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Device Type *</label>
                            <select x-model="selectedDeviceType" @change="selectedFirmwareId = ''; useActiveFirmware = false"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="">Select device type...</option>
                                @foreach($deviceTypes as $dt)
                                    <option value="{{ $dt->id }}">{{ $dt->name }} ({{ $dt->manufacturer }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select the device type to see available firmware</p>
                        </div>

                        {{-- Use Active Firmware Checkbox --}}
                        <div x-show="selectedDeviceType">
                            <label class="inline-flex items-center">
                                <input type="checkbox" x-model="useActiveFirmware" @change="if(useActiveFirmware) selectedFirmwareId = ''"
                                    class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Use Active Firmware</span>
                                <span class="ml-2 text-xs text-green-600 dark:text-green-400" x-show="getActiveFirmwareForType()"
                                    x-text="'(Currently: ' + (getActiveFirmwareForType()?.version || 'None') + ')'"></span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
                                Automatically uses whatever firmware is marked as "active" at execution time. Useful for ongoing upgrades.
                            </p>
                        </div>

                        {{-- Specific Firmware Selection --}}
                        <div x-show="selectedDeviceType && !useActiveFirmware">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firmware Version *</label>
                            <select x-model="selectedFirmwareId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="">Select firmware...</option>
                                <template x-for="fw in getFirmwaresForType()" :key="fw.id">
                                    <option :value="fw.id" x-text="fw.version + ' (' + fw.file_name + ')' + (fw.is_active ? ' ★ ACTIVE' : '')"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Hidden inputs for form submission --}}
                        <input type="hidden" name="task_parameters[device_type_id]" :value="selectedDeviceType">
                        <input type="hidden" name="task_parameters[firmware_id]" :value="useActiveFirmware ? '' : selectedFirmwareId">
                        <input type="hidden" name="task_parameters[use_active_firmware]" :value="useActiveFirmware ? '1' : '0'">

                        {{-- No firmware warning --}}
                        <div x-show="selectedDeviceType && getFirmwaresForType().length === 0"
                            class="bg-yellow-50 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 p-3 rounded-lg text-sm">
                            No firmware available for this device type. Please upload firmware first.
                        </div>
                    </div>

                    {{-- Set Parameters --}}
                    <div x-show="taskType === 'set_parameter_values'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Parameters (JSON)</label>
                        <textarea name="task_parameters_json" rows="6"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white font-mono text-sm"
                            placeholder='{"values": {"Device.ManagementServer.PeriodicInformInterval": "600"}}'></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use {serial_number}, {oui}, etc. for variable substitution</p>
                    </div>

                    {{-- Download Parameters --}}
                    <div x-show="taskType === 'download'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">File Type</label>
                            <select name="task_parameters[file_type]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white">
                                <option value="1 Firmware Upgrade Image">1 Firmware Upgrade Image</option>
                                <option value="3 Vendor Configuration File">3 Vendor Configuration File</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Download URL</label>
                            <input type="url" name="task_parameters[url]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                placeholder="https://hayacs.hay.net/files/config.xml">
                        </div>
                    </div>

                    {{-- Corteca Migration Tasks --}}
                    <div x-show="taskType === 'version_check'" class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-medium text-purple-800 dark:text-purple-200">Corteca Migration Step 1: Version Check</span>
                        </div>
                        <p class="text-sm text-purple-700 dark:text-purple-300">
                            Checks if device firmware is >= 24.02a. Devices that don't meet this requirement will need to be upgraded first using a firmware_upgrade workflow as a dependency.
                        </p>
                        <input type="hidden" name="task_parameters[required_version]" value="24.02a">
                    </div>

                    <div x-show="taskType === 'datamodel_check'" class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7c-2 0-3 1-3 3z"></path>
                            </svg>
                            <span class="font-medium text-purple-800 dark:text-purple-200">Corteca Migration Step 2: Datamodel Check</span>
                        </div>
                        <p class="text-sm text-purple-700 dark:text-purple-300">
                            Verifies device data model. Devices already on TR-181 will be skipped. Devices on TR-098 will proceed to the next migration step.
                        </p>
                    </div>

                    <div x-show="taskType === 'transition_backup'" class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            <span class="font-medium text-purple-800 dark:text-purple-200">Corteca Migration Step 3: Transition Backup</span>
                        </div>
                        <p class="text-sm text-purple-700 dark:text-purple-300">
                            Creates a full backup of all device parameters before migration. This backup will be used to restore settings after the device converts to TR-181.
                        </p>
                    </div>

                    {{-- Extract WiFi via SSH --}}
                    <div x-show="taskType === 'extract_wifi_ssh'" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span class="font-medium text-blue-800 dark:text-blue-200">TR-181 Migration: Extract WiFi Config via SSH</span>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-800/50 p-3 rounded mb-3">
                            <p class="text-sm font-bold text-blue-800 dark:text-blue-200">
                                CRITICAL: Run this BEFORE the pre-config push!
                            </p>
                        </div>
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            Extracts WiFi configuration including <strong>plaintext passwords</strong> via SSH. TR-069 backups mask passwords, so this step is essential for WiFi preservation.
                        </p>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mt-2">
                            Requires device SSH credentials to be configured. WiFi configs will be stored and used for restoration after migration.
                        </p>
                    </div>

                    {{-- HayACS TR-181 Pre-config --}}
                    <div x-show="taskType === 'hayacs_tr181_preconfig'" class="mt-4 p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                            <span class="font-medium text-green-800 dark:text-green-200">TR-181 Migration: Load HayACS Pre-config</span>
                        </div>
                        <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                            Pushes the HayACS TR-181 pre-configuration file to the device. This triggers:
                        </p>
                        <ul class="text-sm text-green-700 dark:text-green-300 list-disc list-inside space-y-1">
                            <li>Factory reset of the device</li>
                            <li>Switch from TR-098 to TR-181 data model</li>
                            <li>Device reconnects to HayACS with TR-181 parameters</li>
                        </ul>
                        <div class="mt-3 p-2 bg-green-100 dark:bg-green-800/50 rounded text-sm text-green-800 dark:text-green-200">
                            <strong>Pre-config URL:</strong> <code class="text-xs">http://hayacs.hay.net/device-config/migration/beacon-g6-pre-config-hayacs-tr181.xml</code>
                        </div>
                        <input type="hidden" name="task_parameters[preconfig_url]" value="http://hayacs.hay.net/device-config/migration/beacon-g6-pre-config-hayacs-tr181.xml">
                    </div>

                    {{-- Restore WiFi from SSH Config --}}
                    <div x-show="taskType === 'wifi_restore_ssh'" class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span class="font-medium text-purple-800 dark:text-purple-200">TR-181 Migration: Restore WiFi from SSH Config</span>
                        </div>
                        <p class="text-sm text-purple-700 dark:text-purple-300">
                            After the device reconnects in TR-181 mode, this restores WiFi settings from the SSH-extracted configuration. Passwords are mapped from UCI interface names to TR-181 paths.
                        </p>
                        <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/30 rounded text-sm text-yellow-800 dark:text-yellow-200">
                            <strong>Important:</strong> Set this workflow's dependency to the "Load HayACS Pre-config" workflow so it runs after the device reboots in TR-181 mode.
                        </div>
                    </div>

                    {{-- Combined Restore (WiFi SSH + TR-069 Backup) - RECOMMENDED --}}
                    <div x-show="taskType === 'combined_restore'" class="mt-4 p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-medium text-green-800 dark:text-green-200">TR-181 Migration: Combined Restore (Recommended)</span>
                            <span class="px-2 py-0.5 text-xs bg-green-200 dark:bg-green-700 text-green-800 dark:text-green-200 rounded-full">Best Option</span>
                        </div>
                        <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                            This is the <strong>recommended restore option</strong> for TR-181 migration. It combines:
                        </p>
                        <ul class="text-sm text-green-700 dark:text-green-300 list-disc pl-5 space-y-1 mb-3">
                            <li><strong>WiFi (from SSH extraction)</strong>: SSIDs and plaintext passwords from SSH-extracted config</li>
                            <li><strong>DHCP settings</strong>: Pool range, lease time, DNS servers, reservations</li>
                            <li><strong>Time/NTP</strong>: NTP servers and timezone settings</li>
                            <li><strong>Parental Controls</strong>: Profiles, URL filters, schedules</li>
                            <li><strong>Port Forwarding</strong>: NAT port mappings (if any exist)</li>
                        </ul>
                        <p class="text-sm text-green-700 dark:text-green-300">
                            All parameters are automatically converted from TR-098 to TR-181 format. WiFi passwords use the SSH-extracted plaintext values (not the masked TR-069 backup values).
                        </p>
                        <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/30 rounded text-sm text-yellow-800 dark:text-yellow-200">
                            <strong>Prerequisites:</strong>
                            <ol class="list-decimal pl-5 mt-1 space-y-1">
                                <li>Run "Extract WiFi Config via SSH" before migration</li>
                                <li>Run "Transition Backup" before migration</li>
                                <li>Set dependency to "Load HayACS Pre-config" workflow</li>
                            </ol>
                        </div>
                    </div>

                    {{-- Legacy Corteca Pre-config (custom URL) --}}
                    <div x-show="taskType === 'corteca_preconfig'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                            <span class="font-medium text-gray-800 dark:text-gray-200">Load Pre-config (Custom URL)</span>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                            Downloads a custom pre-configuration file. Use this for non-standard migration scenarios.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pre-config File URL *</label>
                            <input type="url" name="task_parameters[preconfig_url]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                placeholder="http://hayacs.hay.net/device-config/migration/custom-preconfig.xml">
                        </div>
                    </div>

                    <div x-show="taskType === 'corteca_restore'" class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span class="font-medium text-purple-800 dark:text-purple-200">Corteca Migration Step 5: Restore Settings</span>
                        </div>
                        <p class="text-sm text-purple-700 dark:text-purple-300">
                            After the device reboots in TR-181 mode, this step restores the converted settings from the transition backup. Parameters are automatically mapped from TR-098 to TR-181 format.
                        </p>
                        <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/30 rounded text-sm text-yellow-800 dark:text-yellow-200">
                            <strong>Important:</strong> Set this workflow's dependency to the "Load Pre-config" workflow so it runs after the device reboots.
                        </div>
                    </div>
                </div>

                {{-- Schedule Configuration --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Schedule</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="schedule_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Schedule Type *</label>
                            <select name="schedule_type" id="schedule_type" required x-model="scheduleType"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @foreach($scheduleTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="rate_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate Limit (per hour)</label>
                            <input type="number" name="rate_limit" id="rate_limit" min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('rate_limit', 0) }}"
                                placeholder="0 = unlimited">
                        </div>
                    </div>

                    {{-- Scheduled Time --}}
                    <div x-show="scheduleType === 'scheduled'" class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start At</label>
                            <input type="datetime-local" name="schedule_config[start_at]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End At (Optional)</label>
                            <input type="datetime-local" name="schedule_config[end_at]"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>

                    {{-- Recurring Schedule --}}
                    <div x-show="scheduleType === 'recurring'" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Days of Week</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="schedule_config[days][]" value="{{ $key }}"
                                            class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                                        <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time</label>
                                <input type="time" name="schedule_config[start_time]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    value="02:00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time</label>
                                <input type="time" name="schedule_config[end_time]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    value="05:00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                                <select name="schedule_config[timezone]"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="America/Toronto">America/Toronto (EST)</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Advanced Options --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Advanced Options</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="retry_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retry Count</label>
                            <input type="number" name="retry_count" id="retry_count" min="0" max="10"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('retry_count', 0) }}">
                        </div>

                        <div>
                            <label for="retry_delay_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retry Delay (minutes)</label>
                            <input type="number" name="retry_delay_minutes" id="retry_delay_minutes" min="1"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('retry_delay_minutes', 5) }}">
                        </div>

                        <div>
                            <label for="stop_on_failure_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stop on Failure %</label>
                            <input type="number" name="stop_on_failure_percent" id="stop_on_failure_percent" min="0" max="100"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                value="{{ old('stop_on_failure_percent', 0) }}"
                                placeholder="0 = never stop">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="run_once_per_device" value="1" checked
                                class="rounded border-gray-300 text-blue-600 dark:bg-gray-700">
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Run once per device (don't repeat for devices that completed)</span>
                        </label>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('workflows.index') }}"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Create Workflow
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function workflowForm() {
    return {
        taskType: 'set_parameter_values',
        scheduleType: 'immediate',
        selectedDeviceType: '',
        selectedFirmwareId: '',
        useActiveFirmware: false,
        firmwaresByDeviceType: @json($firmwaresByDeviceType),

        getFirmwaresForType() {
            if (!this.selectedDeviceType) return [];
            return this.firmwaresByDeviceType[this.selectedDeviceType] || [];
        },

        getActiveFirmwareForType() {
            const firmwares = this.getFirmwaresForType();
            return firmwares.find(fw => fw.is_active) || null;
        }
    }
}
</script>
@endsection
