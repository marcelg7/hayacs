<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectionRequestService
{
    /**
     * Send a connection request to a device
     *
     * @param Device $device
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendConnectionRequest(Device $device): array
    {
        if (!$device->connection_request_url) {
            Log::warning('Cannot send connection request - no URL configured', [
                'device_id' => $device->id,
            ]);

            return [
                'success' => false,
                'message' => 'Device does not have a connection request URL configured',
            ];
        }

        $url = $device->connection_request_url;
        $username = $device->connection_request_username ?? '';
        $password = $device->connection_request_password ?? '';

        Log::info('Sending connection request to device', [
            'device_id' => $device->id,
            'url' => $url,
            'has_username' => !empty($username),
        ]);

        try {
            // TR-069 Connection Request is a simple HTTP GET/POST with digest auth
            // Most devices expect an empty GET request
            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => false, // Many devices use self-signed certs
                ])
                ->withDigestAuth($username, $password)
                ->get($url);

            if ($response->successful() || $response->status() === 401) {
                // 200 OK = success
                // 401 = Device may respond with 401 initially for digest auth challenge, which is normal
                // The client should auto-handle the challenge and retry

                if ($response->successful()) {
                    Log::info('Connection request sent successfully', [
                        'device_id' => $device->id,
                        'status_code' => $response->status(),
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Connection request sent successfully',
                    ];
                }
            }

            // If we get here, it failed
            Log::warning('Connection request failed', [
                'device_id' => $device->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => "Connection request failed with status {$response->status()}",
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection request - network error', [
                'device_id' => $device->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Could not connect to device: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Connection request failed with exception', [
                'device_id' => $device->id,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send connection request and wait for device to connect
     * Returns true if device connected within timeout
     *
     * @param Device $device
     * @param int $timeoutSeconds How long to wait for device connection
     * @return bool
     */
    public function sendAndWaitForConnection(Device $device, int $timeoutSeconds = 30): bool
    {
        $lastInform = $device->last_inform;

        $result = $this->sendConnectionRequest($device);

        if (!$result['success']) {
            return false;
        }

        // Poll for new Inform
        $startTime = time();
        while (time() - $startTime < $timeoutSeconds) {
            sleep(1);

            $device->refresh();

            // Check if device sent a new Inform
            if ($device->last_inform && $device->last_inform->gt($lastInform)) {
                Log::info('Device connected after connection request', [
                    'device_id' => $device->id,
                    'wait_time' => time() - $startTime,
                ]);

                return true;
            }
        }

        Log::warning('Device did not connect after connection request', [
            'device_id' => $device->id,
            'timeout' => $timeoutSeconds,
        ]);

        return false;
    }
}
