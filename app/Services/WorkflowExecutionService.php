<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Firmware;
use App\Models\GroupWorkflow;
use App\Models\Parameter;
use App\Models\Task;
use App\Models\WorkflowExecution;
use App\Models\WorkflowLog;
use App\Services\Tr181MigrationService;
use App\Services\NokiaSshService;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionService
{
    /**
     * Execute a workflow for a specific device
     */
    public function executeForDevice(GroupWorkflow $workflow, Device $device): ?Task
    {
        // Get or create execution record
        $execution = WorkflowExecution::firstOrCreate(
            [
                'group_workflow_id' => $workflow->id,
                'device_id' => $device->id,
            ],
            [
                'status' => 'pending',
                'scheduled_at' => now(),
            ]
        );

        // Check if already processed
        if (in_array($execution->status, ['completed', 'in_progress', 'queued'])) {
            return null;
        }

        // Check dependency
        if ($workflow->depends_on_workflow_id) {
            $dependencyMet = WorkflowExecution::where('group_workflow_id', $workflow->depends_on_workflow_id)
                ->where('device_id', $device->id)
                ->where('status', 'completed')
                ->exists();

            if (!$dependencyMet) {
                $execution->markSkipped('Dependency workflow not completed');
                WorkflowLog::warning(
                    $workflow->id,
                    "Skipped: dependency workflow {$workflow->depends_on_workflow_id} not completed",
                    $execution->id,
                    $device->id
                );
                return null;
            }
        }

        // Create the task
        $task = $this->createTaskForWorkflow($workflow, $device);

        if (!$task) {
            $execution->markFailed(['error' => 'Failed to create task']);
            WorkflowLog::error(
                $workflow->id,
                'Failed to create task for device',
                $execution->id,
                $device->id
            );
            return null;
        }

        // Update execution with task reference
        $execution->markQueued($task);

        WorkflowLog::info(
            $workflow->id,
            "Created task {$task->id} for device",
            $execution->id,
            $device->id,
            ['task_type' => $workflow->task_type]
        );

        // If task is already completed (e.g., cached backup), trigger completion callback
        if ($task->status === 'completed') {
            $this->onTaskCompleted($task);
        }

        return $task;
    }

    /**
     * Create a Task based on workflow configuration
     */
    private function createTaskForWorkflow(GroupWorkflow $workflow, Device $device): ?Task
    {
        // Build parameters based on task type
        $parameters = $this->buildTaskParameters($workflow, $device);

        // Handle special case: transition_backup with use_cached_data
        // This creates the backup immediately from cached database data
        if (!empty($parameters['use_cached_data']) && $workflow->task_type === 'transition_backup') {
            return $this->createCachedBackupTask($workflow, $device, $parameters);
        }

        // Handle special case: extract_wifi_ssh is a server-side task, not TR-069
        // Execute SSH extraction immediately and create a completed task record
        if ($workflow->task_type === 'extract_wifi_ssh') {
            return $this->executeExtractWifiSsh($workflow, $device);
        }

        // For task types that require parameters, fail if parameters are null
        $requiresParams = ['firmware_upgrade', 'set_parameter_values', 'get_parameter_values', 'download', 'upload', 'restore'];
        if (in_array($workflow->task_type, $requiresParams) && $parameters === null) {
            Log::error('Task creation failed: required parameters are null', [
                'workflow_id' => $workflow->id,
                'device_id' => $device->id,
                'task_type' => $workflow->task_type,
            ]);
            return null;
        }

        $taskData = [
            'device_id' => $device->id,
            'task_type' => $this->mapTaskType($workflow->task_type),
            'status' => 'pending',
            'description' => "Workflow: {$workflow->name}",
        ];

        if ($parameters !== null) {
            $taskData['parameters'] = $parameters;
        }

        return Task::create($taskData);
    }

    /**
     * Execute SSH WiFi extraction - this runs immediately on the server side
     * Uses Tr181MigrationService to SSH into the device and extract WiFi config
     */
    private function executeExtractWifiSsh(GroupWorkflow $workflow, Device $device): ?Task
    {
        Log::info('Executing SSH WiFi extraction', [
            'device_id' => $device->id,
            'device_serial' => $device->serial_number,
            'workflow_id' => $workflow->id,
        ]);

        // Create a task record to track the operation
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'extract_wifi_ssh',
            'status' => 'in_progress',
            'description' => "Workflow: {$workflow->name}",
            'parameters' => [
                'migration_step' => 'extract_wifi_ssh',
                'purpose' => 'Extract WiFi config with plaintext passwords via SSH',
            ],
        ]);

        try {
            // Use NokiaSshService to extract WiFi via SSH
            // Returns array of DeviceWifiConfig models on success, throws exception on failure
            $sshService = app(NokiaSshService::class);
            $savedConfigs = $sshService->extractWifiConfig($device);

            // extractWifiConfig returns saved configs (or throws exception on failure)
            $configCount = count($savedConfigs);

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => [
                    'success' => true,
                    'message' => "Extracted {$configCount} WiFi configurations via SSH",
                    'configs_count' => $configCount,
                ],
            ]);

            Log::info('SSH WiFi extraction completed', [
                'device_id' => $device->id,
                'configs_extracted' => $configCount,
                'task_id' => $task->id,
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SSH WiFi extraction exception', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
                'task_id' => $task->id,
            ]);
        }

        return $task;
    }

    /**
     * Create a backup task using cached database data (no device query needed)
     * Used for Nokia TR-098 devices that don't support multi-param GetParameterValues
     */
    private function createCachedBackupTask(GroupWorkflow $workflow, Device $device, array $parameters): ?Task
    {
        // Query WiFi parameters from database
        $patterns = $parameters['wifi_param_patterns'] ?? ['%WLANConfiguration%'];

        $query = Parameter::where('device_id', $device->id);
        $query->where(function($q) use ($patterns) {
            foreach ($patterns as $pattern) {
                $q->orWhere('name', 'like', $pattern);
            }
        });

        $wifiParams = $query->get(['name', 'value', 'type', 'writable']);

        if ($wifiParams->isEmpty()) {
            Log::error('No WiFi parameters found in database for cached backup', [
                'device_id' => $device->id,
                'patterns' => $patterns,
            ]);
            return null;
        }

        // Convert to backup format
        $backupData = [];
        foreach ($wifiParams as $param) {
            $backupData[$param->name] = [
                'value' => $param->value,
                'type' => $param->type,
                'writable' => $param->writable,
            ];
        }

        // Create the config backup
        $backup = $device->configBackups()->create([
            'name' => 'Corteca Transition Backup',
            'description' => 'corteca_transition - WiFi parameters backed up before TR-181 migration (from cached data)',
            'backup_data' => $backupData,
            'parameter_count' => count($backupData),
            'type' => 'corteca_transition',
        ]);

        Log::info('Created Corteca transition backup from cached data', [
            'device_id' => $device->id,
            'backup_id' => $backup->id,
            'param_count' => count($backupData),
        ]);

        // Create a "virtual" task that's already completed
        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_parameter_values',
            'status' => 'completed',
            'description' => "Workflow: {$workflow->name} (from cached data)",
            'parameters' => $parameters,
            'result' => [
                'source' => 'cached_data',
                'backup_id' => $backup->id,
                'param_count' => count($backupData),
            ],
            'completed_at' => now(),
        ]);

        return $task;
    }

    /**
     * Map workflow task type to Task model task_type
     */
    private function mapTaskType(string $workflowTaskType): string
    {
        return match ($workflowTaskType) {
            'firmware_upgrade' => 'download',
            'set_parameter_values' => 'set_parameter_values',
            'get_parameter_values' => 'get_parameter_values',
            'reboot' => 'reboot',
            'factory_reset' => 'factory_reset',
            'backup' => 'get_parameter_values', // Backup is a get_parameter_values with processing
            'restore' => 'set_parameter_values',
            'download' => 'download',
            'upload' => 'upload',
            // TR-181 migration tasks
            'version_check' => 'get_parameter_values', // Gets version, workflow decides next step
            'datamodel_check' => 'get_parameter_values', // Gets data model indicator
            'transition_backup' => 'get_parameter_values', // Full backup before migration
            'extract_wifi_ssh' => 'extract_wifi_ssh', // Extract WiFi via SSH (non-TR-069 task)
            'hayacs_tr181_preconfig' => 'download', // Push HayACS TR-181 pre-config file
            'wifi_restore_ssh' => 'set_parameter_values', // Restore WiFi from SSH-extracted config
            'combined_restore' => 'set_parameter_values', // Combined: WiFi SSH + TR-069 backup params
            // Legacy Corteca tasks
            'corteca_preconfig' => 'download', // Push pre-config file (custom URL)
            'corteca_restore' => 'set_parameter_values', // Restore converted settings
            default => $workflowTaskType,
        };
    }

    /**
     * Build task parameters based on workflow configuration
     */
    private function buildTaskParameters(GroupWorkflow $workflow, Device $device): ?array
    {
        $config = $workflow->task_parameters ?? [];

        return match ($workflow->task_type) {
            'firmware_upgrade' => $this->buildFirmwareUpgradeParams($config, $device),
            'set_parameter_values' => $this->buildSetParamsParams($config, $device),
            'get_parameter_values' => $this->buildGetParamsParams($config),
            'download' => $this->buildDownloadParams($config),
            'upload' => $this->buildUploadParams($config),
            'reboot', 'factory_reset' => null,
            'backup' => $this->buildBackupParams($device),
            'restore' => $this->buildRestoreParams($config, $device),
            // TR-181 migration tasks
            'version_check' => $this->buildVersionCheckParams($device),
            'datamodel_check' => $this->buildDatamodelCheckParams($device),
            'transition_backup' => $this->buildTransitionBackupParams($device),
            'extract_wifi_ssh' => $this->buildExtractWifiSshParams($device),
            'hayacs_tr181_preconfig' => $this->buildHayacsTr181PreconfigParams($config, $device),
            'wifi_restore_ssh' => $this->buildWifiRestoreSshParams($device),
            'combined_restore' => $this->buildCombinedRestoreParams($device),
            // Legacy Corteca tasks
            'corteca_preconfig' => $this->buildCortecaPreconfigParams($config, $device),
            'corteca_restore' => $this->buildCortecaRestoreParams($config, $device),
            default => $config,
        };
    }

    /**
     * Build firmware upgrade parameters
     */
    private function buildFirmwareUpgradeParams(array $config, Device $device): ?array
    {
        $firmware = null;

        // Check if using active firmware for the device type
        if (!empty($config['use_active_firmware']) && $config['use_active_firmware'] === '1') {
            $deviceTypeId = $config['device_type_id'] ?? null;

            if ($deviceTypeId) {
                // Get the active firmware for this device type
                $firmware = Firmware::where('device_type_id', $deviceTypeId)
                    ->where('is_active', true)
                    ->first();

                if (!$firmware) {
                    Log::warning('No active firmware found for device type', [
                        'device_type_id' => $deviceTypeId,
                        'device_id' => $device->id,
                    ]);
                    return null;
                }

                Log::info('Using active firmware for device type', [
                    'device_type_id' => $deviceTypeId,
                    'firmware_id' => $firmware->id,
                    'firmware_version' => $firmware->version,
                ]);
            } else {
                Log::warning('use_active_firmware set but no device_type_id provided', ['config' => $config]);
                return null;
            }
        } else {
            // Use specific firmware ID
            $firmwareId = $config['firmware_id'] ?? null;

            if (!$firmwareId) {
                Log::warning('Firmware upgrade workflow missing firmware_id', ['config' => $config]);
                return null;
            }

            $firmware = Firmware::find($firmwareId);
            if (!$firmware) {
                Log::warning('Firmware not found', ['firmware_id' => $firmwareId]);
                return null;
            }
        }

        // Build download URL
        $downloadUrl = $firmware->getFullDownloadUrl();

        return [
            'file_type' => $config['file_type'] ?? '1 Firmware Upgrade Image',
            'url' => $downloadUrl,
            'file_size' => $firmware->file_size,
            'target_filename' => $firmware->file_name,
        ];
    }

    /**
     * Build set parameter values parameters
     */
    private function buildSetParamsParams(array $config, Device $device): array
    {
        $values = $config['values'] ?? $config;

        // Support variable substitution
        $processed = [];
        foreach ($values as $name => $value) {
            $processed[$name] = $this->substituteVariables($value, $device);
        }

        return $processed;
    }

    /**
     * Build get parameter values parameters
     */
    private function buildGetParamsParams(array $config): array
    {
        return [
            'names' => $config['names'] ?? $config['parameters'] ?? [],
        ];
    }

    /**
     * Build download parameters
     */
    private function buildDownloadParams(array $config): array
    {
        return [
            'file_type' => $config['file_type'] ?? '3 Vendor Configuration File',
            'url' => $config['url'] ?? '',
            'file_size' => $config['file_size'] ?? 0,
            'target_filename' => $config['target_filename'] ?? '',
        ];
    }

    /**
     * Build upload parameters
     */
    private function buildUploadParams(array $config): array
    {
        return [
            'file_type' => $config['file_type'] ?? '1 Configuration File',
            'url' => $config['url'] ?? '',
        ];
    }

    /**
     * Build backup parameters (get all parameters)
     */
    private function buildBackupParams(Device $device): array
    {
        $dataModel = $device->getDataModel();
        $rootPath = $dataModel === 'TR-181' ? 'Device.' : 'InternetGatewayDevice.';

        return [
            'partial_path' => $rootPath,
            'is_backup' => true,
        ];
    }

    /**
     * Build restore parameters from backup
     */
    private function buildRestoreParams(array $config, Device $device): ?array
    {
        $backupId = $config['backup_id'] ?? null;

        if ($backupId) {
            $backup = $device->configBackups()->find($backupId);
        } else {
            // Use latest backup
            $backup = $device->configBackups()->latest()->first();
        }

        if (!$backup) {
            Log::warning('No backup found for restore', ['device_id' => $device->id]);
            return null;
        }

        // Filter to writable parameters, excluding management server
        $params = collect($backup->backup_data)
            ->filter(function ($param, $name) {
                if (!($param['writable'] ?? false)) {
                    return false;
                }
                if (str_contains($name, 'ManagementServer.URL') ||
                    str_contains($name, 'ManagementServer.Username') ||
                    str_contains($name, 'ManagementServer.Password')) {
                    return false;
                }
                return true;
            })
            ->mapWithKeys(fn($param, $name) => [$name => $param['value']])
            ->toArray();

        return $params;
    }

    /**
     * Substitute variables in parameter values
     */
    private function substituteVariables(string $value, Device $device): string
    {
        $replacements = [
            '{serial_number}' => $device->serial_number ?? '',
            '{oui}' => $device->oui ?? '',
            '{product_class}' => $device->product_class ?? '',
            '{manufacturer}' => $device->manufacturer ?? '',
            '{ip_address}' => $device->ip_address ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    /**
     * Update execution status based on task completion
     */
    public function onTaskCompleted(Task $task): void
    {
        $execution = WorkflowExecution::where('task_id', $task->id)->first();

        if (!$execution) {
            return;
        }

        if ($task->status === 'completed') {
            $execution->markCompleted([
                'task_id' => $task->id,
                'completed_at' => $task->completed_at,
            ]);

            WorkflowLog::info(
                $execution->group_workflow_id,
                'Task completed successfully',
                $execution->id,
                $execution->device_id,
                ['task_id' => $task->id]
            );

            // Check if workflow is complete
            $this->checkWorkflowCompletion($execution->workflow);

            // Trigger next workflow step if there's a dependent workflow waiting
            $this->triggerNextWorkflowStep($execution);
        } elseif ($task->status === 'failed') {
            $execution->markFailed([
                'task_id' => $task->id,
                'error' => $task->error_message ?? 'Task failed',
            ]);

            WorkflowLog::error(
                $execution->group_workflow_id,
                'Task failed: ' . ($task->error_message ?? 'Unknown error'),
                $execution->id,
                $execution->device_id,
                ['task_id' => $task->id]
            );

            // Check failure threshold
            $this->checkFailureThreshold($execution->workflow);
        }
    }

    /**
     * Trigger the next workflow step by sending a connection request
     * This keeps the workflow chain moving without waiting for periodic informs
     */
    private function triggerNextWorkflowStep(WorkflowExecution $execution): void
    {
        $completedWorkflow = $execution->workflow;
        $device = Device::find($execution->device_id);

        if (!$device) {
            return;
        }

        // Find workflows that depend on the completed workflow
        $dependentWorkflows = GroupWorkflow::where('depends_on_workflow_id', $completedWorkflow->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->get();

        if ($dependentWorkflows->isEmpty()) {
            Log::debug('No dependent workflows waiting', [
                'completed_workflow_id' => $completedWorkflow->id,
                'device_id' => $device->id,
            ]);
            return;
        }

        // Check if any dependent workflow can now run for this device
        foreach ($dependentWorkflows as $nextWorkflow) {
            if ($nextWorkflow->canRunForDevice($device)) {
                Log::info('Triggering next workflow step via connection request', [
                    'completed_workflow' => $completedWorkflow->name,
                    'next_workflow' => $nextWorkflow->name,
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                ]);

                // Short delay to allow the current session to fully close
                // Use a dispatch after delay if possible, or send immediately
                $connectionService = app(ConnectionRequestService::class);
                $result = $connectionService->sendConnectionRequest($device);

                WorkflowLog::info(
                    $nextWorkflow->id,
                    'Connection request sent to trigger workflow: ' . ($result['success'] ? 'success' : 'failed'),
                    null,
                    $device->id,
                    ['result' => $result['message'] ?? '']
                );

                // Only send one connection request even if multiple workflows are waiting
                // The next connect will process all eligible workflows
                break;
            }
        }
    }

    /**
     * Check if workflow has completed all executions
     */
    private function checkWorkflowCompletion(GroupWorkflow $workflow): void
    {
        $stats = $workflow->getStats();

        $pending = $stats['pending'] + $stats['queued'] + $stats['in_progress'];

        if ($pending === 0 && $stats['total'] > 0) {
            $workflow->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            WorkflowLog::info(
                $workflow->id,
                "Workflow completed: {$stats['completed']} succeeded, {$stats['failed']} failed, {$stats['skipped']} skipped"
            );
        }
    }

    /**
     * Check if failure threshold exceeded
     */
    private function checkFailureThreshold(GroupWorkflow $workflow): void
    {
        if ($workflow->stop_on_failure_percent <= 0) {
            return;
        }

        $stats = $workflow->getStats();
        $completed = $stats['completed'] + $stats['failed'];

        if ($completed === 0) {
            return;
        }

        $failurePercent = ($stats['failed'] / $completed) * 100;

        if ($failurePercent >= $workflow->stop_on_failure_percent) {
            $workflow->update(['status' => 'paused']);

            WorkflowLog::warning(
                $workflow->id,
                "Workflow paused: failure rate {$failurePercent}% exceeds threshold {$workflow->stop_on_failure_percent}%",
                null,
                null,
                ['stats' => $stats]
            );
        }
    }

    // =========================================================================
    // Corteca Migration Task Builders
    // =========================================================================

    /**
     * Build version check parameters - gets software version for comparison
     */
    private function buildVersionCheckParams(Device $device): array
    {
        $dataModel = $device->getDataModel();
        $versionParam = $dataModel === 'TR-181'
            ? 'Device.DeviceInfo.SoftwareVersion'
            : 'InternetGatewayDevice.DeviceInfo.SoftwareVersion';

        return [
            'names' => [$versionParam],
            'corteca_migration_step' => 'version_check',
            'required_version' => '24.02a', // Minimum version for Corteca
        ];
    }

    /**
     * Build datamodel check parameters - determines if device is TR-098 or TR-181
     * Uses the device's known data model to query the appropriate parameter
     */
    private function buildDatamodelCheckParams(Device $device): array
    {
        // Query the parameter appropriate for the device's current data model
        // This confirms the device is still on the expected model before migration
        $dataModel = $device->getDataModel();
        $versionParam = $dataModel === 'TR-181'
            ? 'Device.DeviceInfo.SoftwareVersion'
            : 'InternetGatewayDevice.DeviceInfo.SoftwareVersion';

        return [
            'names' => [$versionParam],
            'corteca_migration_step' => 'datamodel_check',
            'expected_datamodel' => $dataModel,
        ];
    }

    /**
     * Build transition backup parameters - WiFi-focused backup before migration
     *
     * NOTE: Nokia Beacon G6 TR-098 devices do NOT support GetParameterValues
     * with multiple parameters reliably. Instead of querying the device,
     * we use the parameters already cached in our database from "Get Everything".
     *
     * This approach:
     * 1. Is instant (no device query needed)
     * 2. Always works (doesn't depend on device quirks)
     * 3. Uses data we already have from regular device polling
     */
    private function buildTransitionBackupParams(Device $device): array
    {
        $dataModel = $device->getDataModel();

        if ($dataModel === 'TR-181') {
            // TR-181 devices support partial paths - query fresh data
            return [
                'partial_path' => 'Device.',
                'is_backup' => true,
                'corteca_migration_step' => 'transition_backup',
                'backup_type' => 'corteca_transition',
            ];
        }

        // TR-098 Nokia devices: Use existing cached parameters instead of querying
        // This is a "local backup" operation - we mark it specially so the handler
        // knows to save from database instead of waiting for device response
        return [
            'use_cached_data' => true,
            'corteca_migration_step' => 'transition_backup',
            'backup_type' => 'corteca_transition',
            'wifi_param_patterns' => [
                '%WLANConfiguration%',
                '%X_ALU-COM_Wifi%',
                '%X_ALU-COM_BandSteering%',
                '%X_ALU-COM_WifiSchedule%',
                '%X_ALU-COM_NokiaParentalControl%',
            ],
        ];
    }

    /**
     * Get WiFi parameters that actually exist on this device from our database
     */
    private function getDeviceWifiParams(Device $device): array
    {
        return Parameter::where('device_id', $device->id)
            ->where(function($q) {
                $q->where('name', 'like', '%WLANConfiguration.1.%')
                  ->orWhere('name', 'like', '%WLANConfiguration.2.%')
                  ->orWhere('name', 'like', '%WLANConfiguration.5.%')
                  ->orWhere('name', 'like', '%WLANConfiguration.6.%')
                  ->orWhere('name', 'like', '%X_ALU-COM_Wifi%')
                  ->orWhere('name', 'like', '%X_ALU-COM_BandSteering%')
                  ->orWhere('name', 'like', '%X_ALU-COM_WifiSchedule%')
                  ->orWhere('name', 'like', '%X_ALU-COM_NokiaParentalControl%');
            })
            ->where(function($q) {
                // Only essential config parameters (not stats, not AC categories)
                $q->where('name', 'like', '%.SSID')
                  ->orWhere('name', 'like', '%.Enable')
                  ->orWhere('name', 'like', '%.KeyPassphrase')
                  ->orWhere('name', 'like', '%.PreSharedKey')
                  ->orWhere('name', 'like', '%.Channel')
                  ->orWhere('name', 'like', '%.BeaconType')
                  ->orWhere('name', 'like', '%.SSIDAdvertisementEnabled')
                  ->orWhere('name', 'like', '%.RadioEnabled')
                  ->orWhere('name', 'like', '%SteeringEnable')
                  ->orWhere('name', 'like', '%MLOEnable');
            })
            // Skip stats and internal params that can't be restored
            ->where('name', 'not like', '%.Stats.%')
            ->where('name', 'not like', '%.AC.%')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->toArray();
    }

    /**
     * Get the list of WiFi parameters to backup from Nokia Beacon G6 TR-098 devices
     * These are the critical parameters needed to restore WiFi after Corteca migration
     */
    private function getNokiaWifiBackupParams(): array
    {
        return [
            // 2.4GHz Primary WiFi (WLANConfiguration.1)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.RadioEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.IEEE11iEncryptionModes',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.IEEE11iAuthenticationMode',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WPAAuthenticationMode',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.WPAEncryptionModes',

            // 2.4GHz Guest/Secondary (WLANConfiguration.2)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.RadioEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.BeaconType',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.PreSharedKey',

            // 5GHz Primary WiFi (WLANConfiguration.5)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.RadioEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.BeaconType',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.PreSharedKey',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.IEEE11iEncryptionModes',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.IEEE11iAuthenticationMode',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.WPAAuthenticationMode',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.WPAEncryptionModes',

            // 5GHz Guest/Secondary (WLANConfiguration.6)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.RadioEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.PreSharedKey.1.PreSharedKey',

            // Nokia-specific WiFi settings
            'InternetGatewayDevice.X_ALU-COM_Wifi.SteeringEnable',
            'InternetGatewayDevice.X_ALU-COM_Wifi.DynamicBHEnable',
            'InternetGatewayDevice.X_ALU-COM_Wifi.MLOEnable',
            'InternetGatewayDevice.X_ALU-COM_Wifi.X_ALU-COM_BOENGAgent.Enable',
            'InternetGatewayDevice.X_ALU-COM_Wifi.X_ALU-COM_NokiaWiFi.Enable',
            'InternetGatewayDevice.X_ALU-COM_Wifi.X_ALU-COM_MESH_WDSHideSSID.HideSSID',

            // Band steering settings
            'InternetGatewayDevice.X_ALU-COM_BandSteering.Enable',
            'InternetGatewayDevice.X_ALU-COM_BandSteering.SteerLegacy',

            // WiFi schedule
            'InternetGatewayDevice.X_ALU-COM_WifiSchedule.Enable',
            'InternetGatewayDevice.X_ALU-COM_WifiSchedule.StartTime',
            'InternetGatewayDevice.X_ALU-COM_WifiSchedule.EndTime',

            // Parental controls (if configured)
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Enable',
        ];
    }

    /**
     * Build ConfigMigration flag parameters - CRITICAL for preserving WiFi settings
     * This MUST be set BEFORE pushing the pre-config file!
     */
    private function buildConfigMigrationParams(Device $device): array
    {
        // Nokia's hidden parameter that preserves WiFi middleware database during migration
        // Without this, all WiFi settings will be reset to factory defaults!
        return [
            'InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration' => '1',
        ];
    }

    /**
     * Build parameters for SSH WiFi extraction
     * This is a non-TR-069 task - it runs SSH commands directly
     */
    private function buildExtractWifiSshParams(Device $device): array
    {
        return [
            'device_id' => $device->id,
            'migration_step' => 'extract_wifi_ssh',
            'purpose' => 'Extract WiFi config with plaintext passwords via SSH before TR-181 migration',
        ];
    }

    /**
     * Build HayACS TR-181 pre-config parameters - uses the correct pre-config URL
     * Updated to use simpler PREEGEBOPR file from hay.net (only changes OperatorID to EGEB)
     * Device keeps same OUI - no device ID change!
     */
    private function buildHayacsTr181PreconfigParams(array $config, Device $device): array
    {
        // Use the hay.net pre-config URL - hayacs.hay.net fails with "file corrupted" error
        // New simpler file only sets OperatorID=EGEB to trigger TR-181 mode
        $preconfigUrl = $config['preconfig_url'] ?? Tr181MigrationService::PRECONFIG_EXTERNAL_URL;

        return [
            'file_type' => '3 Vendor Configuration File',
            'url' => $preconfigUrl,
            'file_size' => $config['file_size'] ?? 0,
            'migration_step' => 'hayacs_tr181_preconfig',
            'purpose' => 'TR-181 migration pre-config - sets OperatorID=EGEB to trigger data model switch',
        ];
    }

    /**
     * Build WiFi restore parameters from SSH-extracted config
     * Uses the Tr181MigrationService to get SSH WiFi configs and map to TR-181 parameters
     */
    private function buildWifiRestoreSshParams(Device $device): ?array
    {
        $migrationService = app(Tr181MigrationService::class);
        $result = $migrationService->createWifiFallbackFromSshConfigs($device);

        if (!$result['success']) {
            Log::warning('No SSH WiFi configs available for restore', [
                'device_id' => $device->id,
                'message' => $result['message'],
            ]);
            return null;
        }

        // Return the parameters that were set on the task
        return $result['task']->parameters ?? null;
    }

    /**
     * Build combined restore parameters - merges WiFi from SSH extraction with
     * other settings from TR-069 backup (DHCP, port mappings, parental controls, time, etc.)
     *
     * This is the recommended post-migration restore method for Nokia Beacon G6:
     * - WiFi passwords come from SSH extraction (plaintext, not masked)
     * - DHCP, NAT, parental controls, time settings come from TR-069 backup
     * - Both are converted to TR-181 format and merged
     *
     * @param Device $device The post-migration TR-181 device
     * @return array|null The combined parameters to set
     */
    private function buildCombinedRestoreParams(Device $device): ?array
    {
        $parametersToSet = [];
        $sources = [];

        // Part 1: Get WiFi parameters from SSH-extracted configs (with plaintext passwords)
        $migrationService = app(Tr181MigrationService::class);

        // Check if this device has a pre-migration record (OUI may have changed)
        $previousDevice = $migrationService->findPreviousDeviceRecord($device);
        $sourceDevice = $previousDevice ?? $device;

        $wifiConfigs = \App\Models\DeviceWifiConfig::where('device_id', $sourceDevice->id)
            ->customerFacing()
            ->enabled()
            ->whereNotNull('password_encrypted')
            ->get();

        if ($wifiConfigs->isNotEmpty()) {
            $sshMapping = $migrationService->getSshToTr181WifiMapping();

            foreach ($wifiConfigs as $config) {
                $interfaceName = $config->interface_name;

                if (!isset($sshMapping[$interfaceName])) {
                    continue;
                }

                $paths = $sshMapping[$interfaceName];
                $password = $config->getPassword();

                // Set SSID
                if (!empty($config->ssid)) {
                    $parametersToSet[] = [
                        'name' => $paths['ssid_path'],
                        'value' => $config->ssid,
                        'type' => 'xsd:string',
                    ];
                }

                // Set password (from SSH - plaintext!)
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
            }

            $sources['wifi_ssh'] = [
                'networks' => $wifiConfigs->count(),
                'source_device_id' => $sourceDevice->id,
            ];

            Log::info('Combined restore: Added WiFi params from SSH extraction', [
                'device_id' => $device->id,
                'source_device_id' => $sourceDevice->id,
                'wifi_networks' => $wifiConfigs->count(),
                'params_added' => count($parametersToSet),
            ]);
        }

        // Part 2: Get non-WiFi parameters from TR-069 backup (DHCP, NAT, time, parental controls)
        $backup = $sourceDevice->configBackups()
            ->where(function ($query) {
                $query->where('description', 'like', '%corteca_transition%')
                    ->orWhere('description', 'like', '%Corteca%')
                    ->orWhere('description', 'like', '%transition%')
                    ->orWhere('type', 'corteca_transition');
            })
            ->latest()
            ->first();

        if (!$backup) {
            // Try to find any recent backup
            $backup = $sourceDevice->configBackups()->latest()->first();
        }

        if ($backup && !empty($backup->backup_data)) {
            // Convert TR-098 backup parameters to TR-181, excluding WiFi (we have better data from SSH)
            $backupData = is_array($backup->backup_data) ? $backup->backup_data : json_decode($backup->backup_data, true);

            if ($backupData) {
                $mappings = $this->getTr098ToTr181ParameterMapping();

                foreach ($backupData as $name => $param) {
                    // Skip WiFi parameters - we have better data from SSH
                    if (str_contains($name, 'WLANConfiguration') ||
                        str_contains($name, 'PreSharedKey') ||
                        str_contains($name, 'KeyPassphrase')) {
                        continue;
                    }

                    // Skip non-writable or management server params
                    if (!($param['writable'] ?? false)) {
                        continue;
                    }
                    if (str_contains($name, 'ManagementServer.URL') ||
                        str_contains($name, 'ManagementServer.Username') ||
                        str_contains($name, 'ManagementServer.Password')) {
                        continue;
                    }

                    // Check if we have a direct mapping
                    if (isset($mappings[$name])) {
                        $parametersToSet[] = [
                            'name' => $mappings[$name],
                            'value' => $param['value'],
                            'type' => $param['type'] ?? 'xsd:string',
                        ];
                        continue;
                    }

                    // Try pattern-based conversion
                    $tr181Name = $this->convertParameterName($name);
                    if ($tr181Name && $tr181Name !== $name) {
                        $parametersToSet[] = [
                            'name' => $tr181Name,
                            'value' => $param['value'],
                            'type' => $param['type'] ?? 'xsd:string',
                        ];
                    }
                }

                $sources['tr069_backup'] = [
                    'backup_id' => $backup->id,
                    'backup_type' => $backup->type ?? 'unknown',
                    'original_param_count' => count($backupData),
                ];

                Log::info('Combined restore: Added params from TR-069 backup', [
                    'device_id' => $device->id,
                    'backup_id' => $backup->id,
                    'backup_param_count' => count($backupData),
                    'total_params_now' => count($parametersToSet),
                ]);
            }
        }

        if (empty($parametersToSet)) {
            Log::warning('Combined restore: No parameters found from either source', [
                'device_id' => $device->id,
                'source_device_id' => $sourceDevice->id,
                'has_ssh_wifi' => $wifiConfigs->isNotEmpty(),
                'has_backup' => $backup !== null,
            ]);
            return null;
        }

        Log::info('Combined restore: Built parameter set', [
            'device_id' => $device->id,
            'total_parameters' => count($parametersToSet),
            'sources' => $sources,
        ]);

        return [
            'parameters' => $parametersToSet,
            'purpose' => 'tr181_migration_combined_restore',
            'sources' => $sources,
        ];
    }

    /**
     * Build Corteca pre-config parameters - downloads the conversion config file (custom URL)
     * Updated to use simpler PREEGEBOPR file from hay.net (only changes OperatorID to EGEB)
     */
    private function buildCortecaPreconfigParams(array $config, Device $device): array
    {
        // URL to pre-config file that triggers TR-181 conversion
        // Use hay.net URL - hayacs.hay.net fails with "file corrupted" error
        $preconfigUrl = $config['preconfig_url'] ?? Tr181MigrationService::PRECONFIG_EXTERNAL_URL;

        return [
            'file_type' => '3 Vendor Configuration File',
            'url' => $preconfigUrl,
            'file_size' => $config['file_size'] ?? 0,
            'corteca_migration_step' => 'preconfig',
        ];
    }

    /**
     * Build Corteca restore parameters - restores converted settings after migration
     */
    private function buildCortecaRestoreParams(array $config, Device $device): ?array
    {
        // Find the transition backup for this device
        $backup = $device->configBackups()
            ->where('description', 'like', '%corteca_transition%')
            ->orWhere('description', 'like', '%Corteca%')
            ->latest()
            ->first();

        if (!$backup) {
            // Try to find any recent backup
            $backup = $device->configBackups()->latest()->first();
        }

        if (!$backup) {
            Log::warning('No backup found for Corteca restore', ['device_id' => $device->id]);
            return null;
        }

        // Convert TR-098 parameters to TR-181 format
        $convertedParams = $this->convertTr098ToTr181($backup->backup_data);

        return array_merge($convertedParams, [
            'corteca_migration_step' => 'restore',
            'source_backup_id' => $backup->id,
        ]);
    }

    /**
     * Convert TR-098 parameter names to TR-181 equivalents
     * This handles the Nokia Beacon G6 specific parameter mapping
     */
    private function convertTr098ToTr181(array $tr098Params): array
    {
        $converted = [];

        // Parameter mapping table: TR-098 => TR-181
        // This comprehensive mapping is built from actual device parameter analysis
        // See: devices ALCLFD0FC633 (TR-098) and ALCLFD0A7F1E (TR-181)
        $mappings = $this->getTr098ToTr181ParameterMapping();

        foreach ($tr098Params as $name => $param) {
            // Skip non-writable or management server params
            if (!($param['writable'] ?? false)) {
                continue;
            }
            if (str_contains($name, 'ManagementServer.URL') ||
                str_contains($name, 'ManagementServer.Username') ||
                str_contains($name, 'ManagementServer.Password')) {
                continue;
            }

            // Check if we have a direct mapping
            if (isset($mappings[$name])) {
                $converted[$mappings[$name]] = $param['value'];
                continue;
            }

            // Try pattern-based conversion for common paths
            $tr181Name = $this->convertParameterName($name);
            if ($tr181Name && $tr181Name !== $name) {
                $converted[$tr181Name] = $param['value'];
            }
        }

        Log::info('Converted TR-098 to TR-181 parameters', [
            'original_count' => count($tr098Params),
            'converted_count' => count($converted),
        ]);

        return $converted;
    }

    /**
     * Convert a single TR-098 parameter name to TR-181 format
     */
    private function convertParameterName(string $tr098Name): ?string
    {
        // Basic conversion: InternetGatewayDevice -> Device
        if (str_starts_with($tr098Name, 'InternetGatewayDevice.')) {
            $path = substr($tr098Name, strlen('InternetGatewayDevice.'));

            // Apply path transformations
            $conversions = [
                'LANDevice.1.WLANConfiguration.' => 'WiFi.Radio.',
                'LANDevice.1.LANHostConfigManagement.' => 'DHCPv4.Server.Pool.1.',
                'WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.' => 'IP.Interface.1.',
                'WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.PortMapping.' => 'NAT.PortMapping.',
                'Time.' => 'Time.',
                'DeviceInfo.' => 'DeviceInfo.',
            ];

            foreach ($conversions as $from => $to) {
                if (str_starts_with($path, $from)) {
                    return 'Device.' . $to . substr($path, strlen($from));
                }
            }

            // Default: just replace root
            return 'Device.' . $path;
        }

        return null;
    }

    /**
     * Get comprehensive TR-098 to TR-181 parameter mapping for Nokia Beacon G6
     *
     * This mapping is built from actual device parameter analysis:
     * - TR-098 device: ALCLFD0FC633 (OUI 80AB4D)
     * - TR-181 device: ALCLFD0A7F1E (OUI 0C7C28)
     *
     * Categories covered:
     * - WiFi Configuration (SSID, passphrase, security, radio settings)
     * - DHCP Server (pool, lease time, DNS, reservations)
     * - Time/NTP (servers, timezone)
     * - Parental Controls (profiles, URL filters, schedules)
     * - Port Mappings (NAT, port forwarding)
     * - Trusted Networks (access control)
     *
     * Note: WiFi passwords are handled separately via SSH extraction because
     * TR-069 returns masked values for PreSharedKey/KeyPassphrase fields.
     *
     * @return array<string, string> Map of TR-098 path => TR-181 path
     */
    private function getTr098ToTr181ParameterMapping(): array
    {
        return [
            // ============================================================
            // WiFi Configuration - Primary Networks
            // Note: WiFi passwords should come from SSH extraction, not TR-069 backup
            // TR-069 returns masked values like "**********" for password fields
            // ============================================================

            // 2.4GHz Primary (WLANConfiguration.1 -> WiFi.SSID.1/AccessPoint.1)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => 'Device.WiFi.SSID.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable' => 'Device.WiFi.SSID.1.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType' => 'Device.WiFi.AccessPoint.1.Security.ModeEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel' => 'Device.WiFi.Radio.1.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AutoChannelEnable' => 'Device.WiFi.Radio.1.AutoChannelEnable',

            // 5GHz Primary (WLANConfiguration.5 -> WiFi.SSID.5/AccessPoint.5)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID' => 'Device.WiFi.SSID.5.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable' => 'Device.WiFi.SSID.5.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.5.Security.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.BeaconType' => 'Device.WiFi.AccessPoint.5.Security.ModeEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Channel' => 'Device.WiFi.Radio.2.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.AutoChannelEnable' => 'Device.WiFi.Radio.2.AutoChannelEnable',

            // 6GHz Primary (WLANConfiguration.9 -> WiFi.SSID.9/AccessPoint.9) - if present
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.SSID' => 'Device.WiFi.SSID.9.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.Enable' => 'Device.WiFi.SSID.9.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.9.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.9.Security.KeyPassphrase',

            // Guest Networks
            // 2.4GHz Guest (WLANConfiguration.2 -> WiFi.SSID.2/AccessPoint.2)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID' => 'Device.WiFi.SSID.2.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.Enable' => 'Device.WiFi.SSID.2.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.2.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase',

            // 5GHz Guest (WLANConfiguration.6 -> WiFi.SSID.6/AccessPoint.6)
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSID' => 'Device.WiFi.SSID.6.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.Enable' => 'Device.WiFi.SSID.6.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.6.SSIDAdvertisementEnabled',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.6.Security.KeyPassphrase',

            // ============================================================
            // DHCP Server Configuration
            // ============================================================
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPServerEnable' => 'Device.DHCPv4.Server.Enable',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MinAddress' => 'Device.DHCPv4.Server.Pool.1.MinAddress',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MaxAddress' => 'Device.DHCPv4.Server.Pool.1.MaxAddress',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.SubnetMask' => 'Device.DHCPv4.Server.Pool.1.SubnetMask',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPLeaseTime' => 'Device.DHCPv4.Server.Pool.1.LeaseTime',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DNSServers' => 'Device.DHCPv4.Server.Pool.1.DNSServers',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DomainName' => 'Device.DHCPv4.Server.Pool.1.DomainName',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPRouters' => 'Device.DHCPv4.Server.Pool.1.IPRouters',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.ReservedAddresses' => 'Device.DHCPv4.Server.Pool.1.ReservedAddresses',

            // DHCP Static Reservations (mapped dynamically - these are templates)
            // Actual instances will be matched by convertParameterName()

            // LAN IP Interface
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.Enable' => 'Device.IP.Interface.1.Enable',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress' => 'Device.IP.Interface.1.IPv4Address.1.IPAddress',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceSubnetMask' => 'Device.IP.Interface.1.IPv4Address.1.SubnetMask',

            // ============================================================
            // Time/NTP Configuration
            // ============================================================
            'InternetGatewayDevice.Time.Enable' => 'Device.Time.Enable',
            'InternetGatewayDevice.Time.NTPServer1' => 'Device.Time.NTPServer1',
            'InternetGatewayDevice.Time.NTPServer2' => 'Device.Time.NTPServer2',
            'InternetGatewayDevice.Time.NTPServer3' => 'Device.Time.NTPServer3',
            'InternetGatewayDevice.Time.NTPServer4' => 'Device.Time.NTPServer4',
            'InternetGatewayDevice.Time.NTPServer5' => 'Device.Time.NTPServer5',
            'InternetGatewayDevice.Time.LocalTimeZone' => 'Device.Time.LocalTimeZone',
            // Note: LocalTimeZoneName doesn't have direct TR-181 equivalent on Nokia devices

            // ============================================================
            // Parental Controls (Nokia-specific X_ALU-COM/X_ASB_COM extension)
            // Note: TR-098 uses X_ALU-COM, TR-181 uses X_ASB_COM prefix
            // ============================================================
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Enable' => 'Device.X_ALU-COM_NokiaParentalControl.Enable',
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.GlobalAccessInternet' => 'Device.X_ASB_COM_NokiaParentalControl.GlobalAccessInternet',

            // Profile 1 (first parental control profile)
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.1.Name' => 'Device.X_ASB_COM_NokiaParentalControl.Profile.1.Name',
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.1.HomeGroup' => 'Device.X_ASB_COM_NokiaParentalControl.Profile.1.HomeGroup',
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.1.AccessInternet' => 'Device.X_ASB_COM_NokiaParentalControl.Profile.1.AccessInternet',
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.1.UrlFilter.UrlFilterEnable' => 'Device.X_ASB_COM_NokiaParentalControl.Profile.1.UrlFilter.UrlFilterEnable',
            'InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.1.UrlFilter.UrlFilterMode' => 'Device.X_ASB_COM_NokiaParentalControl.Profile.1.UrlFilter.UrlFilterMode',

            // ============================================================
            // Trusted Networks / Access Control
            // TR-098: TrustedNetwork.{i}
            // TR-181: X_ALU-COM_AccessControl.AccessControlEntry.{i}
            // ============================================================
            // Note: The structure differs significantly between TR-098 and TR-181
            // TR-098 has SourceIPRangeStart/End, TR-181 has TrustedNetworkEnable
            // This may require special handling during restore

            // ============================================================
            // Port Mapping / NAT
            // TR-098: WANIPConnection.1.PortMapping.{i}
            // TR-181: NAT.PortMapping.{i}
            // These are handled dynamically by convertParameterName() due to variable instance numbers
            // ============================================================
        ];
    }

    /**
     * Get mapping for Port Mapping parameters
     * Port mappings have dynamic instance numbers, so we provide the property mappings
     *
     * @param int $instanceNum The port mapping instance number
     * @return array<string, string>
     */
    public function getPortMappingParameterMapping(int $instanceNum): array
    {
        $tr098Base = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.PortMapping.{$instanceNum}";
        $tr181Base = "Device.NAT.PortMapping.{$instanceNum}";

        return [
            "{$tr098Base}.PortMappingEnabled" => "{$tr181Base}.Enable",
            "{$tr098Base}.PortMappingDescription" => "{$tr181Base}.Description",
            "{$tr098Base}.ExternalPort" => "{$tr181Base}.ExternalPort",
            "{$tr098Base}.ExternalPortEndRange" => "{$tr181Base}.ExternalPortEndRange",
            "{$tr098Base}.InternalPort" => "{$tr181Base}.InternalPort",
            "{$tr098Base}.InternalClient" => "{$tr181Base}.InternalClient",
            "{$tr098Base}.PortMappingProtocol" => "{$tr181Base}.Protocol",
            "{$tr098Base}.RemoteHost" => "{$tr181Base}.RemoteHost",
            "{$tr098Base}.PortMappingLeaseDuration" => "{$tr181Base}.LeaseDuration",
        ];
    }

    /**
     * Get mapping for DHCP Static Address parameters
     *
     * @param int $poolNum The DHCP pool number (usually 1)
     * @param int $instanceNum The static address instance number
     * @return array<string, string>
     */
    public function getDhcpStaticAddressMapping(int $poolNum, int $instanceNum): array
    {
        $tr098Base = "InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPStaticAddress.{$instanceNum}";
        $tr181Base = "Device.DHCPv4.Server.Pool.{$poolNum}.StaticAddress.{$instanceNum}";

        return [
            "{$tr098Base}.Enable" => "{$tr181Base}.Enable",
            "{$tr098Base}.Chaddr" => "{$tr181Base}.Chaddr",
            "{$tr098Base}.Yiaddr" => "{$tr181Base}.Yiaddr",
        ];
    }

    /**
     * Get mapping for Parental Control Profile parameters
     *
     * @param int $profileNum The profile number
     * @return array<string, string>
     */
    public function getParentalControlProfileMapping(int $profileNum): array
    {
        $tr098Base = "InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.Profile.{$profileNum}";
        $tr181Base = "Device.X_ASB_COM_NokiaParentalControl.Profile.{$profileNum}";

        return [
            "{$tr098Base}.Name" => "{$tr181Base}.Name",
            "{$tr098Base}.HomeGroup" => "{$tr181Base}.HomeGroup",
            "{$tr098Base}.AccessInternet" => "{$tr181Base}.AccessInternet",
            "{$tr098Base}.UrlFilter.UrlFilterEnable" => "{$tr181Base}.UrlFilter.UrlFilterEnable",
            "{$tr098Base}.UrlFilter.UrlFilterMode" => "{$tr181Base}.UrlFilter.UrlFilterMode",
        ];
    }
}
