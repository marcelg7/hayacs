<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Firmware;
use App\Models\Task;
use App\Models\ConfigBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service to handle TR-098 to TR-181 migration for Nokia Beacon G6 devices
 *
 * Migration Requirements:
 * 1. Device must be Nokia Beacon G6 (product_class check)
 * 2. Device must be running TR-181-capable firmware (3FE49996IJMK14)
 * 3. Device must currently be on TR-098 data model
 * 4. ConfigMigration flag must be set BEFORE applying pre-config file
 */
class Tr181MigrationService
{
    /**
     * Required firmware version for TR-181 migration
     */
    const REQUIRED_FIRMWARE = '3FE49996IJMK14';

    /**
     * Nokia Beacon G6 product class identifiers
     */
    const BEACON_G6_PRODUCT_CLASSES = ['Beacon G6', 'G-240W-F', 'Beacon6'];

    /**
     * Nokia OUIs (Organizationally Unique Identifiers)
     * 80AB4D - Nokia Corporation (most common for Beacon devices)
     * 0C7C28 - Nokia (Alcatel-Lucent brand)
     * D0542D - Nokia (older devices)
     */
    const NOKIA_OUIS = ['80AB4D', '0C7C28', 'D0542D'];

    /**
     * Parameter paths for migration
     */
    const CONFIG_MIGRATION_PARAM = 'InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration';
    const TR181_ENABLED_PARAM = 'InternetGatewayDevice.ManagementServer.X_ASB_COM_TR181Enabled';

    /**
     * Pre-config file for Hay ACS
     */
    const PRECONFIG_FILE = 'beacon-g6-pre-config-hayacs-tr181.xml';

