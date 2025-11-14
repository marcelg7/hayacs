<?php

namespace App\Http\Controllers;

use App\Models\Device;
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

            // Handle empty POST (device signaling end of session)
            if (empty($xmlContent) || strlen($xmlContent) < 10) {
                Log::info('Empty POST received - ending CWMP session');

                // Return 204 No Content to end the session
                return response('', 204)
                    ->header('Content-Type', 'text/xml; charset=utf-8');
            }

            // Parse incoming message
            $parsed = $this->cwmpService->parseInform($xmlContent);

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
                'GetParameterValuesResponse' => $this->handleGetParameterValuesResponse($parsed),
                'SetParameterValuesResponse' => $this->handleSetParameterValuesResponse($parsed),
                'RebootResponse' => $this->handleRebootResponse($parsed),
                'FactoryResetResponse' => $this->handleFactoryResetResponse($parsed),
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

        $device->save();

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

        // Auto-provision device based on events and rules
        $this->provisioningService->autoProvision($device, $parsed['events']);

        // Check for pending tasks
        $pendingTask = $device->tasks()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->first();

        if ($pendingTask) {
            // Mark task as sent
            $pendingTask->markAsSent();

            // Generate RPC based on task type
            return $this->generateRpcForTask($pendingTask);
        }

        // No tasks - send InformResponse
        return $this->cwmpService->createInformResponse($parsed['max_envelopes']);
    }

    /**
     * Handle GetParameterValuesResponse
     */
    private function handleGetParameterValuesResponse(array $parsed): string
    {
        // Find the task that was sent
        $task = Task::where('status', 'sent')
            ->where('task_type', 'get_params')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            // Store parameters
            $device = $task->device;
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
        // Find the task that was sent
        $task = Task::where('status', 'sent')
            ->where('task_type', 'set_params')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($task) {
            if ($parsed['status'] === 0) {
                $task->markAsCompleted(['status' => $parsed['status']]);
                Log::info('SetParameterValues completed', ['device_id' => $task->device_id]);
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
     * Generate RPC message for a task
     */
    private function generateRpcForTask(Task $task): string
    {
        return match ($task->task_type) {
            'get_params' => $this->cwmpService->createGetParameterValues(
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
            default => $this->cwmpService->createEmptyResponse(),
        };
    }
}

