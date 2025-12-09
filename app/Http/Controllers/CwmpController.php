<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceType;
use App\Models\CwmpSession;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use App\Services\CwmpService;
use App\Services\ProvisioningService;
use App\Services\WorkflowExecutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CwmpController extends Controller
{
    public function __construct(
        private CwmpService $cwmpService,
        private ProvisioningService $provisioningService,
        private ConnectionRequestService $connectionRequestService,
        private WorkflowExecutionService $workflowExecutionService
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

            // Extract session info (cwmp:ID, CWMP namespace) before parsing
            // This is needed for proper response generation with matching namespace/ID
            $this->cwmpService->extractSessionInfo($xmlContent);

            // Determine message type and parse accordingly
            // Check for Response in element name (handles both <Response> and <Response/>)
            // Also check for TransferComplete which doesn't contain "Response" but is still a response
            $isResponse = str_contains($xmlContent, 'Response') || str_contains($xmlContent, 'TransferComplete');

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
                'GetRPCMethods' => $this->handleGetRPCMethodsRequest(),
                'GetParameterValues' => $this->handleGetParameterValuesResponse($parsed),
                'GetParameterNames' => $this->handleGetParameterNamesResponse($parsed),
                'SetParameterValues' => $this->handleSetParameterValuesResponse($parsed),
                'Reboot' => $this->handleRebootResponse($parsed),
                'FactoryReset' => $this->handleFactoryResetResponse($parsed),
                'TransferComplete' => $this->handleTransferCompleteResponse($parsed),
                'AddObject' => $this->handleAddObjectResponse($parsed),
                'DeleteObject' => $this->handleDeleteObjectResponse($parsed),
                'Fault' => $this->handleFaultResponse($parsed, $xmlContent),
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

        // Extract ModelName for better device identification (e.g., 854G-1, 844G-1 instead of just ONT)
        if (isset($parsed['parameters']['InternetGatewayDevice.DeviceInfo.ModelName'])) {
            $device->model_name = $parsed['parameters']['InternetGatewayDevice.DeviceInfo.ModelName']['value'];
        } elseif (isset($parsed['parameters']['Device.DeviceInfo.ModelName'])) {
            $device->model_name = $parsed['parameters']['Device.DeviceInfo.ModelName']['value'];
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

        // Store device ID in session for response handler matching
        // This ensures responses are matched to the correct device's tasks
        session(['cwmp_device_id' => $deviceId]);

        // Auto-provision new devices and queue "Get Everything" for full backup
        // The initial backup will be created after Get Everything completes (with all parameters)
        if (!$device->auto_provisioned) {
            $this->provisioningService->autoProvision($device, $parsed['events']);
            $device->update(['auto_provisioned' => true]);
            Log::info('Auto-provisioning triggered', [
                'device_id' => $device->id,
            ]);

            // Queue "Get Everything" task to discover all parameters for initial backup
            // This replaces the old immediate 8-parameter backup with a comprehensive one
            if (!$device->initial_backup_created) {
                $this->queueGetEverythingForInitialBackup($device);
            }
        }

        // Fetch connection request credentials if missing
        // This allows the ACS to send connection requests to the device
        if (empty($device->connection_request_username) || empty($device->connection_request_password)) {
            // Check if we already have a pending task to fetch credentials
            $hasPendingCredentialTask = $device->tasks()
                ->where('status', 'pending')
                ->where('description', 'Fetch connection request credentials')
                ->exists();

            if (!$hasPendingCredentialTask) {
                // Try both TR-098 and TR-181 paths
                $credentialParams = [
                    'InternetGatewayDevice.ManagementServer.ConnectionRequestUsername',
                    'InternetGatewayDevice.ManagementServer.ConnectionRequestPassword',
                    'Device.ManagementServer.ConnectionRequestUsername',
                    'Device.ManagementServer.ConnectionRequestPassword',
                ];

                Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'get_parameter_values',
                    'description' => 'Fetch connection request credentials',
                    'status' => 'pending',
                    'parameters' => $credentialParams,
                ]);

                Log::info('Queued task to fetch connection request credentials', [
                    'device_id' => $device->id,
                ]);
            }
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

        // Define diagnostic task types that need to wait for DIAGNOSTICS COMPLETE event
        $diagnosticTaskTypes = ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan'];

        // Check for boot events (M Reboot or 1 BOOT)
        // If device sends boot event, we can verify reboot succeeded without needing uptime value
        $hasBootEvent = collect($parsed['events'])->contains(function ($event) {
            return in_array($event['code'], ['M Reboot', '1 BOOT']);
        });

        // If boot event present, auto-complete any pending/sent uptime refresh tasks
        // The boot event itself proves the reboot succeeded
        if ($hasBootEvent) {
            $uptimeTasks = $device->tasks()
                ->whereIn('status', ['pending', 'sent'])
                ->where('task_type', 'get_params')
                ->get()
                ->filter(fn($task) => $this->isUptimeRefreshTask($task));

            foreach ($uptimeTasks as $uptimeTask) {
                $uptimeTask->markAsCompleted([
                    'verified_via' => 'boot_event',
                    'boot_events' => collect($parsed['events'])
                        ->filter(fn($e) => in_array($e['code'], ['M Reboot', '1 BOOT']))
                        ->pluck('code')
                        ->toArray(),
                ]);

                Log::info('Uptime refresh task auto-completed via boot event', [
                    'device_id' => $device->id,
                    'task_id' => $uptimeTask->id,
                    'events' => $parsed['events'],
                ]);
            }
        }

        // Check for tasks that were sent but never responded to
        // This can happen when a device starts a new session without sending responses
        // Only check tasks that were sent more than 10 seconds ago - give recent tasks time to complete
        $abandonedTasksQuery = $device->tasks()
            ->where('status', 'sent')
            ->where('sent_at', '<', now()->subSeconds(10));

        // If DIAGNOSTICS COMPLETE event is present, exclude diagnostic tasks
        // (they will be processed by queueDiagnosticResultRetrieval instead)
        if ($hasDiagnosticsComplete) {
            $abandonedTasksQuery->whereNotIn('task_type', $diagnosticTaskTypes);
        }

        $abandonedTasks = $abandonedTasksQuery->get();

        if ($abandonedTasks->isNotEmpty()) {
            foreach ($abandonedTasks as $abandonedTask) {
                // Special handling for set_params tasks
                // WiFi changes often cause device to disconnect/reconnect, but changes may have succeeded
                if ($abandonedTask->task_type === 'set_params') {
                    // Queue a verification task to check if parameters actually changed
                    $this->queueParameterVerification($device, $abandonedTask, $parsed['parameters']);
                } elseif ($this->isUptimeRefreshTask($abandonedTask)) {
                    // Special handling for post-reboot uptime refresh tasks
                    // Some devices (like Nokia Beacon G6) close the post-boot session before responding
                    // Give these tasks a retry instead of failing immediately
                    $this->handleUptimeRefreshRetry($device, $abandonedTask);
                } elseif (in_array($abandonedTask->task_type, $diagnosticTaskTypes)) {
                    // Diagnostic tasks need time to complete - device will send DIAGNOSTICS COMPLETE event later
                    // Only mark as failed if task has been sent for more than 3 minutes
                    $sentAt = $abandonedTask->sent_at ?? $abandonedTask->updated_at;
                    $minutesSinceSent = $sentAt ? now()->diffInMinutes($sentAt) : 0;

                    if ($minutesSinceSent >= 3) {
                        $abandonedTask->markAsFailed(
                            'Diagnostic task timed out after ' . $minutesSinceSent . ' minutes. ' .
                            'Device did not send DIAGNOSTICS COMPLETE event.'
                        );

                        Log::warning('Diagnostic task timed out', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'minutes_since_sent' => $minutesSinceSent,
                        ]);
                    } else {
                        Log::info('Diagnostic task still waiting for completion', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'minutes_since_sent' => $minutesSinceSent,
                        ]);
                    }
                } elseif ($abandonedTask->task_type === 'set_parameter_values' &&
                    is_array($abandonedTask->progress_info) &&
                    ($abandonedTask->progress_info['follow_up_from_add_object'] ?? false)) {
                    // Special handling for port mapping SetParameterValues follow-up tasks
                    // Nokia Beacon G6 and similar devices may close the session and reconnect
                    // instead of responding in the same session. Give more time before failing.
                    $sentAt = $abandonedTask->sent_at ?? $abandonedTask->updated_at;
                    $secondsSinceSent = $sentAt ? now()->diffInSeconds($sentAt) : 0;

                    // Give 30 seconds before marking as failed (device may need multiple reconnects)
                    if ($secondsSinceSent >= 30) {
                        $abandonedTask->markAsFailed(
                            'Port mapping configuration task timed out after ' . $secondsSinceSent . ' seconds. ' .
                            'Device may have rejected the parameters or disconnected during configuration.'
                        );

                        Log::warning('Port mapping follow-up task timed out', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'seconds_since_sent' => $secondsSinceSent,
                        ]);
                    } else {
                        Log::info('Port mapping follow-up task still waiting for response', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'seconds_since_sent' => $secondsSinceSent,
                        ]);
                    }
                } elseif ($abandonedTask->task_type === 'set_parameter_values' &&
                    $abandonedTask->description &&
                    str_contains($abandonedTask->description, 'WiFi:')) {
                    // Special handling for WiFi configuration tasks
                    // TR-181 Nokia Beacon G6 devices take ~2.5 minutes per radio to apply WiFi changes
                    // Device's periodic inform may fire during processing, creating a new session
                    // Give WiFi tasks 3 minutes before triggering verification (matches timeout command)
                    $sentAt = $abandonedTask->sent_at ?? $abandonedTask->updated_at;
                    $minutesSinceSent = $sentAt ? now()->diffInMinutes($sentAt) : 0;

                    if ($minutesSinceSent >= 3) {
                        $abandonedTask->markAsFailed(
                            'WiFi configuration task timed out after ' . $minutesSinceSent . ' minutes. ' .
                            'Device did not respond to WiFi parameter changes.'
                        );

                        Log::warning('WiFi configuration task timed out', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'description' => $abandonedTask->description,
                            'minutes_since_sent' => $minutesSinceSent,
                        ]);
                    } else {
                        Log::info('WiFi configuration task still processing', [
                            'device_id' => $device->id,
                            'task_id' => $abandonedTask->id,
                            'task_type' => $abandonedTask->task_type,
                            'description' => $abandonedTask->description,
                            'minutes_since_sent' => $minutesSinceSent,
                        ]);
                    }
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

                    // Notify workflow service about task failure
                    $this->workflowExecutionService->onTaskCompleted($abandonedTask);
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

        // Log all events to device history
        $this->logDeviceEvents($device, $parsed['events'], $request->ip(), $session->id);

        // Check for DIAGNOSTICS COMPLETE event and queue result retrieval
        foreach ($parsed['events'] as $event) {
            if ($event['code'] === '8 DIAGNOSTICS COMPLETE') {
                $this->queueDiagnosticResultRetrieval($device);
                break;
            }
        }

        // Auto-provision device based on events and rules
        $this->provisioningService->autoProvision($device, $parsed['events']);

        // Trigger on_connect workflows for this device
        $this->triggerOnConnectWorkflows($device);

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
        // First try with recent inform (within 5 minutes)
        $device = Device::where('ip_address', $request->ip())
            ->where('last_inform', '>=', now()->subMinutes(5))
            ->orderBy('last_inform', 'desc')
            ->first();

        // If not found, try with longer window (30 minutes) for devices that
        // send empty POSTs without a preceding Inform (e.g., Calix GigaSpire)
        if (!$device) {
            $device = Device::where('ip_address', $request->ip())
                ->where('last_inform', '>=', now()->subMinutes(30))
                ->orderBy('last_inform', 'desc')
                ->first();
        }

        if (!$device) {
            Log::warning('Empty POST from unknown device', ['ip' => $request->ip()]);
            return response('', 204)->header('Content-Type', 'text/xml; charset=utf-8');
        }

        // Check for pending tasks (with proper ordering - diagnostics before reboot)
        $pendingTask = $this->getNextPendingTask($device);

        if ($pendingTask) {
            Log::info('Sending queued RPC to device', [
                'device_id' => $device->id,
                'task_type' => $pendingTask->task_type,
            ]);

            // Mark task as sent
            $pendingTask->markAsSent();

            // Set device context for proper CWMP namespace selection
            // This ensures we use CWMP 1.2 for Calix/Nokia devices even without session info
            $this->cwmpService->setDeviceContext($device);

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
     * Handle GetRPCMethods request from device
     * Some devices (e.g., Calix GigaSpire) send GetRPCMethods to query ACS capabilities
     * before they will accept RPC commands from the ACS.
     */
    private function handleGetRPCMethodsRequest(): string
    {
        Log::info('Device requested GetRPCMethods - responding with supported methods');

        return $this->cwmpService->createGetRPCMethodsResponse();
    }

    /**
     * Handle GetParameterValuesResponse
     */
    private function handleGetParameterValuesResponse(array $parsed): string
    {
        // Find the task that was sent (get_params, discover_troubleshooting, get_diagnostic_results, verify_set_params, or get_parameter_values)
        $task = $this->findSentTaskForSession(['get_params', 'discover_troubleshooting', 'get_diagnostic_results', 'verify_set_params', 'get_parameter_values']);

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
                        // Use markAsCompleted() to trigger any follow-up actions (like queuing upload after download)
                        $diagnosticTask->markAsCompleted($parsed['parameters']);

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

                // Chunk parameters for devices that can't handle large requests
                // SmartRG devices use partial path queries (one path per request like USS)
                $isSmartRG = strtolower($device->manufacturer ?? '') === 'smartrg' ||
                    strtoupper($device->oui ?? '') === 'E82C6D';

                // SmartRG: 1 partial path per chunk (each path returns many params)
                // Other devices: 20 individual params per chunk
                $chunkSize = $isSmartRG ? 1 : 20;
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

                // Notify workflow service about task completion
                $this->workflowExecutionService->onTaskCompleted($task);

                Log::info('GetParameterValues completed', [
                    'device_id' => $device->id,
                    'param_count' => count($parsed['parameters']),
                ]);

                // Check if this is a WiFi verification task
                if ($this->isWifiVerificationTask($task)) {
                    $this->processWifiVerification($task, $parsed['parameters']);
                }
            }
        }

        // Check for more pending tasks (with proper ordering)
        if ($task && $task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                // SmartRG devices drop sessions after ~2 minutes
                // End session after each task and trigger new connection
                $isSmartRG = strtolower($device->manufacturer ?? '') === 'smartrg' ||
                    strtoupper($device->oui ?? '') === 'E82C6D';

                if ($isSmartRG) {
                    Log::info('SmartRG: ending session, triggering new connection for next task', [
                        'device_id' => $device->id,
                        'next_task_id' => $nextTask->id,
                    ]);
                    $this->connectionRequestService->sendConnectionRequest($device);
                    return $this->cwmpService->createEmptyResponse();
                }

                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
                return $this->generateRpcForTask($nextTask);
            } else {
                // No more tasks - check if we need to create initial backup
                $this->createInitialBackupIfNeeded($device);
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
        $task = $this->findSentTaskForSession(['get_parameter_names']);

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

        // Check for more pending tasks (with proper ordering)
        if ($task && $task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
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
        // Find the task that was sent (set_params, set_parameter_values, download/upload diagnostics, ping_diagnostics, traceroute_diagnostics, or wifi_scan)
        $task = $this->findSentTaskForSession(['set_params', 'set_parameter_values', 'ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan']);

        if ($task) {
            if ($parsed['status'] === 0) {
                // For diagnostic tasks, keep them as 'sent' until we get the "8 DIAGNOSTICS COMPLETE" event
                // and retrieve the actual results
                if (in_array($task->task_type, ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan'])) {
                    Log::info('Diagnostic task acknowledged by device, awaiting completion', [
                        'device_id' => $task->device_id,
                        'task_type' => $task->task_type,
                    ]);
                } else {
                    // Use workflow-aware completion
                    $this->completeTaskWithWorkflowNotification($task, ['status' => $parsed['status']]);
                    Log::info('SetParameterValues completed', ['device_id' => $task->device_id]);

                    // If this was a port mapping configuration, save the parameters to database
                    // The UI will reload from database - no need to fetch from device again
                    if ($task->description === 'Configure port mapping' ||
                        (is_array($task->parameters) &&
                         collect($task->parameters)->keys()->first() &&
                         str_contains(collect($task->parameters)->keys()->first(), 'PortMapping'))) {

                        $device = $task->device;

                        // Store the port mapping parameters that were just set
                        foreach ($task->parameters as $name => $paramData) {
                            $value = is_array($paramData) ? ($paramData['value'] ?? '') : $paramData;
                            $type = is_array($paramData) ? ($paramData['type'] ?? 'xsd:string') : 'xsd:string';

                            $device->parameters()->updateOrCreate(
                                ['name' => $name],
                                [
                                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                                    'type' => $type,
                                ]
                            );
                        }

                        Log::info('Port mapping parameters saved to database', [
                            'device_id' => $device->id,
                            'parameter_count' => count($task->parameters),
                        ]);
                    }
                }
            } else {
                $task->markAsFailed('SetParameterValues failed with status: ' . $parsed['status']);
                Log::warning('SetParameterValues failed', ['device_id' => $task->device_id, 'status' => $parsed['status']]);
            }
        }

        // Check for more pending tasks (with proper ordering)
        if ($task && $task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
                return $this->generateRpcForTask($nextTask);
            }

            // No immediate next task, but check if there are tasks waiting for next session
            // If so, schedule a connection request after a delay to wake up the device
            $waitingTasks = $task->device->tasks()
                ->where('status', 'pending')
                ->whereNotNull('progress_info')
                ->get()
                ->filter(function ($t) {
                    return is_array($t->progress_info) && ($t->progress_info['wait_for_next_session'] ?? false);
                });

            if ($waitingTasks->isNotEmpty()) {
                Log::info('Tasks waiting for next session, scheduling connection request', [
                    'device_id' => $task->device->id,
                    'waiting_task_count' => $waitingTasks->count(),
                ]);

                // Dispatch a delayed connection request (4 second delay)
                // This ensures the task ages past the 3-second window before the device reconnects
                $deviceId = $task->device_id;
                $connectionRequestService = $this->connectionRequestService;
                dispatch(function () use ($deviceId, $connectionRequestService) {
                    try {
                        $device = Device::find($deviceId);
                        if ($device) {
                            $connectionRequestService->sendConnectionRequest($device);
                            Log::info('Delayed connection request sent for waiting tasks', [
                                'device_id' => $device->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to send delayed connection request', [
                            'device_id' => $deviceId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                })->delay(now()->addSeconds(4));
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
        $task = $this->findSentTaskForSession(['reboot']);

        if ($task) {
            $this->completeTaskWithWorkflowNotification($task);
            Log::info('Reboot command sent', ['device_id' => $task->device_id]);

            // Queue a task to refresh uptime after reboot completes
            $device = $task->device;
            $dataModel = $device->getDataModel();
            $uptimeParam = $dataModel === 'TR-181'
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
        $task = $this->findSentTaskForSession(['factory_reset']);

        if ($task) {
            $this->completeTaskWithWorkflowNotification($task);
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
        $task = $this->findSentTaskForSession(['download', 'upload', 'config_restore']);
        $commandKey = $parsed['command_key'] ?? null;

        // If no sent task found, this could be:
        // 1. A duplicate TransferComplete for an already-completed task
        // 2. A TransferComplete for a deleted task
        // 3. A stale TransferComplete from a previous ACS session
        // 4. A TransferComplete that arrived in a new session after the device disconnected mid-transfer
        // Try to recover the task using the command_key which now contains the task ID
        if (!$task) {
            $deviceId = $this->getSessionDeviceId();
            $recoveredTask = null;

            // Try to extract task ID from command_key (format: download_{taskId}_{timestamp} or upload_{taskId}_{timestamp})
            if ($commandKey && preg_match('/^(download|upload)_(\d+)_\d+$/', $commandKey, $matches)) {
                $taskId = (int) $matches[2];
                $recoveredTask = Task::find($taskId);

                // Verify this task belongs to the current device and is in a recoverable state
                if ($recoveredTask) {
                    $currentDevice = $this->getSessionDevice();
                    if ($currentDevice && $recoveredTask->device_id === $currentDevice->id &&
                        in_array($recoveredTask->status, ['failed', 'sent'])) {
                        $task = $recoveredTask;
                        Log::info('TransferComplete recovered orphaned task via command_key', [
                            'device_id' => $deviceId,
                            'task_id' => $taskId,
                            'command_key' => $commandKey,
                            'previous_status' => $recoveredTask->status,
                        ]);
                    } else {
                        $recoveredTask = null; // Don't recover if device mismatch or wrong status
                    }
                }
            }

            if (!$task) {
                Log::info('TransferComplete received without matching sent task (orphan/duplicate)', [
                    'device_id' => $deviceId,
                    'command_key' => $commandKey,
                    'fault_code' => $parsed['fault_code'] ?? 0,
                    'fault_string' => $parsed['fault_string'] ?? '',
                ]);
                // Send proper TransferCompleteResponse to acknowledge and stop device retries
                return $this->cwmpService->createTransferCompleteResponse();
            }
        }

        if ($task) {
            $faultCode = $parsed['fault_code'] ?? 0;
            $faultString = $parsed['fault_string'] ?? '';
            $startTime = $parsed['start_time'] ?? null;
            $completeTime = $parsed['complete_time'] ?? null;

            // Calculate transfer speed if we have timing data and file size
            // For downloads: file_size is in parameters (known before transfer)
            // For uploads: file_size is in progress_info (set by DeviceUploadController when file is received)
            $speedMbps = null;
            $transferDuration = null;
            $fileSizeBytes = $task->parameters['file_size']
                ?? $task->progress_info['file_size']
                ?? null;

            if ($startTime && $completeTime && $fileSizeBytes) {
                try {
                    $start = new \DateTime($startTime);
                    $complete = new \DateTime($completeTime);
                    $transferDuration = $complete->getTimestamp() - $start->getTimestamp();
                    if ($transferDuration > 0) {
                        $fileSizeBytes = (int) $fileSizeBytes;
                        $speedBps = ($fileSizeBytes * 8) / $transferDuration; // bits per second
                        $speedMbps = round($speedBps / 1000000, 2); // Megabits per second
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse transfer times', ['error' => $e->getMessage()]);
                }
            }

            // Build result data
            $resultData = [
                'fault_code' => $faultCode,
                'fault_string' => $faultString,
                'start_time' => $startTime,
                'complete_time' => $completeTime,
                'transfer_duration_seconds' => $transferDuration,
                'speed_mbps' => $speedMbps,
            ];

            if ($faultCode === 0) {
                $task->markAsCompleted($resultData);

                Log::info('Transfer completed successfully', [
                    'device_id' => $task->device_id,
                    'task_type' => $task->task_type,
                    'start_time' => $startTime,
                    'complete_time' => $completeTime,
                    'speed_mbps' => $speedMbps,
                ]);
            } else {
                // For speed tests with validation errors, mark as completed (file was transferred but rejected)
                // Only count validation errors like "Invalid image format", not connection failures
                $isSpeedTest = str_contains($task->parameters['description'] ?? '', 'Speed test');
                $isValidationError = str_contains($faultString, 'Invalid') || str_contains($faultString, 'format');
                $hasReasonableSpeed = $speedMbps !== null && $speedMbps < 2000; // Sanity check: less than 2 Gbps

                // For uploads that report error 9011 but file was actually received successfully
                // This is a known behavior with some Calix/GigaSpire devices that report upload errors
                // even when our server received the file and responded with HTTP 200
                $isUploadTask = $task->task_type === 'upload';
                $fileWasReceived = !empty($task->progress_info['uploaded_file']) && !empty($task->progress_info['file_size']);
                $isUploadErrorCode = $faultCode === 9011; // TR-069 Upload failure code

                if ($isSpeedTest && $isValidationError && $hasReasonableSpeed && $transferDuration > 0) {
                    $resultData['note'] = 'Speed test successful - validation error ignored';
                    $task->markAsCompleted($resultData);

                    Log::info('Speed test completed (validation error ignored)', [
                        'device_id' => $task->device_id,
                        'speed_mbps' => $speedMbps,
                        'fault_code' => $faultCode,
                        'fault_string' => $faultString,
                    ]);
                } elseif ($isUploadTask && $fileWasReceived && $isUploadErrorCode) {
                    // File was successfully received by our server - mark as completed despite device error
                    $resultData['note'] = 'Upload successful - device reported error but file was received';
                    $resultData['uploaded_file'] = $task->progress_info['uploaded_file'];
                    $resultData['file_size'] = $task->progress_info['file_size'];
                    $task->markAsCompleted($resultData);

                    Log::info('Upload completed (device error ignored - file received)', [
                        'device_id' => $task->device_id,
                        'file_size' => $task->progress_info['file_size'],
                        'uploaded_file' => $task->progress_info['uploaded_file'],
                        'fault_code' => $faultCode,
                        'fault_string' => $faultString,
                    ]);
                } else {
                    $task->markAsFailed("Transfer failed (FaultCode {$faultCode}): {$faultString}", $resultData);

                    Log::warning('Transfer failed', [
                        'device_id' => $task->device_id,
                        'task_type' => $task->task_type,
                        'fault_code' => $faultCode,
                        'fault_string' => $faultString,
                        'speed_mbps' => $speedMbps,
                    ]);
                }
            }
        }

        // Send proper TransferCompleteResponse to acknowledge and stop device retries
        // Note: Device will continue session after receiving this and may send more data or end session
        return $this->cwmpService->createTransferCompleteResponse();
    }

    /**
     * Handle AddObjectResponse (for creating object instances like PortMapping)
     */
    private function handleAddObjectResponse(array $parsed): string
    {
        $task = $this->findSentTaskForSession(['add_object']);

        if ($task) {
            $instanceNumber = $parsed['instance_number'] ?? null;
            $status = $parsed['status'] ?? 1;

            if ($status === 0 && $instanceNumber) {
                // Success - mark task as completed with instance number
                $task->markAsCompleted([
                    'instance_number' => $instanceNumber,
                    'status' => $status,
                ]);

                Log::info('AddObject succeeded', [
                    'device_id' => $task->device_id,
                    'object_name' => $task->parameters['object_name'] ?? '',
                    'instance_number' => $instanceNumber,
                ]);

                // Check if this is a port mapping creation with follow-up parameters
                if (!empty($task->parameters['follow_up_parameters'])) {
                    $device = $task->device;
                    $followUpParams = $task->parameters['follow_up_parameters'];
                    $objectPrefix = rtrim($task->parameters['object_name'] ?? '', '.');

                    // Replace {instance} placeholder with actual instance number
                    $parameters = [];
                    foreach ($followUpParams as $key => $value) {
                        $newKey = str_replace('{instance}', $instanceNumber, $key);
                        $parameters[$newKey] = $value;
                    }

                    // Create the follow-up SetParameterValues task
                    // Mark it to wait for next session (SmartRG one-task-per-session limitation)
                    $followUpTask = Task::create([
                        'device_id' => $device->id,
                        'task_type' => 'set_parameter_values',
                        'description' => 'Configure port mapping',
                        'status' => 'pending',
                        'parameters' => $parameters,
                        'progress_info' => [
                            'wait_for_next_session' => true,
                            'follow_up_from_add_object' => true,
                        ],
                    ]);

                    Log::info('Created follow-up SetParameterValues task (will execute on next session)', [
                        'device_id' => $device->id,
                        'task_id' => $followUpTask->id,
                        'instance_number' => $instanceNumber,
                    ]);

                    // Send connection request to wake up device for the follow-up task
                    // Delay slightly so the task ages past the wait_for_next_session window (3 seconds)
                    // This ensures the device connects AFTER the task is old enough to be sent
                    // Using sleep(4) to give a 1-second buffer beyond the 3-second window
                    sleep(4);
                    $this->connectionRequestService->sendConnectionRequest($device);
                }
            } else {
                // Failed
                $task->markAsFailed(
                    'AddObject failed with status: ' . $status
                );

                Log::warning('AddObject failed', [
                    'device_id' => $task->device_id,
                    'status' => $status,
                ]);
            }
        }

        // Check for more pending tasks
        if ($task && $task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
                return $this->generateRpcForTask($nextTask);
            }
        }

        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle DeleteObjectResponse
     */
    private function handleDeleteObjectResponse(array $parsed): string
    {
        $task = $this->findSentTaskForSession(['delete_object']);

        if ($task) {
            $status = $parsed['status'] ?? 1;

            if ($status === 0) {
                $task->markAsCompleted(['status' => $status]);

                $objectName = $task->parameters['object_name'] ?? '';

                Log::info('DeleteObject succeeded', [
                    'device_id' => $task->device_id,
                    'object_name' => $objectName,
                ]);

                // Clean up parameters from database for the deleted object
                // The object_name should end with a trailing dot (e.g., "...PortMapping.1.")
                // We delete all parameters that start with this path
                if (!empty($objectName) && $task->device) {
                    // Remove trailing dot if present for the LIKE query
                    $basePath = rtrim($objectName, '.');

                    $deletedCount = $task->device->parameters()
                        ->where('name', 'LIKE', $basePath . '.%')
                        ->delete();

                    Log::info('Cleaned up parameters for deleted object', [
                        'device_id' => $task->device_id,
                        'object_path' => $basePath,
                        'parameters_deleted' => $deletedCount,
                    ]);
                }
            } else {
                $task->markAsFailed(
                    'DeleteObject failed with status: ' . $status
                );

                Log::warning('DeleteObject failed', [
                    'device_id' => $task->device_id,
                    'status' => $status,
                ]);
            }
        }

        // Check for more pending tasks
        if ($task && $task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
                return $this->generateRpcForTask($nextTask);
            }
        }

        return $this->cwmpService->createEmptyResponse();
    }

    /**
     * Handle SOAP Fault response from device
     * This is sent when the device cannot execute a command (invalid parameters, etc.)
     */
    private function handleFaultResponse(array $parsed, string $xmlContent): string
    {
        // Parse the fault details from the XML
        $faultCode = 'Unknown';
        $faultString = 'Unknown fault';
        $parameterFaults = [];

        // Extract fault information using regex (simpler than full XML parsing)
        if (preg_match('/<FaultCode>(\d+)<\/FaultCode>/', $xmlContent, $m)) {
            $faultCode = $m[1];
        }
        if (preg_match('/<FaultString>([^<]+)<\/FaultString>/', $xmlContent, $m)) {
            $faultString = $m[1];
        }

        // Extract SetParameterValuesFault entries (for parameter-specific errors)
        if (preg_match_all('/<SetParameterValuesFault>.*?<ParameterName>([^<]+)<\/ParameterName>.*?<FaultCode>(\d+)<\/FaultCode>.*?<FaultString>([^<]+)<\/FaultString>.*?<\/SetParameterValuesFault>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $parameterFaults[] = [
                    'parameter' => $match[1],
                    'code' => $match[2],
                    'message' => $match[3],
                ];
            }
        }

        Log::warning('SOAP Fault received from device', [
            'fault_code' => $faultCode,
            'fault_string' => $faultString,
            'parameter_faults' => $parameterFaults,
        ]);

        // Find the most recent 'sent' task for this device and mark it as failed
        // Try session-based lookup first, then fall back to IP-based lookup
        $deviceId = $this->getSessionDeviceId();
        $task = null;

        if ($deviceId) {
            $task = Task::where('status', 'sent')
                ->where('device_id', $deviceId)
                ->orderBy('updated_at', 'desc')
                ->first();
        }

        // Fallback: Try to find device by IP address if session didn't work
        if (!$task) {
            $clientIp = request()->ip();
            if ($clientIp) {
                $device = Device::where('ip_address', $clientIp)->first();
                if ($device) {
                    $task = Task::where('status', 'sent')
                        ->where('device_id', $device->id)
                        ->orderBy('updated_at', 'desc')
                        ->first();

                    if ($task) {
                        Log::info('Found task for SOAP Fault via IP fallback', [
                            'device_id' => $device->id,
                            'task_id' => $task->id,
                            'ip' => $clientIp,
                        ]);
                    }
                }
            }
        }

        if (!$task) {
            Log::warning('Cannot associate SOAP Fault with a task - no device ID in session and IP fallback failed', [
                'fault_code' => $faultCode,
                'fault_string' => $faultString,
                'ip' => request()->ip(),
            ]);
            return $this->cwmpService->createEmptyResponse();
        }

        // Task found - mark it as failed with detailed error message
        $errorMessage = "SOAP Fault {$faultCode}: {$faultString}";

        if (!empty($parameterFaults)) {
            $paramErrors = array_map(function ($pf) {
                return "{$pf['parameter']} ({$pf['code']}: {$pf['message']})";
            }, $parameterFaults);
            $errorMessage .= ' - Invalid parameters: ' . implode(', ', $paramErrors);
        }

        $task->markAsFailed($errorMessage);

        Log::warning('Task failed due to SOAP Fault', [
            'task_id' => $task->id,
            'task_type' => $task->task_type,
            'device_id' => $task->device_id,
            'error' => $errorMessage,
        ]);

        // Check for more pending tasks (other tasks may still work)
        if ($task->device) {
            $device = $task->device;
            $nextTask = $this->getNextPendingTask($device);

            if ($nextTask) {
                $nextTask->markAsSent();
                // Set device context for proper CWMP namespace
                $this->cwmpService->setDeviceContext($device);
                return $this->generateRpcForTask($nextTask);
            }
        }

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
            // get_parameter_values: parameters can be flat array or have 'names' key
            'get_parameter_values' => $this->cwmpService->createGetParameterValues(
                $task->parameters['names'] ?? $task->parameters ?? []
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
                $task->parameters['password'] ?? '',
                $task->id  // Include task ID in command_key for orphan recovery
            ),
            'upload' => $this->cwmpService->createUpload(
                $task->parameters['url'] ?? '',
                $task->parameters['file_type'] ?? '3 Vendor Log File',
                $task->parameters['username'] ?? '',
                $task->parameters['password'] ?? '',
                $task->id  // Include task ID in command_key for orphan recovery
            ),
            'config_restore' => $this->cwmpService->createDownload(
                $task->parameters['url'] ?? '',
                $task->parameters['file_type'] ?? '3 Vendor Configuration File',
                $task->parameters['username'] ?? '',
                $task->parameters['password'] ?? '',
                $task->id  // Include task ID in command_key for orphan recovery
            ),
            'ping_diagnostics' => $this->generatePingDiagnostics($task),
            'traceroute_diagnostics' => $this->generateTracerouteDiagnostics($task),
            'add_object' => $this->cwmpService->createAddObject(
                $task->parameters['object_name'] ?? ''
            ),
            'delete_object' => $this->cwmpService->createDeleteObject(
                $task->parameters['object_name'] ?? ''
            ),
            default => $this->cwmpService->createEmptyResponse(),
        };
    }

    /**
     * Generate Ping Diagnostics SetParameterValues request
     */
    private function generatePingDiagnostics(Task $task): string
    {
        $dataModel = $task->device->getDataModel();
        // TR-181 uses Device.IP.Diagnostics.IPPing (not IPPingDiagnostics)
        $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.IPPing' : 'InternetGatewayDevice.IPPingDiagnostics';

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
        // TR-181 uses Device.IP.Diagnostics.TraceRoute (not TraceRouteDiagnostics)
        $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.TraceRoute' : 'InternetGatewayDevice.TraceRouteDiagnostics';

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
            // TR-181 uses Device.IP.Diagnostics.IPPing (not IPPingDiagnostics)
            $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.IPPing' : 'InternetGatewayDevice.IPPingDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.SuccessCount",
                "{$prefix}.FailureCount",
                "{$prefix}.AverageResponseTime",
                "{$prefix}.MinimumResponseTime",
                "{$prefix}.MaximumResponseTime",
            ];
        } elseif ($taskType === 'traceroute_diagnostics') {
            // TR-181 uses Device.IP.Diagnostics.TraceRoute (not TraceRouteDiagnostics)
            $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.TraceRoute' : 'InternetGatewayDevice.TraceRouteDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.ResponseTime",
                "{$prefix}.RouteHopsNumberOfEntries",
                "{$prefix}.RouteHops.",  // Partial path query to get all hop entries
            ];
        } elseif ($taskType === 'download_diagnostics') {
            $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.DownloadDiagnostics' : 'InternetGatewayDevice.DownloadDiagnostics';
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
            $prefix = $dataModel === 'TR-181' ? 'Device.IP.Diagnostics.UploadDiagnostics' : 'InternetGatewayDevice.UploadDiagnostics';
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
        $isDevice2 = $dataModel === 'TR-181';

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

    /**
     * Get the next pending task for a device with proper ordering
     * - Diagnostic tasks in 'sent' status block reboot/factory_reset
     * - Reboot/factory_reset always execute last
     */
    private function getNextPendingTask(Device $device): ?Task
    {
        $destructiveTaskTypes = ['reboot', 'factory_reset'];
        $diagnosticTaskTypes = ['ping_diagnostics', 'traceroute_diagnostics', 'download_diagnostics', 'upload_diagnostics', 'wifi_scan'];

        // Check for tasks that were 'sent' very recently but device sent GetRPCMethods instead of responding
        // These tasks need to be resent (they were marked 'sent' in the last 30 seconds)
        $recentlySentTask = $device->tasks()
            ->where('status', 'sent')
            ->where('updated_at', '>=', now()->subSeconds(30))
            ->whereNotIn('task_type', $diagnosticTaskTypes) // Don't resend diagnostics as they may still be processing
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($recentlySentTask) {
            Log::info('Resending recently sent task (device may have sent GetRPCMethods first)', [
                'device_id' => $device->id,
                'task_id' => $recentlySentTask->id,
                'task_type' => $recentlySentTask->task_type,
                'sent_seconds_ago' => $recentlySentTask->updated_at->diffInSeconds(now()),
            ]);
            // Reset to pending so markAsSent() works correctly
            $recentlySentTask->update(['status' => 'pending']);
            return $recentlySentTask;
        }

        // Check if there are diagnostic tasks waiting for completion (in 'sent' status)
        $hasPendingDiagnostics = $device->tasks()
            ->where('status', 'sent')
            ->whereIn('task_type', $diagnosticTaskTypes)
            ->exists();

        // Build query for pending tasks
        $query = $device->tasks()
            ->where('status', 'pending')
            ->orderBy('created_at');

        // If diagnostics are pending, exclude destructive tasks
        if ($hasPendingDiagnostics) {
            $query->whereNotIn('task_type', $destructiveTaskTypes);

            Log::info('Holding destructive tasks until diagnostics complete', [
                'device_id' => $device->id,
            ]);
        }

        // Get all pending non-destructive tasks
        $pendingTasks = (clone $query)
            ->whereNotIn('task_type', $destructiveTaskTypes)
            ->get();

        // Filter out tasks marked to wait for next session if they were just created
        $nextTask = $pendingTasks->first(function ($task) {
            // Check if task is marked to wait for next session
            if (is_array($task->progress_info) &&
                ($task->progress_info['wait_for_next_session'] ?? false)) {

                // Only skip if task was created within the last 3 seconds
                // This prevents sending the task in the same CWMP session that created it
                // A typical CWMP session (Inform -> InformResponse -> EmptyPost -> RPC) takes 1-2 seconds
                $createdSeconds = $task->created_at->diffInSeconds(now());
                if ($createdSeconds < 3) {
                    Log::info('Skipping task marked to wait for next session', [
                        'task_id' => $task->id,
                        'task_type' => $task->task_type,
                        'created_seconds_ago' => $createdSeconds,
                    ]);
                    return false; // Skip this task
                }
            }
            return true; // Use this task
        });

        // If no non-destructive tasks, get destructive tasks (only if no pending diagnostics)
        if (!$nextTask && !$hasPendingDiagnostics) {
            $nextTask = $device->tasks()
                ->where('status', 'pending')
                ->whereIn('task_type', $destructiveTaskTypes)
                ->orderBy('created_at')
                ->first();
        }

        return $nextTask;
    }

    /**
     * Check if a task is an uptime refresh task (created after reboot)
     */
    private function isUptimeRefreshTask(Task $task): bool
    {
        // Must be a get_params task
        if ($task->task_type !== 'get_params') {
            return false;
        }

        // Check description
        if ($task->description && stripos($task->description, 'uptime') !== false) {
            return true;
        }

        // Check parameters for UpTime
        if (is_array($task->parameters)) {
            foreach ($task->parameters as $paramName => $paramValue) {
                if (stripos($paramName, 'UpTime') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle retry logic for uptime refresh tasks
     * Some devices (like Nokia Beacon G6) close the post-boot session before responding
     * to commands, so we give uptime refresh tasks a chance to retry
     */
    private function handleUptimeRefreshRetry(Device $device, Task $task): void
    {
        $maxRetries = 2;

        // Get current retry count from progress_info
        $progressInfo = $task->progress_info ?? [];
        $retryCount = $progressInfo['uptime_retry_count'] ?? 0;

        if ($retryCount < $maxRetries) {
            // Increment retry count and reset task to pending
            $progressInfo['uptime_retry_count'] = $retryCount + 1;
            $progressInfo['last_retry_at'] = now()->toDateTimeString();

            $task->update([
                'status' => 'pending',
                'progress_info' => $progressInfo,
                'sent_at' => null,
            ]);

            Log::info('Uptime refresh task queued for retry', [
                'device_id' => $device->id,
                'task_id' => $task->id,
                'retry_count' => $retryCount + 1,
                'max_retries' => $maxRetries,
            ]);
        } else {
            // Max retries reached, mark as failed
            $task->markAsFailed(
                'Post-reboot uptime refresh failed after ' . $maxRetries . ' retries. ' .
                'Device may be closing sessions before responding to commands.'
            );

            Log::warning('Uptime refresh task failed after max retries', [
                'device_id' => $device->id,
                'task_id' => $task->id,
                'retry_count' => $retryCount,
            ]);
        }
    }

    /**
     * Create initial backup if Get Everything has completed and backup doesn't exist yet
     */
    private function createInitialBackupIfNeeded(Device $device): void
    {
        // Skip if initial backup already exists
        if ($device->initial_backup_created) {
            return;
        }

        // Only create backup if we have a meaningful number of parameters
        // (more than just the 8 from Inform)
        $paramCount = $device->parameters()->count();
        if ($paramCount < 50) {
            return;
        }

        // Create the full initial backup
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
            'name' => 'Initial Backup - ' . now()->format('Y-m-d H:i:s'),
            'description' => 'Automatically created on first TR-069 connection to preserve device configuration',
            'backup_data' => $parameters,
            'is_auto' => true,
            'parameter_count' => count($parameters),
        ]);

        $device->update([
            'initial_backup_created' => true,
            'last_backup_at' => now(),
        ]);

        Log::info('Initial backup created after Get Everything completed', [
            'device_id' => $device->id,
            'parameter_count' => count($parameters),
        ]);
    }

    /**
     * Queue "Get Everything" task to discover all parameters for initial backup
     */
    private function queueGetEverythingForInitialBackup(Device $device): void
    {
        // Check if already has a pending get_parameter_names task
        $existingTask = $device->tasks()
            ->where('task_type', 'get_parameter_names')
            ->whereIn('status', ['pending', 'sent'])
            ->exists();

        if ($existingTask) {
            Log::info('Skipping Get Everything - task already exists', [
                'device_id' => $device->id,
            ]);
            return;
        }

        // Determine data model root
        $dataModel = $device->getDataModel();
        $root = $dataModel === 'TR-181' ? 'Device.' : 'InternetGatewayDevice.';

        // Create the discovery task
        Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_parameter_names',
            'description' => 'Initial parameter discovery for backup',
            'parameters' => [
                'path' => $root,
                'next_level' => false, // Get ALL parameters recursively
                'for_initial_backup' => true, // Flag to trigger backup creation when complete
            ],
            'status' => 'pending',
        ]);

        Log::info('Queued Get Everything for initial backup', [
            'device_id' => $device->id,
            'data_model' => $dataModel,
            'root' => $root,
        ]);
    }

    /**
     * Find the correct PortMapping parameter path for a device
     * Returns the full path like: InternetGatewayDevice.WANDevice.2.WANConnectionDevice.2.WANIPConnection.6.PortMapping
     */
    private function findPortMappingPath(Device $device): ?string
    {
        // Search for the active WAN connection with a valid external IP
        $wanConnections = $device->parameters()
            ->where('name', 'LIKE', '%WANIPConnection%.ExternalIPAddress')
            ->where('value', '!=', '')
            ->where('value', '!=', '0.0.0.0')
            ->get();

        foreach ($wanConnections as $ipParam) {
            // Extract the WAN path
            if (preg_match('/(InternetGatewayDevice\.WANDevice\.\d+\.WANConnectionDevice\.\d+\.WANIPConnection\.\d+)\.ExternalIPAddress/', $ipParam->name, $matches)) {
                $wanPath = $matches[1];

                // Check if this is a public IP (not 192.168.x.x which is MER "back door")
                if (!str_starts_with($ipParam->value, '192.168.')) {
                    return $wanPath . '.PortMapping';
                }
            }
        }

        // Fallback: return first WAN connection found (even if private IP)
        if (isset($wanPath)) {
            return $wanPath . '.PortMapping';
        }

        return null;
    }

    /**
     * Log all events from an Inform to device event history
     */
    private function logDeviceEvents(Device $device, array $events, string $sourceIp, int $sessionId): void
    {
        foreach ($events as $event) {
            $eventCode = $event['code'] ?? 'unknown';
            $commandKey = $event['command_key'] ?? null;

            // Build details array for special events
            $details = [];

            // For TRANSFER COMPLETE, include the command key for correlation
            if ($eventCode === '7 TRANSFER COMPLETE' && $commandKey) {
                $details['command_key'] = $commandKey;
                $this->handleTransferComplete($device, $commandKey);
            }

            // For DIAGNOSTICS COMPLETE, note which diagnostic completed
            if ($eventCode === '8 DIAGNOSTICS COMPLETE' && $commandKey) {
                $details['command_key'] = $commandKey;
            }

            // For VALUE CHANGE, we could log which parameter changed if available
            if ($eventCode === '4 VALUE CHANGE') {
                // The parameter name is in the command_key for some devices
                if ($commandKey) {
                    $details['parameter'] = $commandKey;
                }
            }

            DeviceEvent::create([
                'device_id' => $device->id,
                'event_code' => $eventCode,
                'event_type' => DeviceEvent::normalizeEventCode($eventCode),
                'command_key' => $commandKey,
                'details' => !empty($details) ? $details : null,
                'source_ip' => $sourceIp,
                'session_id' => (string) $sessionId,
            ]);
        }

        Log::debug('Device events logged', [
            'device_id' => $device->id,
            'event_count' => count($events),
        ]);
    }

    /**
     * Handle TRANSFER COMPLETE event - update firmware status, mark tasks complete
     */
    private function handleTransferComplete(Device $device, string $commandKey): void
    {
        // Find the task that initiated this transfer
        $task = $device->tasks()
            ->where('command_key', $commandKey)
            ->whereIn('task_type', ['download', 'firmware_upgrade'])
            ->whereIn('status', ['sent', 'in_progress'])
            ->first();

        if ($task) {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => ['transfer_complete' => true, 'completed_at' => now()->toIso8601String()],
            ]);

            Log::info('Transfer complete - task marked as completed', [
                'device_id' => $device->id,
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'command_key' => $commandKey,
            ]);

            // Notify workflow execution service of task completion
            $this->workflowExecutionService->onTaskCompleted($task);

            // Queue a task to refresh device info (get updated software version)
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'get_parameter_values',
                'description' => 'Refresh device info after firmware upgrade',
                'status' => 'pending',
                'parameters' => $device->getDataModel() === 'TR-181'
                    ? ['Device.DeviceInfo.SoftwareVersion', 'Device.DeviceInfo.HardwareVersion']
                    : ['InternetGatewayDevice.DeviceInfo.SoftwareVersion', 'InternetGatewayDevice.DeviceInfo.HardwareVersion'],
            ]);
        } else {
            Log::info('Transfer complete event received (no matching task)', [
                'device_id' => $device->id,
                'command_key' => $commandKey,
            ]);
        }
    }

    /**
     * Get the current session device ID for task matching
     * Returns the device ID stored during Inform handling
     */
    private function getSessionDeviceId(): ?string
    {
        return session('cwmp_device_id');
    }

    /**
     * Find a sent task for the current session device
     * This prevents matching tasks from other concurrent device sessions
     */
    private function findSentTaskForSession(array $taskTypes): ?Task
    {
        $deviceId = $this->getSessionDeviceId();

        // First try: If we have a device ID from the session, use it
        if ($deviceId) {
            $task = Task::where('status', 'sent')
                ->where('device_id', $deviceId)
                ->whereIn('task_type', $taskTypes)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($task) {
                return $task;
            }
        }

        // Fallback: Try to find device by IP address (for long-running operations
        // where session may have been overwritten by other concurrent devices)
        $clientIp = request()->ip();
        if ($clientIp) {
            // Look up device by last known IP
            $device = Device::where('ip_address', $clientIp)->first();

            if ($device) {
                $task = Task::where('status', 'sent')
                    ->where('device_id', $device->id)
                    ->whereIn('task_type', $taskTypes)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($task) {
                    Log::info('Found task via IP fallback', [
                        'device_id' => $device->id,
                        'task_id' => $task->id,
                        'ip' => $clientIp,
                    ]);
                    return $task;
                }
            }
        }

        // Last resort: Return most recent sent task of matching types
        // This is less safe but prevents stuck tasks
        return Task::where('status', 'sent')
            ->whereIn('task_type', $taskTypes)
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    /**
     * Check if this is a WiFi verification task
     */
    private function isWifiVerificationTask(Task $task): bool
    {
        if ($task->task_type !== 'get_params') {
            return false;
        }

        // Check if progress_info contains verification_for_task_id
        if (is_array($task->progress_info) && isset($task->progress_info['verification_for_task_id'])) {
            return true;
        }

        return false;
    }

    /**
     * Process WiFi verification results
     * Compare actual values with expected values and update original task status
     */
    private function processWifiVerification(Task $verificationTask, array $actualParams): void
    {
        $originalTaskId = $verificationTask->progress_info['verification_for_task_id'] ?? null;
        $expectedValues = $verificationTask->progress_info['expected_values'] ?? [];

        if (!$originalTaskId) {
            Log::warning('WiFi verification task missing original task ID', [
                'verification_task_id' => $verificationTask->id,
            ]);
            return;
        }

        $originalTask = Task::find($originalTaskId);
        if (!$originalTask) {
            Log::warning('WiFi verification: Original task not found', [
                'verification_task_id' => $verificationTask->id,
                'original_task_id' => $originalTaskId,
            ]);
            return;
        }

        // Compare expected vs actual values
        $matched = 0;
        $mismatched = 0;
        $missing = 0;
        $skipped = 0;
        $mismatches = [];

        // Write-only parameters that return empty/masked values - skip these in verification
        $writeOnlyPatterns = ['Passphrase', 'Password', 'PreSharedKey', 'Key.'];

        foreach ($expectedValues as $paramName => $expectedData) {
            $expectedValue = is_array($expectedData) ? ($expectedData['value'] ?? $expectedData) : $expectedData;

            // Check if this is a write-only parameter (passwords, etc.)
            $isWriteOnly = false;
            foreach ($writeOnlyPatterns as $pattern) {
                if (str_contains($paramName, $pattern)) {
                    $isWriteOnly = true;
                    break;
                }
            }

            if (isset($actualParams[$paramName])) {
                $actualValue = $actualParams[$paramName]['value'] ?? $actualParams[$paramName];

                // Skip write-only parameters (they return empty for security)
                if ($isWriteOnly && (empty($actualValue) || $actualValue === '')) {
                    $skipped++;
                    continue;
                }

                // Normalize for comparison (handle boolean strings, etc.)
                $normalizedExpected = $this->normalizeValueForComparison($expectedValue);
                $normalizedActual = $this->normalizeValueForComparison($actualValue);

                if ($normalizedExpected === $normalizedActual) {
                    $matched++;
                } else {
                    $mismatched++;
                    $mismatches[$paramName] = [
                        'expected' => $expectedValue,
                        'actual' => $actualValue,
                    ];
                }
            } else {
                $missing++;
            }
        }

        $total = $matched + $mismatched + $missing;
        $successRate = $total > 0 ? round(($matched / $total) * 100, 1) : 0;

        // Consider it verified if 80%+ of parameters match
        // (some params like passwords may not be readable)
        $verified = $successRate >= 80;

        if ($verified) {
            $skippedNote = $skipped > 0 ? ", {$skipped} write-only skipped" : '';
            $originalTask->update([
                'status' => 'completed',
                'result' => json_encode([
                    'verified' => true,
                    'message' => "WiFi settings verified successfully ({$successRate}% match{$skippedNote})",
                    'matched' => $matched,
                    'mismatched' => $mismatched,
                    'missing' => $missing,
                    'skipped' => $skipped,
                    'verification_task_id' => $verificationTask->id,
                ]),
            ]);

            Log::info('WiFi verification successful', [
                'original_task_id' => $originalTaskId,
                'verification_task_id' => $verificationTask->id,
                'success_rate' => $successRate,
                'matched' => $matched,
                'mismatched' => $mismatched,
                'missing' => $missing,
                'skipped' => $skipped,
            ]);

            // Notify workflow execution service of task completion
            $this->workflowExecutionService->onTaskCompleted($originalTask);
        } else {
            $skippedNote = $skipped > 0 ? ", {$skipped} write-only skipped" : '';
            $originalTask->update([
                'status' => 'failed',
                'result' => json_encode([
                    'verified' => false,
                    'message' => "WiFi settings verification failed ({$successRate}% match{$skippedNote})",
                    'matched' => $matched,
                    'mismatched' => $mismatched,
                    'missing' => $missing,
                    'skipped' => $skipped,
                    'mismatches' => $mismatches,
                    'verification_task_id' => $verificationTask->id,
                ]),
            ]);

            Log::warning('WiFi verification failed', [
                'original_task_id' => $originalTaskId,
                'verification_task_id' => $verificationTask->id,
                'success_rate' => $successRate,
                'matched' => $matched,
                'mismatched' => $mismatched,
                'missing' => $missing,
                'skipped' => $skipped,
                'mismatches' => $mismatches,
            ]);
        }
    }

    /**
     * Normalize values for comparison (handle boolean strings, etc.)
     */
    private function normalizeValueForComparison($value): string
    {
        $strValue = (string) $value;

        // Normalize boolean strings
        if (in_array(strtolower($strValue), ['true', '1', 'yes', 'on'])) {
            return 'true';
        }
        if (in_array(strtolower($strValue), ['false', '0', 'no', 'off'])) {
            return 'false';
        }

        return $strValue;
    }

    /**
     * Complete a task and notify workflow execution service
     * Also queues a device refresh for workflow tasks
     */
    private function completeTaskWithWorkflowNotification(Task $task, ?array $result = null): void
    {
        $task->markAsCompleted($result);

        // Notify workflow execution service
        $this->workflowExecutionService->onTaskCompleted($task);

        // If this task was part of a workflow, queue a device refresh
        $execution = \App\Models\WorkflowExecution::where('task_id', $task->id)->first();
        if ($execution) {
            $device = $task->device;
            if ($device) {
                // Queue a refresh to get updated device info
                Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'get_parameter_values',
                    'description' => 'Refresh device after workflow task',
                    'status' => 'pending',
                    'parameters' => $this->getDeviceRefreshParameters($device),
                ]);

                Log::info('Queued device refresh after workflow task completion', [
                    'device_id' => $device->id,
                    'task_id' => $task->id,
                    'workflow_id' => $execution->group_workflow_id,
                ]);
            }
        }
    }

    /**
     * Get parameters for a device refresh based on data model
     */
    private function getDeviceRefreshParameters(Device $device): array
    {
        $dataModel = $device->getDataModel();

        if ($dataModel === 'TR-181') {
            return [
                'Device.DeviceInfo.SoftwareVersion',
                'Device.DeviceInfo.HardwareVersion',
                'Device.DeviceInfo.UpTime',
                'Device.ManagementServer.PeriodicInformInterval',
            ];
        }

        return [
            'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
            'InternetGatewayDevice.DeviceInfo.HardwareVersion',
            'InternetGatewayDevice.DeviceInfo.UpTime',
            'InternetGatewayDevice.ManagementServer.PeriodicInformInterval',
        ];
    }

    /**
     * Trigger on_connect workflows for a device
     * Creates tasks for any active workflows with schedule_type='on_connect' that match this device
     */
    private function triggerOnConnectWorkflows(Device $device): void
    {
        // Find active on_connect workflows
        $workflows = \App\Models\GroupWorkflow::where('schedule_type', 'on_connect')
            ->where('is_active', true)
            ->where('status', 'active')
            ->get();

        if ($workflows->isEmpty()) {
            return;
        }

        foreach ($workflows as $workflow) {
            // Check if device matches this workflow's group
            if (!$workflow->deviceGroup->matchesDevice($device)) {
                continue;
            }

            // Check if workflow can run for this device (handles dependencies and run_once_per_device)
            if (!$workflow->canRunForDevice($device)) {
                continue;
            }

            // Execute the workflow for this device
            $task = $this->workflowExecutionService->executeForDevice($workflow, $device);

            if ($task) {
                Log::info('On-connect workflow triggered', [
                    'device_id' => $device->id,
                    'workflow_id' => $workflow->id,
                    'workflow_name' => $workflow->name,
                    'task_id' => $task->id,
                ]);
            }
        }
    }
}