    /**
     * Check if a device is eligible for TR-181 migration
     *
     * @param Device $device
     * @return array{eligible: bool, reasons: array, warnings: array}
     */
    public function checkEligibility(Device $device): array
    {
        $reasons = [];
        $warnings = [];

        // Check 1: Is this a Nokia Beacon G6?
        $isBeaconG6 = $this->isBeaconG6($device);
        if (!$isBeaconG6) {
            $reasons[] = "Device is not a Nokia Beacon G6 (product_class: {$device->product_class}, OUI: {$device->oui})";
        }

        // Check 2: Is the device currently on TR-098?
        $dataModel = $device->getDataModel();
        if ($dataModel !== 'TR-098') {
            $reasons[] = "Device is already on {$dataModel} data model (migration not needed)";
        }

        // Check 3: Is the device running TR-181-capable firmware?
        $firmwareOk = $this->hasRequiredFirmware($device);
        if (!$firmwareOk) {
            $reasons[] = "Device firmware ({$device->software_version}) is not TR-181 capable. Required: " . self::REQUIRED_FIRMWARE;
        }

        // Check 4: Is the device online/connected?
        if (!$device->online) {
            $warnings[] = "Device is currently offline. Migration will proceed when device reconnects.";
        }

        // Check 5: Does the device have a recent backup?
        $hasBackup = $device->configBackups()->where('created_at', '>=', now()->subDays(7))->exists();
        if (!$hasBackup) {
            $warnings[] = "No recent backup found. A full parameter harvest will be performed before migration.";
        }

        // Check 6: Are there pending tasks?
        $pendingTasks = $device->pendingTasks()->count();
        if ($pendingTasks > 0) {
            $warnings[] = "Device has {$pendingTasks} pending task(s). Migration will queue after existing tasks.";
        }

        // Check 7: Check if ConfigMigration parameter exists (if we have parameter data)
        $configMigrationExists = $device->parameters()
            ->where('name', 'like', '%X_ALU-COM_ConfigMigration%')
            ->exists();
        if (!$configMigrationExists && $device->parameters()->count() > 0) {
            $warnings[] = "ConfigMigration parameter not found in device parameters. Will attempt to set anyway.";
        }

        $eligible = empty($reasons);

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'warnings' => $warnings,
            'device_info' => [
                'serial_number' => $device->serial_number,
                'product_class' => $device->product_class,
                'oui' => $device->oui,
                'firmware' => $device->software_version,
                'data_model' => $dataModel,
                'online' => $device->online,
                'last_inform' => $device->last_inform?->toDateTimeString(),
            ],
        ];
    }

    /**
     * Check if device is a Nokia Beacon G6
     */
    public function isBeaconG6(Device $device): bool
    {
        $deviceOui = strtoupper($device->oui);

        // Check OUI first (most reliable)
        if (in_array($deviceOui, self::NOKIA_OUIS)) {
            // Also verify product class contains Beacon G6 indicators
            foreach (self::BEACON_G6_PRODUCT_CLASSES as $productClass) {
                if (stripos($device->product_class, $productClass) !== false) {
                    return true;
                }
            }
            // Nokia OUI but unknown product class - check model name
            if (stripos($device->model_name ?? '', 'Beacon') !== false) {
                return true;
            }
        }

        // Fallback: Check manufacturer and product class
        if (stripos($device->manufacturer, 'Nokia') !== false ||
            stripos($device->manufacturer, 'Alcatel') !== false ||
            stripos($device->manufacturer, 'ALU') !== false) {
            foreach (self::BEACON_G6_PRODUCT_CLASSES as $productClass) {
                if (stripos($device->product_class, $productClass) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if device has the required firmware for TR-181 migration
     */
    public function hasRequiredFirmware(Device $device): bool
    {
        $firmware = $device->software_version;

        // Direct match
        if (stripos($firmware, self::REQUIRED_FIRMWARE) !== false) {
            return true;
        }

        // Check against DeviceType's active firmware
        $deviceType = DeviceType::where('product_class', $device->product_class)->first();
        if ($deviceType) {
            $activeFirmware = $deviceType->activeFirmware();
            if ($activeFirmware && stripos($activeFirmware->file_name, self::REQUIRED_FIRMWARE) !== false) {
                // Device type has the right firmware configured
                // Check if device is running it
                if (stripos($firmware, $activeFirmware->version) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get count of devices eligible for migration
     */
    public function getEligibleDeviceCount(): array
    {
        $beaconG6Devices = Device::where(function ($query) {
            $query->whereIn('oui', self::NOKIA_OUIS)
                  ->orWhere('product_class', 'like', '%Beacon G6%')
                  ->orWhere('product_class', 'like', '%G-240W-F%');
        })->get();

        $stats = [
            'total_beacon_g6' => $beaconG6Devices->count(),
            'tr098_devices' => 0,
            'tr181_devices' => 0,
            'eligible_for_migration' => 0,
            'needs_firmware_upgrade' => 0,
            'already_migrated' => 0,
        ];

        foreach ($beaconG6Devices as $device) {
            $dataModel = $device->getDataModel();

            if ($dataModel === 'TR-098') {
                $stats['tr098_devices']++;

                if ($this->hasRequiredFirmware($device)) {
                    $stats['eligible_for_migration']++;
                } else {
                    $stats['needs_firmware_upgrade']++;
                }
            } else {
                $stats['tr181_devices']++;
                $stats['already_migrated']++;
            }
        }

        return $stats;
    }

    /**
     * Create migration tasks for a device
     *
     * Task sequence:
     * 1. Harvest all parameters (backup)
     * 2. Set ConfigMigration = 1
     * 3. Push pre-config file (triggers TR-181 switch)
     *
     * @param Device $device
     * @param bool $skipBackup Skip parameter harvest if recent backup exists
     * @return array{success: bool, tasks: array, message: string}
     */
    public function createMigrationTasks(Device $device, bool $skipBackup = false): array
    {
        // First check eligibility
        $eligibility = $this->checkEligibility($device);

        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'tasks' => [],
                'message' => 'Device not eligible: ' . implode('; ', $eligibility['reasons']),
            ];
        }

        $tasks = [];
        $taskOrder = 1;

        // Task 1: Harvest all parameters (unless skipped)
        if (!$skipBackup) {
            $harvestTask = Task::create([
                'device_id' => $device->id,
                'type' => 'get_parameter_values',
                'status' => 'pending',
                'parameters' => [
                    'paths' => ['InternetGatewayDevice.'],
                    'purpose' => 'tr181_migration_backup',
                    'migration_step' => 1,
                ],
                'created_at' => now(),
            ]);
            $tasks[] = $harvestTask;
            $taskOrder++;
        }

        // Task 2: Set ConfigMigration = 1
        $configMigrationTask = Task::create([
            'device_id' => $device->id,
            'type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => [
                'parameters' => [
                    [
                        'name' => self::CONFIG_MIGRATION_PARAM,
                        'value' => '1',
                        'type' => 'xsd:string',
                    ],
                ],
                'purpose' => 'tr181_migration_config_flag',
                'migration_step' => $taskOrder,
            ],
            'created_at' => now(),
        ]);
        $tasks[] = $configMigrationTask;
        $taskOrder++;

        // Task 3: Push pre-config file
        $preconfigUrl = $this->getPreconfigFileUrl();
        $preconfigTask = Task::create([
            'device_id' => $device->id,
            'type' => 'download',
            'status' => 'pending',
            'parameters' => [
                'url' => $preconfigUrl,
                'file_type' => '3 Vendor Configuration File',
                'purpose' => 'tr181_migration_preconfig',
                'migration_step' => $taskOrder,
            ],
            'created_at' => now(),
        ]);
        $tasks[] = $preconfigTask;

        // Tag the device as migration in progress
        $device->update([
            'tags' => array_merge($device->tags ?? [], ['tr181_migration_pending']),
        ]);

        Log::info("TR-181 migration tasks created for device {$device->serial_number}", [
            'device_id' => $device->id,
            'task_count' => count($tasks),
            'task_ids' => array_map(fn($t) => $t->id, $tasks),
        ]);

        return [
            'success' => true,
            'tasks' => $tasks,
            'message' => 'Migration tasks created successfully. ' . count($tasks) . ' tasks queued.',
            'warnings' => $eligibility['warnings'],
        ];
    }

    /**
     * Get the URL for the pre-config file
     */
    public function getPreconfigFileUrl(): string
    {
        // The pre-config file should be served from a URL accessible by the device
        // Use HTTP since TR-069 devices may not support HTTPS with cert validation
        $baseUrl = config('app.url');

        // Force HTTP for device downloads (many TR-069 devices don't support HTTPS)
        $httpUrl = str_replace('https://', 'http://', $baseUrl);

        return $httpUrl . '/storage/migration/' . self::PRECONFIG_FILE;
    }

    /**
     * Handle post-migration verification
     * Called when a device reconnects after migration
     *
     * @param Device $device
     * @return array{success: bool, wifi_preserved: bool, message: string}
     */
    public function verifyMigration(Device $device): array
    {
        $dataModel = $device->getDataModel();

        if ($dataModel !== 'TR-181') {
            return [
                'success' => false,
                'wifi_preserved' => null,
                'message' => "Migration verification failed: Device still on {$dataModel}",
            ];
        }

        // Check WiFi SSID - compare with backup
        $lastBackup = $device->configBackups()
            ->where('created_at', '>=', now()->subDays(1))
            ->whereJsonContains('metadata->purpose', 'tr181_migration_backup')
            ->first();

        if (!$lastBackup) {
            // Try to find any recent backup
            $lastBackup = $device->configBackups()
                ->where('created_at', '>=', now()->subDays(7))
                ->first();
        }

        // Get current WiFi SSID (TR-181 path)
        $currentSsid = $device->getParameter('Device.WiFi.SSID.1.SSID');

        $wifiPreserved = true;
        $message = "Migration completed successfully. Device now on TR-181.";

        if ($lastBackup && $currentSsid) {
            // Compare with backup
            $backupParams = json_decode($lastBackup->parameters, true);
            $oldSsid = $backupParams['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'] ?? null;

            if ($oldSsid && $currentSsid !== $oldSsid) {
                $wifiPreserved = false;
                $message = "WARNING: WiFi SSID changed! Old: {$oldSsid}, New: {$currentSsid}. ConfigMigration may have failed.";

                Log::warning("TR-181 migration WiFi mismatch for device {$device->serial_number}", [
                    'old_ssid' => $oldSsid,
                    'new_ssid' => $currentSsid,
                ]);
            }
        }

        // Update device tags
        $tags = $device->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== 'tr181_migration_pending');
        $tags[] = $wifiPreserved ? 'tr181_migration_success' : 'tr181_migration_wifi_lost';
        $device->update(['tags' => array_values($tags)]);

        Log::info("TR-181 migration verification for device {$device->serial_number}", [
            'device_id' => $device->id,
            'data_model' => $dataModel,
            'wifi_preserved' => $wifiPreserved,
        ]);

        return [
            'success' => true,
            'wifi_preserved' => $wifiPreserved,
            'message' => $message,
        ];
    }

    /**
     * Get TR-098 to TR-181 parameter mapping for WiFi fallback
     */
    public function getWifiParameterMapping(): array
    {
        return [
            // 2.4 GHz Primary
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => 'Device.WiFi.SSID.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable' => 'Device.WiFi.SSID.1.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType' => 'Device.WiFi.AccessPoint.1.Security.ModeEnabled',

            // 5 GHz Primary
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID' => 'Device.WiFi.SSID.5.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.5.Security.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable' => 'Device.WiFi.SSID.5.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled',

            // 6 GHz (if present)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.SSID' => 'Device.WiFi.SSID.9.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.9.Security.KeyPassphrase',

            // Guest Network (2.4 GHz)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID' => 'Device.WiFi.SSID.2.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase',

            // Guest Network (5 GHz)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSID' => 'Device.WiFi.SSID.6.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.6.Security.KeyPassphrase',
        ];
    }

    /**
     * Create fallback tasks to restore WiFi from backup
     * Used when ConfigMigration fails and WiFi settings are lost
     *
     * @param Device $device
     * @param ConfigBackup $backup
     * @return array
     */
    public function createWifiFallbackTasks(Device $device, ConfigBackup $backup): array
    {
        $backupParams = json_decode($backup->parameters, true);
        $mapping = $this->getWifiParameterMapping();
        $parametersToSet = [];

        foreach ($mapping as $tr098Path => $tr181Path) {
            if (isset($backupParams[$tr098Path]) && !empty($backupParams[$tr098Path])) {
                $parametersToSet[] = [
                    'name' => $tr181Path,
                    'value' => $backupParams[$tr098Path],
                    'type' => 'xsd:string',
                ];
            }
        }

        if (empty($parametersToSet)) {
            return [
                'success' => false,
                'message' => 'No WiFi parameters found in backup to restore',
            ];
        }

        // Create task to set all WiFi parameters
        $task = Task::create([
            'device_id' => $device->id,
            'type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => [
                'parameters' => $parametersToSet,
                'purpose' => 'tr181_migration_wifi_fallback',
            ],
            'created_at' => now(),
        ]);

        Log::info("TR-181 WiFi fallback task created for device {$device->serial_number}", [
            'device_id' => $device->id,
            'task_id' => $task->id,
            'parameter_count' => count($parametersToSet),
        ]);

        return [
            'success' => true,
            'task' => $task,
            'message' => 'WiFi fallback task created with ' . count($parametersToSet) . ' parameters',
        ];
    }

    /**
     * Find a previous device record with the same serial number but different ID
     * Used to detect when a device reconnects with a new OUI after migration
     *
     * @param Device $newDevice The newly connected device
     * @return Device|null The previous device record, or null if not found
     */
    public function findPreviousDeviceRecord(Device $newDevice): ?Device
    {
        return Device::where('serial_number', $newDevice->serial_number)
            ->where('id', '!=', $newDevice->id)
            ->where('product_class', $newDevice->product_class)
            ->first();
    }

    /**
     * Check if a device appears to be a migrated device reconnecting with a new ID
     *
     * @param Device $device
     * @return array{is_migrated: bool, previous_device: ?Device, message: string}
     */
    public function checkForMigratedDevice(Device $device): array
    {
        // Only check TR-181 devices (post-migration state)
        if ($device->getDataModel() !== 'TR-181') {
            return [
                'is_migrated' => false,
                'previous_device' => null,
                'message' => 'Device is not on TR-181',
            ];
        }

        // Look for a previous device with same serial but different ID
        $previousDevice = $this->findPreviousDeviceRecord($device);

        if (!$previousDevice) {
            return [
                'is_migrated' => false,
                'previous_device' => null,
                'message' => 'No previous device record found',
            ];
        }

        // Check if previous device was TR-098 (pre-migration state)
        $wasT098 = $previousDevice->getDataModel() === 'TR-098';

        // Check if previous device had migration tag
        $hadMigrationTag = in_array('tr181_migration_pending', $previousDevice->tags ?? []);

        if ($wasT098 || $hadMigrationTag) {
            return [
                'is_migrated' => true,
                'previous_device' => $previousDevice,
                'message' => "Device appears to be migrated from {$previousDevice->id} (OUI changed from {$previousDevice->oui} to {$device->oui})",
            ];
        }

        return [
            'is_migrated' => false,
            'previous_device' => $previousDevice,
            'message' => 'Previous device found but migration not confirmed',
        ];
    }

    /**
     * Merge data from old device record to new device record after migration
     * This handles the case where OUI changes and creates a new device ID
     *
     * @param Device $newDevice The new device record (post-migration)
     * @param Device $oldDevice The old device record (pre-migration)
     * @return array{success: bool, merged: array, message: string}
     */
    public function mergeDeviceRecords(Device $newDevice, Device $oldDevice): array
    {
        $merged = [
            'config_backups' => 0,
            'parameters' => 0,
            'tasks_migrated' => 0,
        ];

        try {
            DB::beginTransaction();

            // 1. Migrate config backups
            $backups = ConfigBackup::where('device_id', $oldDevice->id)->get();
            foreach ($backups as $backup) {
                // Update device_id to new device, but keep reference to old ID
                $metadata = $backup->metadata ?? [];
                $metadata['migrated_from_device_id'] = $oldDevice->id;
                $metadata['migration_date'] = now()->toDateTimeString();

                $backup->update([
                    'device_id' => $newDevice->id,
                    'metadata' => $metadata,
                ]);
                $merged['config_backups']++;
            }

            // 2. Copy subscriber association if new device doesn't have one
            if (!$newDevice->subscriber_id && $oldDevice->subscriber_id) {
                $newDevice->update(['subscriber_id' => $oldDevice->subscriber_id]);
            }

            // 3. Copy connection request credentials if not set
            if (!$newDevice->connection_request_username && $oldDevice->connection_request_username) {
                $newDevice->update([
                    'connection_request_username' => $oldDevice->connection_request_username,
                    'connection_request_password' => $oldDevice->connection_request_password,
                ]);
            }

            // 4. Mark old device as migrated
            $oldTags = $oldDevice->tags ?? [];
            $oldTags = array_filter($oldTags, fn($t) => $t !== 'tr181_migration_pending');
            $oldTags[] = 'tr181_migrated_to_' . $newDevice->id;
            $oldDevice->update([
                'tags' => array_values($oldTags),
                'online' => false, // Old record should no longer be considered online
            ]);

            // 5. Tag new device as migration successor
            $newTags = $newDevice->tags ?? [];
            $newTags[] = 'tr181_migration_success';
            $newTags[] = 'migrated_from_' . $oldDevice->id;
            $newDevice->update(['tags' => array_values(array_unique($newTags))]);

            DB::commit();

            Log::info("Device records merged after TR-181 migration", [
                'old_device_id' => $oldDevice->id,
                'new_device_id' => $newDevice->id,
                'serial_number' => $newDevice->serial_number,
                'backups_migrated' => $merged['config_backups'],
            ]);

            return [
                'success' => true,
                'merged' => $merged,
                'message' => "Successfully merged device data. {$merged['config_backups']} backups transferred.",
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to merge device records: " . $e->getMessage(), [
                'old_device_id' => $oldDevice->id,
                'new_device_id' => $newDevice->id,
            ]);

            return [
                'success' => false,
                'merged' => $merged,
                'message' => 'Failed to merge: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Auto-detect and handle device ID change after migration
     * Call this when a new Beacon G6 device connects with TR-181
     *
     * @param Device $device
     * @return array
     */
    public function handlePostMigrationDeviceIdChange(Device $device): array
    {
        $check = $this->checkForMigratedDevice($device);

        if (!$check['is_migrated']) {
            return [
                'action_taken' => false,
                'message' => $check['message'],
            ];
        }

        // Device appears to be migrated, merge the records
        $mergeResult = $this->mergeDeviceRecords($device, $check['previous_device']);

        return [
            'action_taken' => true,
            'previous_device_id' => $check['previous_device']->id,
            'merge_result' => $mergeResult,
            'message' => $check['message'],
        ];
    }
}
