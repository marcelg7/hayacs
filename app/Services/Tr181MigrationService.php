<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\DeviceWifiConfig;
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
 * 4. SSH WiFi configs should be extracted BEFORE migration (preserves plaintext passwords)
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
     * Nokia OUIs - use the centralized list from Device model
     * @see \App\Models\Device::NOKIA_OUIS
     */

    /**
     * Parameter paths for migration
     */
    const CONFIG_MIGRATION_PARAM = 'InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration';
    const TR181_ENABLED_PARAM = 'InternetGatewayDevice.ManagementServer.X_ASB_COM_TR181Enabled';

    /**
     * Pre-config file for TR-181 migration
     * This simpler file only changes OperatorID to EGEB to trigger TR-181 mode
     * Important: Device keeps same OUI - no device ID change!
     */
    const PRECONFIG_FILE = 'PREEGEBOPR';

    /**
     * External URL for the preconfig file (hosted on hay.net for compatibility)
     * Files served from hayacs.hay.net fail on Nokia devices, but hay.net works
     */
    const PRECONFIG_EXTERNAL_URL = 'https://hay.net/PREEGEBOPR';

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

        // Check 7: Check if SSH WiFi configs are extracted (important for WiFi preservation)
        $sshWifiCheck = $this->checkSshWifiConfigsAvailable($device);
        if (!$sshWifiCheck['has_passwords']) {
            $warnings[] = "IMPORTANT: Extract WiFi config via SSH before migration! TR-069 backups have masked passwords.";
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

        // Check OUI first (most reliable) - use centralized Device model OUI list
        if (in_array($deviceOui, Device::NOKIA_OUIS)) {
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
            $query->whereIn('oui', Device::NOKIA_OUIS)
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
     * Create migration preparation tasks for a device
     *
     * Nokia Beacon G6 TR-098 to TR-181 migration process:
     * 1. Extract WiFi config via SSH (preserves plaintext passwords) - do this first!
     * 2. Create parameter backup via TR-069
     * 3. Push pre-config file (triggers factory reset + TR-181 switch)
     * 4. Device reconnects with TR-181 data model
     * 5. Restore WiFi settings from SSH-extracted config
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

        // Check if we have SSH WiFi configs (critical for WiFi preservation)
        $sshWifiCheck = $this->checkSshWifiConfigsAvailable($device);

        $tasks = [];
        $warnings = $eligibility['warnings'];

        // Task 1: Create TR-069 parameter backup (unless skipped)
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
        }

        // Task 2: Push pre-config file to trigger TR-181 migration
        // The pre-config file causes the device to factory reset and switch to TR-181
        $preconfigUrl = $this->getPreconfigFileUrl();
        $preconfigTask = Task::create([
            'device_id' => $device->id,
            'type' => 'download',
            'status' => 'pending',
            'parameters' => [
                'url' => $preconfigUrl,
                'file_type' => '3 Vendor Configuration File',
                'purpose' => 'tr181_migration_preconfig',
                'migration_step' => 2,
                'note' => 'Pre-config file triggers factory reset and TR-181 data model switch',
            ],
            'created_at' => now(),
        ]);
        $tasks[] = $preconfigTask;

        // Add warning about WiFi if SSH configs not extracted
        if (!$sshWifiCheck['has_passwords']) {
            $warnings[] = 'WARNING: No SSH WiFi configs extracted! WiFi passwords will be lost after migration. Extract WiFi config via SSH before proceeding.';
        } else {
            $warnings[] = "WiFi preservation ready: {$sshWifiCheck['password_count']} networks with passwords will be restored after migration.";
        }

        // Tag the device as migration in progress
        $device->update([
            'tags' => array_merge($device->tags ?? [], ['tr181_migration_pending']),
        ]);

        Log::info("TR-181 migration tasks created for device {$device->serial_number}", [
            'device_id' => $device->id,
            'task_count' => count($tasks),
            'task_ids' => array_map(fn($t) => $t->id, $tasks),
            'ssh_wifi_available' => $sshWifiCheck['has_passwords'],
            'preconfig_url' => $preconfigUrl,
        ]);

        return [
            'success' => true,
            'tasks' => $tasks,
            'message' => 'Migration tasks created. ' . count($tasks) . ' tasks queued. Pre-config will trigger TR-181 switch.',
            'warnings' => $warnings,
            'ssh_wifi_status' => $sshWifiCheck,
            'preconfig_url' => $preconfigUrl,
            'next_steps' => [
                'Pre-config file will be pushed to device',
                'Device will factory reset and reconnect with TR-181 data model',
                'WiFi settings will need to be restored from SSH-extracted config',
                'Monitor device for reconnection (may take 2-5 minutes after reset)',
            ],
        ];
    }

    /**
     * Get the URL for the pre-config file
     *
     * Uses external hay.net URL because files served from hayacs.hay.net fail
     * on Nokia devices with "file corrupted or unusable" error (9018).
     * The hay.net URL has been tested and works for TR-181 migration.
     */
    public function getPreconfigFileUrl(): string
    {
        // Use the external URL that's known to work with Nokia devices
        // Files from hayacs.hay.net fail, but hay.net works
        return self::PRECONFIG_EXTERNAL_URL;
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
     * Get TR-181 WiFi parameter mapping for SSH-extracted configs
     * Maps UCI interface names to TR-181 parameter paths
     */
    public function getSshToTr181WifiMapping(): array
    {
        return [
            // 5GHz Primary (ath0 -> SSID.1/Radio.1/AccessPoint.1)
            'ath0' => [
                'ssid_path' => 'Device.WiFi.SSID.1.SSID',
                'enable_path' => 'Device.WiFi.SSID.1.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled',
            ],
            // 2.4GHz Primary (ath1 -> SSID.2/Radio.2/AccessPoint.2)
            'ath1' => [
                'ssid_path' => 'Device.WiFi.SSID.2.SSID',
                'enable_path' => 'Device.WiFi.SSID.2.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.2.SSIDAdvertisementEnabled',
            ],
            // 5GHz Secondary (ath01)
            'ath01' => [
                'ssid_path' => 'Device.WiFi.SSID.3.SSID',
                'enable_path' => 'Device.WiFi.SSID.3.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.3.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.3.SSIDAdvertisementEnabled',
            ],
            // 2.4GHz Secondary (ath11)
            'ath11' => [
                'ssid_path' => 'Device.WiFi.SSID.4.SSID',
                'enable_path' => 'Device.WiFi.SSID.4.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.4.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.4.SSIDAdvertisementEnabled',
            ],
            // 5GHz Guest (ath03)
            'ath03' => [
                'ssid_path' => 'Device.WiFi.SSID.5.SSID',
                'enable_path' => 'Device.WiFi.SSID.5.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.5.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled',
            ],
            // 2.4GHz Guest (ath13)
            'ath13' => [
                'ssid_path' => 'Device.WiFi.SSID.6.SSID',
                'enable_path' => 'Device.WiFi.SSID.6.Enable',
                'passphrase_path' => 'Device.WiFi.AccessPoint.6.Security.KeyPassphrase',
                'hidden_path' => 'Device.WiFi.AccessPoint.6.SSIDAdvertisementEnabled',
            ],
        ];
    }

    /**
     * Create WiFi fallback tasks using SSH-extracted WiFi configs
     * This is more reliable than TR-069 backup because it has actual plaintext passwords
     *
     * @param Device $device The post-migration TR-181 device
     * @param Device|null $oldDevice The pre-migration TR-098 device (if different ID due to OUI change)
     * @return array
     */
    public function createWifiFallbackFromSshConfigs(Device $device, ?Device $oldDevice = null): array
    {
        // Look for SSH-extracted WiFi configs from the device (or old device if OUI changed)
        $sourceDevice = $oldDevice ?? $device;

        $wifiConfigs = DeviceWifiConfig::where('device_id', $sourceDevice->id)
            ->customerFacing()  // primary, secondary, guest - not backhaul
            ->enabled()
            ->whereNotNull('password_encrypted')
            ->get();

        if ($wifiConfigs->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No SSH-extracted WiFi configs with passwords found for device',
                'source_device_id' => $sourceDevice->id,
            ];
        }

        $mapping = $this->getSshToTr181WifiMapping();
        $parametersToSet = [];
        $networksRestored = [];

        foreach ($wifiConfigs as $config) {
            $interfaceName = $config->interface_name;

            if (!isset($mapping[$interfaceName])) {
                Log::debug("No TR-181 mapping for interface {$interfaceName}, skipping");
                continue;
            }

            $paths = $mapping[$interfaceName];
            $password = $config->getPassword();

            // Set SSID
            if (!empty($config->ssid)) {
                $parametersToSet[] = [
                    'name' => $paths['ssid_path'],
                    'value' => $config->ssid,
                    'type' => 'xsd:string',
                ];
            }

            // Set password
            if (!empty($password)) {
                $parametersToSet[] = [
                    'name' => $paths['passphrase_path'],
                    'value' => $password,
                    'type' => 'xsd:string',
                ];
            }

            // Set enabled state
            $parametersToSet[] = [
                'name' => $paths['enable_path'],
                'value' => $config->enabled ? 'true' : 'false',
                'type' => 'xsd:boolean',
            ];

            // Set hidden state (inverted - SSIDAdvertisementEnabled = !hidden)
            $parametersToSet[] = [
                'name' => $paths['hidden_path'],
                'value' => $config->hidden ? 'false' : 'true',
                'type' => 'xsd:boolean',
            ];

            $networksRestored[] = [
                'interface' => $interfaceName,
                'ssid' => $config->ssid,
                'band' => $config->band,
                'type' => $config->network_type,
            ];
        }

        if (empty($parametersToSet)) {
            return [
                'success' => false,
                'message' => 'No mappable WiFi parameters found in SSH configs',
            ];
        }

        // Create task to set all WiFi parameters
        $task = Task::create([
            'device_id' => $device->id,
            'type' => 'set_parameter_values',
            'status' => 'pending',
            'parameters' => [
                'parameters' => $parametersToSet,
                'purpose' => 'tr181_migration_wifi_fallback_ssh',
                'source_device_id' => $sourceDevice->id,
                'networks_restored' => $networksRestored,
            ],
            'created_at' => now(),
        ]);

        // Mark the SSH configs as migrated
        DeviceWifiConfig::where('device_id', $sourceDevice->id)
            ->whereIn('interface_name', array_column($networksRestored, 'interface'))
            ->update([
                'migrated_to_tr181' => true,
                'migrated_at' => now(),
            ]);

        Log::info("TR-181 WiFi fallback task created from SSH configs for device {$device->serial_number}", [
            'device_id' => $device->id,
            'source_device_id' => $sourceDevice->id,
            'task_id' => $task->id,
            'parameter_count' => count($parametersToSet),
            'networks_restored' => count($networksRestored),
        ]);

        return [
            'success' => true,
            'task' => $task,
            'message' => 'WiFi fallback task created from SSH-extracted configs',
            'parameter_count' => count($parametersToSet),
            'networks_restored' => $networksRestored,
        ];
    }

    /**
     * Check if device has SSH-extracted WiFi configs available for fallback
     *
     * @param Device $device
     * @return array{has_configs: bool, config_count: int, has_passwords: bool}
     */
    public function checkSshWifiConfigsAvailable(Device $device): array
    {
        $configs = $device->wifiConfigs()->customerFacing()->get();
        $withPasswords = $configs->filter(fn($c) => !empty($c->password_encrypted))->count();

        return [
            'has_configs' => $configs->count() > 0,
            'config_count' => $configs->count(),
            'has_passwords' => $withPasswords > 0,
            'password_count' => $withPasswords,
            'extraction_method' => $configs->first()?->extraction_method,
            'extracted_at' => $configs->first()?->extracted_at?->toDateTimeString(),
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
