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

        $device->save();

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
        // Find the task that was sent (either get_params or get_diagnostic_results)
        $task = Task::where('status', 'sent')
            ->whereIn('task_type', ['get_params', 'get_diagnostic_results'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            $device = $task->device;

            // Check if this is a diagnostic result retrieval
            if ($task->task_type === 'get_diagnostic_results') {
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
                            'results' => $parsed['parameters'],
                        ]);
                    }
                }
                // Mark the retrieval task as completed
                $task->markAsCompleted($parsed['parameters']);
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
     * Handle SetParameterValuesResponse
     */
    private function handleSetParameterValuesResponse(array $parsed): string
    {
        // Find the task that was sent (set_params, ping_diagnostics, or traceroute_diagnostics)
        $task = Task::where('status', 'sent')
            ->whereIn('task_type', ['set_params', 'ping_diagnostics', 'traceroute_diagnostics'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            if ($parsed['status'] === 0) {
                // For diagnostic tasks, keep them as 'sent' until we get the "8 DIAGNOSTICS COMPLETE" event
                // and retrieve the actual results
                if ($task->task_type === 'ping_diagnostics' || $task->task_type === 'traceroute_diagnostics') {
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
            'get_params', 'get_diagnostic_results' => $this->cwmpService->createGetParameterValues(
                $task->parameters['names'] ?? []
            ),
            'set_params' => $this->cwmpService->createSetParameterValues(
                $task->parameters['values'] ?? []
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
            ->whereIn('task_type', ['ping_diagnostics', 'traceroute_diagnostics'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$diagnosticTask) {
            Log::warning('DIAGNOSTICS COMPLETE event but no diagnostic task found', [
                'device_id' => $device->id,
            ]);
            return;
        }

        $dataModel = $device->getDataModel();
        $isPing = $diagnosticTask->task_type === 'ping_diagnostics';

        if ($isPing) {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.IPPingDiagnostics' : 'InternetGatewayDevice.IPPingDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.SuccessCount",
                "{$prefix}.FailureCount",
                "{$prefix}.AverageResponseTime",
                "{$prefix}.MinimumResponseTime",
                "{$prefix}.MaximumResponseTime",
            ];
        } else {
            $prefix = $dataModel === 'Device:2' ? 'Device.IP.Diagnostics.TraceRouteDiagnostics' : 'InternetGatewayDevice.TraceRouteDiagnostics';
            $parameters = [
                "{$prefix}.DiagnosticsState",
                "{$prefix}.ResponseTime",
                "{$prefix}.RouteHopsNumberOfEntries",
                "{$prefix}.RouteHops.",  // Partial path query to get all hop entries
            ];
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
}

