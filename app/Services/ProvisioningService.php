<?php

namespace App\Services;

use App\Models\ConfigBackup;
use App\Models\Device;
use App\Models\SubscriberEquipment;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class ProvisioningService
{
    /**
     * Auto-provision a device based on rules
     */
    public function autoProvision(Device $device, array $informEvents): void
    {
        // Check if this is a bootstrap event (first connection or factory reset)
        $isBootstrap = $this->hasBootstrapEvent($informEvents);

        if ($isBootstrap) {
            // Check if device has existing backups older than 1 minute
            // If backup is very recent, it was just created - this is a new device
            // If backup is older, device had backups before - this is a factory reset
            $existingBackup = $device->configBackups()
                ->where('created_at', '<', now()->subMinute())
                ->latest()
                ->first();

            if ($existingBackup) {
                // Factory reset detected - restore from existing backup
                Log::info('Factory reset detected - restoring from backup', [
                    'device_id' => $device->id,
                    'backup_id' => $existingBackup->id,
                    'backup_name' => $existingBackup->name,
                ]);
                $this->restoreFromBackup($device, $existingBackup);
            } else {
                // New device - apply standard provisioning
                Log::info('Auto-provisioning new device', ['device_id' => $device->id]);
                $this->provisionNewDevice($device);
            }

            // Link device to subscriber if not already linked
            $this->linkToSubscriber($device);
        }

        // Apply manufacturer-specific provisioning
        $this->provisionByManufacturer($device);

        // Apply model-specific provisioning
        $this->provisionByModel($device);
    }

    /**
     * Check if Inform contains bootstrap event
     */
    private function hasBootstrapEvent(array $events): bool
    {
        foreach ($events as $event) {
            if (isset($event['code']) && str_contains($event['code'], 'BOOTSTRAP')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Provision a newly connected device
     */
    private function provisionNewDevice(Device $device): void
    {
        // Check if device already has a pending or sent standard config task
        $existingTask = Task::where('device_id', $device->id)
            ->where('task_type', 'set_params')
            ->whereIn('status', ['pending', 'sent'])
            ->exists();

        if ($existingTask) {
            Log::info('Skipping auto-provision - task already exists', [
                'device_id' => $device->id,
            ]);
            return;
        }

        // Standard configuration for all new devices
        $standardConfig = $this->getStandardConfiguration($device);

        if (!empty($standardConfig)) {
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'set_params',
                'parameters' => [
                    'values' => $standardConfig,
                ],
                'status' => 'pending',
            ]);

            // Store connection request credentials in database
            // Since we're setting them on the device, we know what they are
            $crUsername = config('cwmp.connection_request_username', 'admin');
            $crPassword = config('cwmp.connection_request_password', 'admin');
            $device->update([
                'connection_request_username' => $crUsername,
                'connection_request_password' => $crPassword,
            ]);

            Log::info('Created standard configuration task', [
                'device_id' => $device->id,
                'params' => array_keys($standardConfig),
            ]);
        }

        // Set initial device-specific password for Nokia Beacon G6 devices
        if ($device->isNokiaBeacon()) {
            $passwordTask = $device->setInitialPassword();
            if ($passwordTask) {
                Log::info('Created initial password task for Nokia Beacon', [
                    'device_id' => $device->id,
                    'task_id' => $passwordTask->id,
                    'password_format' => '{SerialNumber}_{Suffix}_stay$away',
                ]);
            }
        }
    }

    /**
     * Link device to subscriber based on serial number match
     */
    private function linkToSubscriber(Device $device): void
    {
        // Skip if already linked
        if ($device->subscriber_id) {
            return;
        }

        // Skip if no serial number
        if (empty($device->serial_number)) {
            return;
        }

        // Find matching equipment by serial number (case-insensitive)
        $equipment = SubscriberEquipment::whereRaw('LOWER(serial) = ?', [strtolower($device->serial_number)])
            ->first();

        if ($equipment && $equipment->subscriber_id) {
            $device->update(['subscriber_id' => $equipment->subscriber_id]);

            Log::info('Device linked to subscriber', [
                'device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'subscriber_id' => $equipment->subscriber_id,
            ]);
        }
    }

    /**
     * Restore device from a backup after factory reset
     */
    private function restoreFromBackup(Device $device, ConfigBackup $backup): void
    {
        // Check if there's already a pending restore task to avoid duplicates
        $existingRestoreTask = Task::where('device_id', $device->id)
            ->where('task_type', 'set_parameter_values')
            ->whereIn('status', ['pending', 'sent'])
            ->where('description', 'like', 'Auto-restore from backup%')
            ->exists();

        if ($existingRestoreTask) {
            Log::info('Skipping auto-restore - task already exists', [
                'device_id' => $device->id,
            ]);
            return;
        }

        // Filter parameters to only include writable ones
        $writableParams = collect($backup->backup_data)
            ->filter(function ($param, $name) {
                // Must be writable
                if (!($param['writable'] ?? false)) {
                    return false;
                }
                // Skip management server parameters (ACS URL, credentials, etc.)
                // These should stay at factory defaults to maintain ACS connectivity
                if (str_contains($name, 'ManagementServer.URL') ||
                    str_contains($name, 'ManagementServer.Username') ||
                    str_contains($name, 'ManagementServer.Password')) {
                    return false;
                }
                return true;
            })
            ->mapWithKeys(function ($param, $name) {
                return [$name => [
                    'value' => $param['value'],
                    'type' => $param['type'] ?? 'xsd:string',
                ]];
            })
            ->toArray();

        if (empty($writableParams)) {
            Log::warning('No writable parameters found in backup for auto-restore', [
                'device_id' => $device->id,
                'backup_id' => $backup->id,
            ]);
            return;
        }

        // Create task to restore the parameters
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_parameter_values',
            'description' => 'Auto-restore from backup: ' . $backup->name,
            'status' => 'pending',
            'parameters' => $writableParams,
        ]);

        Log::info('Created auto-restore task after factory reset', [
            'device_id' => $device->id,
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
            'task_id' => $task->id,
            'writable_params_count' => count($writableParams),
            'total_params_in_backup' => count($backup->backup_data),
        ]);
    }

    /**
     * Get standard configuration based on data model
     * Returns parameters with proper TR-069 types for SetParameterValues RPC
     */
    private function getStandardConfiguration(Device $device): array
    {
        $dataModel = $device->getDataModel();
        $config = [];

        // Connection request credentials - allows ACS to initiate connections
        $crUsername = config('cwmp.connection_request_username', 'admin');
        $crPassword = config('cwmp.connection_request_password', 'admin');

        // Check if this is a Nokia/Alcatel-Lucent device using centralized detection
        $isNokia = $device->isNokia();

        if ($dataModel === 'TR-098') {
            // Base TR-098 config - management server settings
            // Must include proper types for strict devices like Calix 844E
            $config = [
                // Enable periodic inform every 15 minutes
                'InternetGatewayDevice.ManagementServer.PeriodicInformEnable' => [
                    'value' => '1',
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.ManagementServer.PeriodicInformInterval' => [
                    'value' => '900',
                    'type' => 'xsd:unsignedInt',
                ],

                // Set connection request credentials (strings)
                'InternetGatewayDevice.ManagementServer.ConnectionRequestUsername' => [
                    'value' => $crUsername,
                    'type' => 'xsd:string',
                ],
                'InternetGatewayDevice.ManagementServer.ConnectionRequestPassword' => [
                    'value' => $crPassword,
                    'type' => 'xsd:string',
                ],
            ];

            // Nokia Beacon G6 TR-098 uses different time parameter paths
            if ($isNokia) {
                // Nokia uses InternetGatewayDevice.Time.LocalTimeZoneName for timezone
                // and InternetGatewayDevice.Time.NTPServer1 exists but as a different path
                // The Time.Enable parameter doesn't exist on Nokia - skip NTP config
                // Nokia devices handle NTP automatically
                Log::info('Nokia TR-098 device detected - skipping NTP configuration', [
                    'device_id' => $device->id,
                ]);
            } else {
                // Standard TR-098 NTP configuration
                $config['InternetGatewayDevice.Time.Enable'] = [
                    'value' => '1',
                    'type' => 'xsd:boolean',
                ];
                $config['InternetGatewayDevice.Time.NTPServer1'] = [
                    'value' => 'ntp.hay.net',
                    'type' => 'xsd:string',
                ];
            }
        } else { // TR-181
            $config = [
                // Enable periodic inform every 15 minutes
                'Device.ManagementServer.PeriodicInformEnable' => [
                    'value' => 'true',
                    'type' => 'xsd:boolean',
                ],
                'Device.ManagementServer.PeriodicInformInterval' => [
                    'value' => '900',
                    'type' => 'xsd:unsignedInt',
                ],

                // Set connection request credentials (strings)
                'Device.ManagementServer.ConnectionRequestUsername' => [
                    'value' => $crUsername,
                    'type' => 'xsd:string',
                ],
                'Device.ManagementServer.ConnectionRequestPassword' => [
                    'value' => $crPassword,
                    'type' => 'xsd:string',
                ],

                // NTP configuration
                'Device.Time.Enable' => [
                    'value' => 'true',
                    'type' => 'xsd:boolean',
                ],
                'Device.Time.NTPServer1' => [
                    'value' => 'ntp.hay.net',
                    'type' => 'xsd:string',
                ],
            ];
        }

        return $config;
    }

    /**
     * Apply manufacturer-specific provisioning
     */
    private function provisionByManufacturer(Device $device): void
    {
        $rules = $this->getManufacturerRules($device->manufacturer);

        if (!empty($rules)) {
            $this->applyProvisioningRules($device, $rules);
        }
    }

    /**
     * Apply model-specific provisioning
     */
    private function provisionByModel(Device $device): void
    {
        $rules = $this->getModelRules($device->product_class);

        if (!empty($rules)) {
            $this->applyProvisioningRules($device, $rules);
        }
    }

    /**
     * Get provisioning rules for a manufacturer
     *
     * You can customize this method or load rules from database/config
     */
    private function getManufacturerRules(?string $manufacturer): array
    {
        if (!$manufacturer) {
            return [];
        }

        // Example manufacturer-specific rules
        return match (strtolower($manufacturer)) {
            'acme' => [
                'type' => 'set_params',
                'parameters' => [
                    'values' => [
                        // Acme-specific settings
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * Get provisioning rules for a model
     *
     * You can customize this method or load rules from database/config
     */
    private function getModelRules(?string $model): array
    {
        if (!$model) {
            return [];
        }

        // Example model-specific rules
        return match (strtolower($model)) {
            'homerouter' => [
                'type' => 'set_params',
                'parameters' => [
                    'values' => [
                        // Set default WiFi SSID based on serial number
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * Apply provisioning rules to a device
     */
    private function applyProvisioningRules(Device $device, array $rules): void
    {
        if (empty($rules)) {
            return;
        }

        // Check if a similar task already exists to avoid duplicates
        $existingTask = Task::where('device_id', $device->id)
            ->where('task_type', $rules['type'])
            ->where('status', 'pending')
            ->first();

        if ($existingTask) {
            return;
        }

        Task::create([
            'device_id' => $device->id,
            'task_type' => $rules['type'],
            'parameters' => $rules['parameters'],
            'status' => 'pending',
        ]);

        Log::info('Applied provisioning rules', [
            'device_id' => $device->id,
            'rule_type' => $rules['type'],
        ]);
    }

    /**
     * Provision device by tags
     * Useful for location-based or group-based provisioning
     */
    public function provisionByTags(Device $device): void
    {
        if (empty($device->tags)) {
            return;
        }

        foreach ($device->tags as $tag) {
            $rules = $this->getTagRules($tag);
            if (!empty($rules)) {
                $this->applyProvisioningRules($device, $rules);
            }
        }
    }

    /**
     * Get provisioning rules for a tag
     */
    private function getTagRules(string $tag): array
    {
        // Example tag-based rules (e.g., location, environment)
        return match (strtolower($tag)) {
            'office' => [
                'type' => 'set_params',
                'parameters' => [
                    'values' => [
                        // Office-specific WiFi settings
                    ],
                ],
            ],
            'warehouse' => [
                'type' => 'set_params',
                'parameters' => [
                    'values' => [
                        // Warehouse-specific settings
                    ],
                ],
            ],
            default => [],
        };
    }
}
