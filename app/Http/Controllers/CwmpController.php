<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\CwmpSession;
use App\Models\Task;
use App\Services\CwmpService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CwmpController extends Controller
{
    public function __construct(
        private CwmpService $cwmpService,
        private ProvisioningService $provisioningService
    ) {}

    /**
     * Main CWMP endpoint - handles all TR-069 communication
     */
    public function handle(Request $request): Response
    {
        try {
            // Get raw POST body (SOAP/XML)
            $xmlContent = $request->getContent();

            // Log incoming request for debugging
            Log::info('CWMP Request received', [
                'ip' => $request->ip(),
                'content_length' => strlen($xmlContent),
                'headers' => $request->headers->all(),
            ]);

            // If content is suspiciously small or empty, log it
            if (strlen($xmlContent) < 100) {
                Log::warning('CWMP Request has very small content', [
                    'content' => $xmlContent,
                    'content_length' => strlen($xmlContent),
                ]);
            }

            // Handle empty POST (device ready for next command or ending session)
            if (empty($xmlContent) || strlen($xmlContent) < 10) {
                return $this->handleEmptyPost($request);
            }

            // Determine message type and parse accordingly
            // Check for Response in element name (handles both <Response> and <Response/>)
            $isResponse = str_contains($xmlContent, 'Response');

            if ($isResponse) {
                $parsed = $this->cwmpService->parseResponse($xmlContent);
            } else {
                $parsed = $this->cwmpService->parseInform($xmlContent);
            }

            // Log the parsed method for debugging
            Log::info('CWMP Method detected', [
                'method' => $parsed['method'] ?? 'unknown',
                'device_id' => $parsed['device_id'] ?? 'unknown',
            ]);

            // For debugging: Log full XML for non-Inform messages
            if ($parsed['method'] !== 'Inform') {
                Log::info('CWMP Response received from device', [
                    'method' => $parsed['method'],
                    'xml' => $xmlContent,
                ]);
            }

            // Handle based on method
            $responseXml = match ($parsed['method']) {
                'Inform' => $this->handleInform($request, $parsed),
                'GetParameterValues' => $this->handleGetParameterValuesResponse($parsed),
                'GetParameterNames' => $this->handleGetParameterNamesResponse($parsed),
                'SetParameterValues' => $this->handleSetParameterValuesResponse($parsed),
                'Reboot' => $this->handleRebootResponse($parsed),
                'FactoryReset' => $this->handleFactoryResetResponse($parsed),
                'TransferComplete' => $this->handleTransferCompleteResponse($parsed),
                default => $this->cwmpService->createEmptyResponse(),
            };

            // Log outgoing response for debugging
            Log::info('CWMP Response sent to device', [
                'response_size' => strlen($responseXml),
                'xml' => $responseXml,
            ]);

            // Return SOAP response
            return response($responseXml, 200)
                ->header('Content-Type', 'text/xml; charset=utf-8')
                ->header('SOAPAction', '');

        } catch (\Exception $e) {
            Log::error('CWMP Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'xml_content' => $xmlContent ?? 'N/A',
            ]);

            // Return SOAP Fault
            return response('', 500)
                ->header('Content-Type', 'text/xml; charset=utf-8');
        }
    }

    /**
     * Handle Inform message from device
     */
    private function handleInform(Request $request, array $parsed): string
    {
        $deviceId = $parsed['device_id'];

        // Create or update device
        $device = Device::updateOrCreate(
            ['id' => $deviceId],
            [
                'manufacturer' => $parsed['manufacturer'],
                'oui' => $parsed['oui'],
                'product_class' => $parsed['product_class'],
                'serial_number' => $parsed['serial_number'],
                'ip_address' => $request->ip(),
                'online' => true,
                'last_inform' => now(),
            ]
        );

        // Store parameters from Inform
        foreach ($parsed['parameters'] as $name => $param) {
            $device->setParameter(
                $name,
                $param['value'],
                $param['type'],
                false
            );
        }

        // Update specific device info from parameters
        if (isset($parsed['parameters']['InternetGatewayDevice.DeviceInfo.SoftwareVersion'])) {
            $device->software_version = $parsed['parameters']['InternetGatewayDevice.DeviceInfo.SoftwareVersion']['value'];
        } elseif (isset($parsed['parameters']['Device.DeviceInfo.SoftwareVersion'])) {
            $device->software_version = $parsed['parameters']['Device.DeviceInfo.SoftwareVersion']['value'];
        }

        if (isset($parsed['parameters']['InternetGatewayDevice.DeviceInfo.HardwareVersion'])) {
            $device->hardware_version = $parsed['parameters']['InternetGatewayDevice.DeviceInfo.HardwareVersion']['value'];
        } elseif (isset($parsed['parameters']['Device.DeviceInfo.HardwareVersion'])) {
            $device->hardware_version = $parsed['parameters']['Device.DeviceInfo.HardwareVersion']['value'];
        }

        if (isset($parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestURL'])) {
            $device->connection_request_url = $parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestURL']['value'];
        } elseif (isset($parsed['parameters']['Device.ManagementServer.ConnectionRequestURL'])) {
            $device->connection_request_url = $parsed['parameters']['Device.ManagementServer.ConnectionRequestURL']['value'];
        }

        if (isset($parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername'])) {
            $device->connection_request_username = $parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername']['value'];
        } elseif (isset($parsed['parameters']['Device.ManagementServer.ConnectionRequestUsername'])) {
            $device->connection_request_username = $parsed['parameters']['Device.ManagementServer.ConnectionRequestUsername']['value'];
        }

        if (isset($parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword'])) {
            $device->connection_request_password = $parsed['parameters']['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword']['value'];
        } elseif (isset($parsed['parameters']['Device.ManagementServer.ConnectionRequestPassword'])) {
            $device->connection_request_password = $parsed['parameters']['Device.ManagementServer.ConnectionRequestPassword']['value'];
        }

        // Capture UDP Connection Request Address (STUN-discovered address)
        if (isset($parsed['parameters']['InternetGatewayDevice.ManagementServer.UDPConnectionRequestAddress'])) {
            $udpAddress = $parsed['parameters']['InternetGatewayDevice.ManagementServer.UDPConnectionRequestAddress']['value'];
            if ($udpAddress && $udpAddress !== '(null)' && $udpAddress !== '') {
                $device->udp_connection_request_address = $udpAddress;
                Log::info('UDP Connection Request Address received', [
                    'device_id' => $device->id,
                    'udp_address' => $udpAddress,
                ]);
            }
        } elseif (isset($parsed['parameters']['Device.ManagementServer.UDPConnectionRequestAddress'])) {
            $udpAddress = $parsed['parameters']['Device.ManagementServer.UDPConnectionRequestAddress']['value'];
            if ($udpAddress && $udpAddress !== '(null)' && $udpAddress !== '') {
                $device->udp_connection_request_address = $udpAddress;
                Log::info('UDP Connection Request Address received', [
                    'device_id' => $device->id,
                    'udp_address' => $udpAddress,
                ]);
            }
        }

        $device->save();

        // Create initial auto-backup if not already created and device has parameters
        // This happens on first TR-069 Inform to secure device data before any changes
        if (!$device->initial_backup_created && $device->parameters()->count() > 0) {
            $parameters = $device->parameters()
                ->get()
                ->mapWithKeys(function ($param) {
                    return [$param->name => [
                        'value' => $param->value,
                        'type' => $param->type,
                        'writable' => $param->writable,
                    ]];
                })
                ->toArray();

            $device->configBackups()->create([
                'name' => 'Initial Auto Backup - ' . now()->format('Y-m-d H:i:s'),
                'description' => 'Automatically created on first TR-069 connection to preserve device configuration',
                'backup_data' => $parameters,
                'is_auto' => true,
                'parameter_count' => count($parameters),
            ]);

            $device->update([
                'initial_backup_created' => true,
                'last_backup_at' => now(),
            ]);

            Log::info('Auto-backup created on first Inform', [
                'device_id' => $device->id,
                'parameter_count' => count($parameters),
            ]);
        }

        // Auto-create DeviceType if it doesn't exist for this product_class
        if ($parsed['product_class'] && $parsed['manufacturer']) {
            $deviceType = DeviceType::firstOrCreate(
                [
                    'product_class' => $parsed['product_class'],
                ],
                [
                    'name' => $parsed['manufacturer'] . ' ' . $parsed['product_class'],
                    'manufacturer' => $parsed['manufacturer'],
                    'description' => 'Auto-created from first device check-in',
                ]
            );

            if ($deviceType->wasRecentlyCreated) {
                Log::info('Auto-created new DeviceType', [
                    'product_class' => $parsed['product_class'],
                    'manufacturer' => $parsed['manufacturer'],
                ]);
            }
        }

        // Check for DIAGNOSTICS COMPLETE event first
        $hasDiagnosticsComplete = collect($parsed['events'])->contains(function ($event) {
            return $event['code'] === '8 DIAGNOSTICS COMPLETE';
        });

        // Check for tasks that were sent but never responded to
        // This can happen when a device starts a new session without sending responses
        $abandonedTasksQuery = $device->tasks()->where('status', 'sent');

        // If DIAGNOSTICS COMPLETE event is present, exclude diagnostic tasks
        // (they will be processed by queueDiagnosticResultRetrieval instead)
        if ($hasDiagnosticsComplete) {
            $abandonedTasksQuery->whereNotIn('task_type', ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan']);
        }

        $abandonedTasks = $abandonedTasksQuery->get();

        if ($abandonedTasks->isNotEmpty()) {
            foreach ($abandonedTasks as $abandonedTask) {
                // Special handling for set_params tasks
                // WiFi changes often cause device to disconnect/reconnect, but changes may have succeeded
                if ($abandonedTask->task_type === 'set_params') {
                    // Queue a verification task to check if parameters actually changed
                    $this->queueParameterVerification($device, $abandonedTask, $parsed['parameters']);
                } else {
                    $abandonedTask->markAsFailed(
                        'Device started new TR-069 session without responding to command. ' .
                        'This usually indicates the device rejected or failed to process the request.'
                    );

                    Log::warning('Abandoned task detected and failed', [
                        'device_id' => $device->id,
                        'task_id' => $abandonedTask->id,
                        'task_type' => $abandonedTask->task_type,
                        'events' => $parsed['events'],
                    ]);
                }
            }
        }

        // Create session
        $session = CwmpSession::create([
            'device_id' => $deviceId,
            'inform_events' => $parsed['events'],
            'messages_exchanged' => 1,
            'started_at' => now(),
        ]);

        Log::info('Device Inform received', [
            'device_id' => $deviceId,
            'events' => $parsed['events'],
            'param_count' => count($parsed['parameters']),
        ]);

        // Check for DIAGNOSTICS COMPLETE event and queue result retrieval
        foreach ($parsed['events'] as $event) {
            if ($event['code'] === '8 DIAGNOSTICS COMPLETE') {
                $this->queueDiagnosticResultRetrieval($device);
                break;
            }
        }

        // Auto-provision device based on events and rules
        $this->provisioningService->autoProvision($device, $parsed['events']);

        // ALWAYS send InformResponse first (TR-069 spec requirement)
        // RPCs will be sent after device acknowledges with empty POST
        return $this->cwmpService->createInformResponse($parsed['max_envelopes']);
    }

    /**
     * Handle empty POST from device (signals device is ready for ACS commands)
     */
    private function handleEmptyPost(Request $request): Response
    {
        Log::info('Empty POST received from device', ['ip' => $request->ip()]);

        // Find the most recent device from this IP address
        $device = Device::where('ip_address', $request->ip())
            ->where('last_inform', '>=', now()->subMinutes(5))
            ->orderBy('last_inform', 'desc')
            ->first();

        if (!$device) {
            Log::warning('Empty POST from unknown device', ['ip' => $request->ip()]);
            return response('', 204)->header('Content-Type', 'text/xml; charset=utf-8');
        }

        // Check for pending tasks
        $pendingTask = $device->tasks()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->first();

        if ($pendingTask) {
            Log::info('Sending queued RPC to device', [
                'device_id' => $device->id,
                'task_type' => $pendingTask->task_type,
            ]);

            // Mark task as sent
            $pendingTask->markAsSent();

            // Generate and send RPC
            $rpcXml = $this->generateRpcForTask($pendingTask);

            Log::info('CWMP Response sent to device', [
                'response_size' => strlen($rpcXml),
                'xml' => $rpcXml,
            ]);

            return response($rpcXml, 200)
                ->header('Content-Type', 'text/xml; charset=utf-8')
                ->header('SOAPAction', '');
        }

        // No pending tasks - end session
        Log::info('No pending tasks - ending CWMP session', ['device_id' => $device->id]);
        return response('', 204)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Handle GetParameterValuesResponse
     */
    private function handleGetParameterValuesResponse(array $parsed): string
    {
        // Find the task that was sent (get_params, discover_troubleshooting, get_diagnostic_results, or verify_set_params)
        $task = Task::where('status', 'sent')
            ->whereIn('task_type', ['get_params', 'discover_troubleshooting', 'get_diagnostic_results', 'verify_set_params'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $device = $task->device;

            // Check if this is a diagnostic result retrieval
            if ($task->task_type === 'get_diagnostic_results') {
                // Store diagnostic parameters in device parameters table
                foreach ($parsed['parameters'] as $name => $param) {
                    $device->setParameter(
                        $name,
                        $param['value'],
                        $param['type']
                    );
                }

                // Find the original diagnostic task and store results there
                $diagnosticTaskId = $task->parameters['diagnostic_task_id'] ?? null;
                if ($diagnosticTaskId) {
                    $diagnosticTask = Task::find($diagnosticTaskId);
                    if ($diagnosticTask) {
                        $diagnosticTask->update([
                            'result' => $parsed['parameters'],
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);

                        Log::info('Diagnostic results stored', [
                            'device_id' => $device->id,
                            'diagnostic_type' => $task->parameters['diagnostic_type'] ?? 'unknown',
                            'param_count' => count($parsed['parameters']),
                        ]);
                    }
                }
                // Mark the retrieval task as completed
                $task->markAsCompleted($parsed['parameters']);
            } elseif ($task->task_type === 'verify_set_params') {
                // Verification task - check if parameters match expected values
                $this->processVerificationResults($task, $parsed['parameters']);
            } elseif ($task->task_type === 'discover_troubleshooting') {
                // Discovery task completed - create detailed query task
                $dataModel = $task->parameters['data_model'] ?? 'InternetGatewayDevice:1';

                // Store discovery results in device
                foreach ($parsed['parameters'] as $name => $param) {
                    $device->setParameter(
                        $name,
                        $param['value'],
                        $param['type']
                    );
                }

                // Mark discovery task as completed
                $task->markAsCompleted($parsed['parameters']);

                Log::info('Discovery completed, building detailed query', [
                    'device_id' => $device->id,
                    'discovery_count' => count($parsed['parameters']),
                ]);

                // Build detailed parameters from discovery results
                $detailedParams = app(\App\Http\Controllers\Api\DeviceController::class)
                    ->buildDetailedParametersFromDiscovery($parsed['parameters'], $dataModel, $device);

                // Chunk parameters for devices that can't handle large requests (e.g., Calix 844E)
                // Split into chunks of 20 parameters to avoid device crashes
                $chunkSize = 20;
                $paramChunks = array_chunk($detailedParams, $chunkSize);

                Log::info('Discovery completed, creating chunked detailed query tasks', [
                    'device_id' => $device->id,
                    'param_count' => count($detailedParams),
                    'chunk_count' => count($paramChunks),
                    'chunk_size' => $chunkSize,
                ]);

                // Create a get_params task for each chunk
                $taskIds = [];
                foreach ($paramChunks as $chunkIndex => $chunk) {
                    $detailedTask = Task::create([
                        'device_id' => $device->id,
                        'task_type' => 'get_params',
                        'parameters' => [
                            'names' => $chunk,
                        ],
                        'status' => 'pending',
                    ]);
                    $taskIds[] = $detailedTask->id;

                    Log::info('Detailed troubleshooting chunk task created', [
                        'device_id' => $device->id,
                        'task_id' => $detailedTask->id,
                        'chunk_index' => $chunkIndex + 1,
                        'chunk_total' => count($paramChunks),
                        'param_count' => count($chunk),
                    ]);
                }

                // Trigger connection request to ensure first task is picked up quickly
                // Subsequent chunks will be picked up in following sessions
                $this->connectionRequestService->sendConnectionRequest($device);
            } else {
                // Regular get_params - store parameters in device
                foreach ($parsed['parameters'] as $name => $param) {
                    $device->setParameter(
                        $name,
                        $param['value'],
                        $param['type']
                    );
                }

                // Mark task as completed
                $task->markAsCompleted($parsed['parameters']);

                Log::info('GetParameterValues completed', [
                    'device_id' => $device->id,
                    'param_count' => count($parsed['parameters']),
                ]);
            }
        }

        // Check for more pending tasks
        if ($task && $task->device) {
            $nextTask = $task->device->tasks()
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->first();

            if ($nextTask) {
                $nextTask->markAsSent();
                return $this->generateRpcForTask($nextTask);
            }
        }

        // No more tasks - end session
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle GetParameterNamesResponse
     */
    private function handleGetParameterNamesResponse(array $parsed): string
    {
        // Find the task that was sent (get_parameter_names)
        $task = Task::where('status', 'sent')
            ->where('task_type', 'get_parameter_names')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $device = $task->device;
            $parameterList = $parsed['parameter_list'] ?? [];

            Log::info('GetParameterNames completed', [
                'device_id' => $device->id,
                'parameter_count' => count($parameterList),
            ]);

            // Mark task as completed with the parameter list
            $task->markAsCompleted([
                'parameter_list' => $parameterList,
                'total_parameters' => count($parameterList),
            ]);

            // Optionally create a follow-up task to retrieve values for all discovered parameters
            // Only retrieve leaf parameters (these are actual values, not just object nodes)
            $leafParams = array_filter($parameterList, fn($param) => !str_ends_with($param['name'], '.'));

            if (!empty($leafParams)) {
                $paramNames = array_column($leafParams, 'name');

                // Chunk parameters into batches to avoid overwhelming the device
                // Many devices can't handle 1000+ parameters in a single request
                $chunkSize = 100;
                $chunks = array_chunk($paramNames, $chunkSize);

                Log::info('Creating chunked tasks to retrieve parameter values', [
                    'device_id' => $device->id,
                    'total_params' => count($paramNames),
                    'chunk_count' => count($chunks),
                    'chunk_size' => $chunkSize,
                ]);

                // Create a task for each chunk
                foreach ($chunks as $index => $chunk) {
                    $valuesTask = Task::create([
                        'device_id' => $device->id,
                        'task_type' => 'get_params',
                        'parameters' => [
                            'names' => $chunk,
                            'chunk_index' => $index + 1,
                            'total_chunks' => count($chunks),
                        ],
                        'status' => 'pending',
                    ]);

                    Log::info('Created chunked parameter retrieval task', [
                        'device_id' => $device->id,
                        'task_id' => $valuesTask->id,
                        'chunk' => ($index + 1) . '/' . count($chunks),
                        'param_count' => count($chunk),
                    ]);
                }
            }
        }

        // Check for more pending tasks
        if ($task && $task->device) {
            $nextTask = $task->device->tasks()
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->first();

            if ($nextTask) {
                $nextTask->markAsSent();
                return $this->generateRpcForTask($nextTask);
            }
        }

        // No more tasks - end session
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle SetParameterValuesResponse
     */
    private function handleSetParameterValuesResponse(array $parsed): string
    {
        // Find the task that was sent (set_params, set_parameter_values, download/upload diagnostics, ping_diagnostics, or traceroute_diagnostics)
        $task = Task::where('status', 'sent')
            ->whereIn('task_type', ['set_params', 'set_parameter_values', 'ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            if ($parsed['status'] === 0) {
                // For diagnostic tasks, keep them as 'sent' until we get the "8 DIAGNOSTICS COMPLETE" event
                // and retrieve the actual results
                if (in_array($task->task_type, ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics'])) {
                    Log::info('Diagnostic task acknowledged by device, awaiting completion', [
                        'device_id' => $task->device_id,
                        'task_type' => $task->task_type,
                    ]);
                } else {
                    $task->markAsCompleted(['status' => $parsed['status']]);
                    Log::info('SetParameterValues completed', ['device_id' => $task->device_id]);
                }
            } else {
                $task->markAsFailed('SetParameterValues failed with status: ' . $parsed['status']);
                Log::warning('SetParameterValues failed', ['device_id' => $task->device_id, 'status' => $parsed['status']]);
            }
        }

        // Check for more pending tasks
        if ($task && $task->device) {
            $nextTask = $task->device->tasks()
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->first();

            if ($nextTask) {
                $nextTask->markAsSent();
                return $this->generateRpcForTask($nextTask);
            }
        }

        // No more tasks - end session
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle RebootResponse
     */
    private function handleRebootResponse(array $parsed): string
    {
        $task = Task::where('status', 'sent')
            ->where('task_type', 'reboot')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $task->markAsCompleted();
            Log::info('Reboot command sent', ['device_id' => $task->device_id]);

            // Queue a task to refresh uptime after reboot completes
            $device = $task->device;
            $dataModel = $device->getDataModel();
            $uptimeParam = $dataModel === 'Device:2'
                ? 'Device.DeviceInfo.UpTime'
                : 'InternetGatewayDevice.DeviceInfo.UpTime';

            Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_params',
                'description' => 'Refresh uptime after reboot',
                'status' => 'pending',
                'parameters' => [$uptimeParam],
            ]);

            Log::info('Queued uptime refresh after reboot', [
                'device_id' => $device->id,
                'parameter' => $uptimeParam,
            ]);
        }

        // End session after reboot command
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle FactoryResetResponse
     */
    private function handleFactoryResetResponse(array $parsed): string
    {
        $task = Task::where('status', 'sent')
            ->where('task_type', 'factory_reset')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $task->markAsCompleted();
            Log::info('FactoryReset command sent', ['device_id' => $task->device_id]);
        }

        // End session after factory reset command
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle TransferComplete (Download/Upload completion notification)
     */
    private function handleTransferCompleteResponse(array $parsed): string
    {
        $task = Task::where('status', 'sent')
            ->whereIn('task_type', ['download', 'upload'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $faultCode = $parsed['fault_code'] ?? 0;
            $faultString = $parsed['fault_string'] ?? '';

            if ($faultCode === 0) {
                $task->markAsCompleted([
                    'fault_code' => $faultCode,
                    'start_time' => $parsed['start_time'] ?? null,
                    'complete_time' => $parsed['complete_time'] ?? null,
                ]);

                Log::info('Transfer completed successfully', [
                    'device_id' => $task->device_id,
                    'task_type' => $task->task_type,
                    'start_time' => $parsed['start_time'] ?? null,
                    'complete_time' => $parsed['complete_time'] ?? null,
                ]);
            } else {
                $task->markAsFailed("Transfer failed (FaultCode {$faultCode}): {$faultString}");

                Log::warning('Transfer failed', [
                    'device_id' => $task->device_id,
                    'task_type' => $task->task_type,
                    'fault_code' => $faultCode,
                    'fault_string' => $faultString,
                ]);
            }
        }

        // End session after transfer complete (device will likely reboot for firmware upgrades)
        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Generate RPC message for a task
     */
    private function generateRpcForTask(Task $task): string
    {
        return match ($task->task_type) {
            'get_params', 'discover_troubleshooting', 'get_diagnostic_results', 'verify_set_params' => $this->cwmpService->createGetParameterValues(
                $task->parameters['names'] ?? []
            ),
            'get_parameter_names' => $this->cwmpService->createGetParameterNames(
                $task->parameters['path'] ?? 'InternetGatewayDevice.',
                $task->parameters['next_level'] ?? false
            ),
            'set_params' => $this->cwmpService->createSetParameterValues(
                $task->parameters['values'] ?? []
            ),
            'set_parameter_values' => $this->cwmpService->createSetParameterValues(
                $task->parameters ?? []
            ),
            'wifi_scan' => $this->cwmpService->createSetParameterValues(
                $task->parameters ?? []
            ),
            'download_diagnostics' => $this->cwmpService->createSetParameterValues(
                $task->parameters ?? []
            ),
            'upload_diagnostics' => $this->cwmpService->createSetParameterValues(
                $task->parameters ?? []
            ),
            'reboot' => $this->cwmpService->createReboot(
                $task->parameters['command_key'] ?? 'reboot_' . time()
            ),
            'factory_reset' => $this->cwmpService->createFactoryReset(),
            'download' => $this->cwmpService->createDownload(
                $task->parameters['url'] ?? '',
                $task->parameters['file_type'] ?? '1 Firmware Upgrade Image',
                $task->parameters['username'] ?? '',
                $task->parameters['password'] ?? ''
            ),
            'upload' => $this->cwmpService->createUpload(
                $task->parameters['url'] ?? '',
                $task->parameters['file_type'] ?? '3 Vendor Log File',
                $task->parameters['username'] ?? '',
                $task->parameters['password'] ?? ''
            ),
            'ping_diagnostics' => $this->generatePingDiagnostics($task),
            'traceroute_diagnostics' => $this->generateTracerouteDiagnostics($task),
            default => $this->cwmpService->createEmptyResponse(),
        };
    }

    /**
     * Generate Ping Diagnostics SetParameterValues request
     */
    private function generatePingDiagnostics(Task $task): string
    {
        $dataModel = $task->device->getDataModel();
        $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.IPPingDiagnostics' : 'InternetGatewayDevice.IPPingDiagnostics';

        $parameters = [
            "{$prefix}.DiagnosticsState" => 'Requested',
            "{$prefix}.Host" => $task->parameters['host'] ?? '8.8.8.8',
            "{$prefix}.NumberOfRepetitions" => [
                'value' => $task->parameters['count'] ?? 4,
                'type' => 'xsd:unsignedInt',
            ],
            "{$prefix}.Timeout" => [
                'value' => $task->parameters['timeout'] ?? 5000,
                'type' => 'xsd:unsignedInt',
            ],
        ];

        return $this->cwmpService->createSetParameterValues($parameters);
    }

    /**
     * Generate Traceroute Diagnostics SetParameterValues request
     */
    private function generateTracerouteDiagnostics(Task $task): string
    {
        $dataModel = $task->device->getDataModel();
        $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.TraceRouteDiagnostics' : 'InternetGatewayDevice.TraceRouteDiagnostics';

        $parameters = [
            "{$prefix}.DiagnosticsState" => 'Requested',
            "{$prefix}.Host" => $task->parameters['host'] ?? '8.8.8.8',
            "{$prefix}.MaxHopCount" => [
                'value' => $task->parameters['max_hops'] ?? 30,
                'type' => 'xsd:unsignedInt',
            ],
            "{$prefix}.Timeout" => [
                'value' => $task->parameters['timeout'] ?? 5000,
                'type' => 'xsd:unsignedInt',
            ],
        ];

        return $this->cwmpService->createSetParameterValues($parameters);
    }

    /**
     * Queue diagnostic result retrieval after DIAGNOSTICS COMPLETE event
     */
    private function queueDiagnosticResultRetrieval(Device $device): void
    {
        // Find the most recent completed diagnostic task
        $diagnosticTask = $device->tasks()
            ->where('status', 'sent')
            ->whereIn('task_type', ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$diagnosticTask) {
            Log::warning('DIAGNOSTICS COMPLETE event but no diagnostic task found', [
                'device_id' => $device->id,
            ]);
            return;
        }

        $dataModel = $device->getDataModel();
        $taskType = $diagnosticTask->task_type;

        if ($taskType === 'ping_diagnostics') {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.IPPingDiagnostics' : 'InternetGatewayDevice.IPPingDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.SuccessCount",
                "{$prefix}.FailureCount",
                "{$prefix}.AverageResponseTime",
                "{$prefix}.MinimumResponseTime",
                "{$prefix}.MaximumResponseTime",
            ];
        } elseif ($taskType === 'traceroute_diagnostics') {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.TraceRouteDiagnostics' : 'InternetGatewayDevice.TraceRouteDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.ResponseTime",
                "{$prefix}.RouteHopsNumberOfEntries",
                "{$prefix}.RouteHops.",  // Partial path query to get all hop entries
            ];
        } elseif ($taskType === 'download_diagnostics') {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.DownloadDiagnostics' : 'InternetGatewayDevice.DownloadDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.ROMTime",
                "{$prefix}.BOMTime",
                "{$prefix}.EOMTime",
                "{$prefix}.TestBytesReceived",
                "{$prefix}.TotalBytesReceived",
                "{$prefix}.TCPOpenRequestTime",
                "{$prefix}.TCPOpenResponseTime",
            ];
        } elseif ($taskType === 'upload_diagnostics') {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.UploadDiagnostics' : 'InternetGatewayDevice.UploadDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.ROMTime",
                "{$prefix}.BOMTime",
                "{$prefix}.EOMTime",
                "{$prefix}.TestBytesSent",
                "{$prefix}.TotalBytesSent",
                "{$prefix}.TCPOpenRequestTime",
                "{$prefix}.TCPOpenResponseTime",
            ];
        } elseif ($taskType === 'wifi_scan') {
            // Get device-specific WiFi diagnostic paths
            $stateParam = $this->getWiFiDiagnosticParameterPath($device, 'state');
            $resultPrefix = $this->getWiFiDiagnosticParameterPath($device, 'result');

            $parameters = [
                $stateParam,
                $resultPrefix,  // Partial path query to get all scan result entries
            ];
        } else {
            return;
        }

        // Create a task to retrieve the diagnostic results
        Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_diagnostic_results',
            'parameters' => [
                'names' => $parameters,
                'diagnostic_task_id' => $diagnosticTask->id,
                'diagnostic_type' => $diagnosticTask->task_type,
            ],
            'status' => 'pending',
        ]);

        Log::info('Queued diagnostic result retrieval', [
            'device_id' => $device->id,
            'diagnostic_type' => $diagnosticTask->task_type,
        ]);
    }

    /**
     * Process verification results and update original task status
     */
    private function processVerificationResults(Task $verificationTask, array $actualParameters): void
    {
        $originalTaskId = $verificationTask->parameters['original_task_id'] ?? null;
        $expectedValues = $verificationTask->parameters['expected_values'] ?? [];

        if (!$originalTaskId) {
            $verificationTask->markAsFailed('No original task ID');
            return;
        }

        $originalTask = Task::find($originalTaskId);
        if (!$originalTask) {
            $verificationTask->markAsFailed('Original task not found');
            return;
        }

        // Compare actual values with expected values
        $matchedCount = 0;
        $totalParams = count($expectedValues);
        $mismatches = [];

        foreach ($expectedValues as $paramName => $expectedValue) {
            // Extract expected value (handle both simple strings and {value, type} objects)
            $expectedVal = is_array($expectedValue) && isset($expectedValue['value'])
                ? $expectedValue['value']
                : $expectedValue;

            // Get actual value
            $actualParam = $actualParameters[$paramName] ?? null;
            $actualVal = $actualParam['value'] ?? null;

            // Normalize for comparison
            $expectedStr = (string) $expectedVal;
            $actualStr = (string) $actualVal;

            if ($expectedStr === $actualStr) {
                $matchedCount++;
            } else {
                $mismatches[$paramName] = [
                    'expected' => $expectedStr,
                    'actual' => $actualStr ?? 'null',
                ];
            }
        }

        // Calculate match percentage
        $matchPercentage = $totalParams > 0 ? ($matchedCount / $totalParams) * 100 : 0;

        // Mark original task based on results
        if ($matchPercentage >= 80) {
            $originalTask->markAsCompleted([
                'message' => 'Parameters verified after reconnect',
                'matched' => $matchedCount,
                'total' => $totalParams,
                'match_percentage' => round($matchPercentage, 1),
            ]);

            Log::info('Parameter verification successful', [
                'device_id' => $originalTask->device_id,
                'original_task_id' => $originalTaskId,
                'matched' => $matchedCount,
                'total' => $totalParams,
                'percentage' => round($matchPercentage, 1),
            ]);
        } else {
            $originalTask->markAsFailed(
                'Parameter verification failed. ' .
                "Matched: {$matchedCount}/{$totalParams} (" . round($matchPercentage, 1) . "%)\n" .
                'Mismatches: ' . json_encode($mismatches, JSON_PRETTY_PRINT)
            );

            Log::warning('Parameter verification failed', [
                'device_id' => $originalTask->device_id,
                'original_task_id' => $originalTaskId,
                'matched' => $matchedCount,
                'total' => $totalParams,
                'percentage' => round($matchPercentage, 1),
                'mismatches' => $mismatches,
            ]);
        }

        // Mark verification task as completed
        $verificationTask->markAsCompleted([
            'matched' => $matchedCount,
            'total' => $totalParams,
            'match_percentage' => round($matchPercentage, 1),
            'original_task_updated' => true,
        ]);
    }

    /**
     * Verify if set_params task actually succeeded by checking current parameter values
     * This handles cases where device disconnects/reconnects after parameter changes (common with WiFi)
     */
    private function queueParameterVerification(Device $device, Task $task, array $currentParameters): void
    {
        $setValues = $task->parameters['values'] ?? [];

        if (empty($setValues)) {
            $task->markAsFailed('No parameters to verify');
            return;
        }

        // Check if any of the parameters from the Inform match what we tried to set
        $matchedCount = 0;
        $totalParams = count($setValues);
        $parametersToVerify = [];

        foreach ($setValues as $paramName => $paramValue) {
            // Extract actual value (handle both simple strings and {value, type} objects)
            $expectedValue = is_array($paramValue) && isset($paramValue['value'])
                ? $paramValue['value']
                : $paramValue;

            // Convert boolean values for comparison
            if (is_bool($expectedValue)) {
                $expectedValue = $expectedValue ? '1' : '0';
            }

            // Check if this parameter is in the current Inform
            $currentValue = $currentParameters[$paramName] ?? null;

            if ($currentValue !== null) {
                // Normalize values for comparison
                $currentValueStr = (string) $currentValue;
                $expectedValueStr = (string) $expectedValue;

                if ($currentValueStr === $expectedValueStr) {
                    $matchedCount++;
                } else {
                    // Parameter exists but doesn't match - need to verify
                    $parametersToVerify[] = $paramName;
                }
            } else {
                // Parameter not in Inform - need to verify
                $parametersToVerify[] = $paramName;
            }
        }

        // If 80% or more parameters matched in the Inform, consider it successful
        if ($matchedCount >= ($totalParams * 0.8)) {
            $task->markAsCompleted([
                'message' => 'Parameters verified in Inform after device reconnect',
                'matched' => $matchedCount,
                'total' => $totalParams,
            ]);

            Log::info('Set params task verified as successful after reconnect', [
                'device_id' => $device->id,
                'task_id' => $task->id,
                'matched' => $matchedCount,
                'total' => $totalParams,
            ]);
        } elseif (!empty($parametersToVerify)) {
            // Queue a GetParameterValues to verify the remaining parameters
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'verify_set_params',
                'description' => 'Verify parameter changes after reconnect',
                'parameters' => [
                    'names' => $parametersToVerify,
                    'original_task_id' => $task->id,
                    'expected_values' => $setValues,
                ],
                'status' => 'pending',
            ]);

            Log::info('Queued verification task for set_params', [
                'device_id' => $device->id,
                'task_id' => $task->id,
                'parameters_to_verify' => count($parametersToVerify),
            ]);
        } else {
            // None matched - likely failed
            $task->markAsFailed(
                'Device reconnected without applying parameter changes. ' .
                'Verified: ' . $matchedCount . '/' . $totalParams . ' parameters.'
            );
        }
    }

    /**
     * Get the WiFi diagnostic parameter path for a device based on data model and manufacturer
     * Supports vendor-specific extensions for Alcatel-Lucent and Calix devices
     */
    private function getWiFiDiagnosticParameterPath(Device $device, string $type): string
    {
        $dataModel = $device->getDataModel();
        $isDevice2 = $dataModel === 'Device:2';

        if ($isDevice2) {
            // Device:2 model - standard TR-181 WiFi diagnostics
            return $type === 'state'
                ? 'Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'Device.WiFi.NeighboringWiFiDiagnostic.Result.';
        }

        // InternetGatewayDevice (TR-098) model - check for vendor-specific extensions

        // Alcatel-Lucent / Nokia devices (e.g., XS-2426X-A)
        if (in_array($device->manufacturer, ['ALCL', 'Nokia', 'Alcatel-Lucent'])) {
            return $type === 'state'
                ? 'InternetGatewayDevice.X_ALU-COM_NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'InternetGatewayDevice.X_ALU-COM_NeighboringWiFiDiagnostic.Result.';
        }

        // Calix devices (GigaSpire, GigaCenter, etc.)
        if ($device->oui === '000631' || stripos($device->manufacturer, 'Calix') !== false) {
            return $type === 'state'
                ? 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.DiagnosticsState'
                : 'InternetGatewayDevice.X_000631_Device.WiFi.NeighboringWiFiDiagnostic.Result.';
        }

        // Default to standard TR-098 WiFi diagnostics (if device supports it)
        // Note: Standard TR-098 doesn't have WiFi diagnostics, so this may not work for all devices
        return $type === 'state'
            ? 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Stats'
            : 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Stats.';
    }
}

