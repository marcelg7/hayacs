<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectionRequestService
{
    protected ?XmppService $xmppService = null;

    /**
     * Get XMPP service instance (lazy loaded)
     */
    protected function getXmppService(): XmppService
    {
        if ($this->xmppService === null) {
            $this->xmppService = app(XmppService::class);
        }
        return $this->xmppService;
    }

    /**
     * Send a connection request to a device
     *
     * @param Device $device
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendConnectionRequest(Device $device): array
    {
        // For mesh APs with port forwarding configured, use the forwarded URL
        $url = $device->getEffectiveConnectionRequestUrl();

        // If getEffectiveConnectionRequestUrl returns null, check for UDP/STUN
        if ($url === null) {
            if ($device->udp_connection_request_address) {
                return $this->sendUdpConnectionRequest($device);
            }

            // Fall back to direct URL
            $url = $device->connection_request_url;
        }

        if (!$url) {
            Log::warning('Cannot send connection request - no URL configured', [
                'device_id' => $device->id,
                'is_mesh' => $device->isMeshDevice(),
            ]);

            return [
                'success' => false,
                'message' => $device->isMeshDevice()
                    ? 'Mesh AP does not have a port forward configured. Run: php artisan mesh:setup-port-forwards --scan'
                    : 'Device does not have a connection request URL configured',
            ];
        }

        $username = $device->connection_request_username ?? '';
        $password = $device->connection_request_password ?? '';

        // Check if URL is a private IP and we're not using a forwarded address
        if ($this->isPrivateIpUrl($url) && empty($device->mesh_forwarded_url)) {
            Log::warning('Connection request URL is a private IP - device may be unreachable', [
                'device_id' => $device->id,
                'url' => $url,
                'is_mesh' => $device->isMeshDevice(),
            ]);

            // For mesh devices, suggest setting up port forward
            if ($device->isMeshDevice()) {
                return [
                    'success' => false,
                    'message' => 'Mesh AP is behind NAT. Setup port forwarding with: php artisan mesh:setup-port-forwards --device=' . $device->id,
                ];
            }
        }

        Log::info('Sending connection request to device', [
            'device_id' => $device->id,
            'url' => $url,
            'is_forwarded' => !empty($device->mesh_forwarded_url),
            'has_username' => !empty($username),
        ]);

        try {
            // TR-069 Connection Request is a simple HTTP GET/POST with digest auth
            // Most devices expect an empty GET request
            // Short timeout (3s) since we don't want to block API responses
            // Device will pick up task on next periodic inform if this fails
            $response = Http::timeout(3)
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
     * Send UDP Connection Request (STUN-enabled devices)
     *
     * @param Device $device
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendUdpConnectionRequest(Device $device): array
    {
        if (!$device->udp_connection_request_address) {
            return [
                'success' => false,
                'message' => 'Device does not have UDP connection request address',
            ];
        }

        // Parse IP:port from UDP address
        $parts = explode(':', $device->udp_connection_request_address);
        if (count($parts) !== 2) {
            Log::warning('Invalid UDP connection request address format', [
                'device_id' => $device->id,
                'address' => $device->udp_connection_request_address,
            ]);

            return [
                'success' => false,
                'message' => 'Invalid UDP address format',
            ];
        }

        $ip = $parts[0];
        $port = (int) $parts[1];

        Log::info('Sending UDP connection request', [
            'device_id' => $device->id,
            'ip' => $ip,
            'port' => $port,
        ]);

        try {
            // Create UDP socket
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket === false) {
                throw new \Exception('Failed to create UDP socket: ' . socket_strerror(socket_last_error()));
            }

            // Set socket timeout (1 second)
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);

            // TR-069 Amendment 3: UDP Connection Request Packet
            // Simple format: Just send any data - device will connect to ACS upon receiving ANY UDP packet
            // Spec requires specific format, but many devices just need to receive SOMETHING
            $username = $device->connection_request_username ?? '';
            $timestamp = time();
            $id = random_int(1, 65535);

            // Build simple packet (some devices work with just this)
            $packet = pack('C*', 0x01, 0x00); // Version 1, Type 0 (Connection Request)

            // Send the packet
            $sent = socket_sendto($socket, $packet, strlen($packet), 0, $ip, $port);

            socket_close($socket);

            if ($sent === false) {
                throw new \Exception('Failed to send UDP packet: ' . socket_strerror(socket_last_error()));
            }

            Log::info('UDP connection request sent successfully', [
                'device_id' => $device->id,
                'bytes_sent' => $sent,
            ]);

            return [
                'success' => true,
                'message' => 'UDP connection request sent successfully',
            ];
        } catch (\Exception $e) {
            Log::error('UDP connection request failed', [
                'device_id' => $device->id,
                'ip' => $ip,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'UDP connection request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send connection request (tries XMPP first, then UDP, then HTTP)
     *
     * @param Device $device
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendConnectionRequestWithFallback(Device $device): array
    {
        // Try XMPP first if device supports it and our server is configured
        if ($device->isXmppEnabled() && $this->getXmppService()->isEnabled()) {
            $result = $this->sendXmppConnectionRequest($device);
            if ($result['success']) {
                return $result;
            }

            Log::info('XMPP connection request failed, trying other methods', [
                'device_id' => $device->id,
                'error' => $result['message'],
            ]);
        }

        // Try UDP if available
        if ($device->udp_connection_request_address && $device->stun_enabled) {
            $result = $this->sendUdpConnectionRequest($device);
            if ($result['success']) {
                return $result;
            }

            Log::info('UDP connection request failed, falling back to HTTP', [
                'device_id' => $device->id,
            ]);
        }

        // Fallback to HTTP
        return $this->sendConnectionRequest($device);
    }

    /**
     * Send XMPP connection request
     *
     * @param Device $device
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendXmppConnectionRequest(Device $device): array
    {
        if (!$device->isXmppEnabled()) {
            return [
                'success' => false,
                'message' => 'Device does not have XMPP enabled',
            ];
        }

        $xmppService = $this->getXmppService();

        if (!$xmppService->isEnabled()) {
            return [
                'success' => false,
                'message' => 'XMPP server is not configured',
            ];
        }

        Log::info('Sending XMPP connection request', [
            'device_id' => $device->id,
            'jid' => $device->xmpp_jid,
        ]);

        return $xmppService->sendConnectionRequest($device);
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

    /**
     * Check if URL contains a private IP address
     */
    private function isPrivateIpUrl(string $url): bool
    {
        if (preg_match('/https?:\/\/([0-9.]+)/', $url, $matches)) {
            $ip = $matches[1];
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }

            // 10.0.0.0/8
            if (($long & 0xFF000000) === 0x0A000000) return true;
            // 172.16.0.0/12
            if (($long & 0xFFF00000) === 0xAC100000) return true;
            // 192.168.0.0/16
            if (($long & 0xFFFF0000) === 0xC0A80000) return true;
        }

        return false;
    }
}
