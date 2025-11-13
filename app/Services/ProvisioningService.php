<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class ProvisioningService
{
    /**
     * Auto-provision a device based on rules
     */
    public function autoProvision(Device $device, array $informEvents): void
    {
        // Check if this is a bootstrap event (first connection)
        $isBootstrap = $this->hasBootstrapEvent($informEvents);

        if ($isBootstrap) {
            Log::info('Auto-provisioning new device', ['device_id' => $device->id]);
            $this->provisionNewDevice($device);
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

            Log::info('Created standard configuration task', [
                'device_id' => $device->id,
                'params' => array_keys($standardConfig),
            ]);
        }
    }

    /**
     * Get standard configuration based on data model
     */
    private function getStandardConfiguration(Device $device): array
    {
        $dataModel = $device->getDataModel();
        $config = [];

        if ($dataModel === 'TR-098') {
            $config = [
                // Enable periodic inform every 5 minutes
                'InternetGatewayDevice.ManagementServer.PeriodicInformEnable' => '1',
                'InternetGatewayDevice.ManagementServer.PeriodicInformInterval' => '300',

                // Enable NTP
                'InternetGatewayDevice.Time.Enable' => '1',
                'InternetGatewayDevice.Time.NTPServer1' => 'pool.ntp.org',
            ];
        } else { // TR-181
            $config = [
                // Enable periodic inform every 5 minutes
                'Device.ManagementServer.PeriodicInformEnable' => 'true',
                'Device.ManagementServer.PeriodicInformInterval' => '300',

                // Enable NTP
                'Device.Time.Enable' => 'true',
                'Device.Time.NTPServer1' => 'pool.ntp.org',
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
