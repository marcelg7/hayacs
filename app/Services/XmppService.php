<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class XmppService
{
    protected $socket = null;
    protected bool $connected = false;
    protected bool $authenticated = false;
    protected ?string $lastError = null;
    protected string $streamId = '';

    /**
     * Check if XMPP is enabled in configuration
     */
    public function isEnabled(): bool
    {
        return config('xmpp.enabled', false) && !empty(config('xmpp.server'));
    }

    /**
     * Connect to the XMPP server
     */
    public function connect(): bool
    {
        if ($this->connected && $this->socket) {
            return true;
        }

        if (!$this->isEnabled()) {
            $this->lastError = 'XMPP is not enabled in configuration';
            return false;
        }

        try {
            $server = config('xmpp.server');
            $port = config('xmpp.port', 5222);
            $username = config('xmpp.username');
            $password = config('xmpp.password');
            $domain = config('xmpp.domain');

            if (empty($password)) {
                $this->lastError = 'XMPP password not configured';
                return false;
            }

            Log::info('Connecting to XMPP server', [
                'server' => $server,
                'port' => $port,
                'username' => $username,
                'domain' => $domain,
            ]);

            // Create TCP socket
            $timeout = config('xmpp.connect_timeout', 10);
            $this->socket = @stream_socket_client(
                "tcp://{$server}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT
            );

            if (!$this->socket) {
                $this->lastError = "Failed to connect: {$errstr} ({$errno})";
                return false;
            }

            stream_set_timeout($this->socket, $timeout);
            $this->connected = true;

            // Initialize XMPP stream
            if (!$this->initStream($domain)) {
                return false;
            }

            // Authenticate
            if (!$this->authenticate($username, $password, $domain)) {
                return false;
            }

            Log::info('Connected to XMPP server successfully');
            return true;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();

            Log::error('Failed to connect to XMPP server', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Initialize XMPP stream
     */
    protected function initStream(string $domain): bool
    {
        $xml = "<?xml version='1.0'?>" .
            "<stream:stream to='{$domain}' " .
            "xmlns='jabber:client' " .
            "xmlns:stream='http://etherx.jabber.org/streams' " .
            "version='1.0'>";

        $this->write($xml);
        $response = $this->read();

        if (strpos($response, 'stream:stream') === false) {
            $this->lastError = 'Failed to initialize XMPP stream';
            return false;
        }

        // Extract stream ID
        if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $response, $matches)) {
            $this->streamId = $matches[1];
        }

        // Check for STARTTLS requirement
        if (strpos($response, 'starttls') !== false) {
            if (!$this->startTls($domain)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Upgrade to TLS
     */
    protected function startTls(string $domain): bool
    {
        $this->write("<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'/>");
        $response = $this->read();

        if (strpos($response, 'proceed') === false) {
            $this->lastError = 'STARTTLS failed';
            return false;
        }

        // Upgrade socket to TLS
        $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            $this->lastError = 'TLS handshake failed';
            return false;
        }

        // Re-initialize stream after TLS
        return $this->initStream($domain);
    }

    /**
     * Authenticate with SASL PLAIN
     */
    protected function authenticate(string $username, string $password, string $domain): bool
    {
        // Wait for features if not already received
        $response = $this->read(1);

        // SASL PLAIN: base64(authzid \0 authcid \0 password)
        $authString = base64_encode("\0{$username}\0{$password}");

        $this->write("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='PLAIN'>{$authString}</auth>");
        $response = $this->read();

        if (strpos($response, 'success') === false) {
            if (strpos($response, 'not-authorized') !== false) {
                $this->lastError = 'Authentication failed: Invalid username or password';
            } else {
                $this->lastError = 'Authentication failed: ' . strip_tags($response);
            }
            return false;
        }

        $this->authenticated = true;

        // Re-initialize stream after auth
        $xml = "<?xml version='1.0'?>" .
            "<stream:stream to='{$domain}' " .
            "xmlns='jabber:client' " .
            "xmlns:stream='http://etherx.jabber.org/streams' " .
            "version='1.0'>";

        $this->write($xml);
        $this->read(); // stream response

        // Bind resource
        $this->write("<iq type='set' id='bind1'><bind xmlns='urn:ietf:params:xml:ns:xmpp-bind'><resource>acs</resource></bind></iq>");
        $this->read();

        // Start session
        $this->write("<iq type='set' id='sess1'><session xmlns='urn:ietf:params:xml:ns:xmpp-session'/></iq>");
        $this->read();

        return true;
    }

    /**
     * Write to socket
     */
    protected function write(string $data): void
    {
        if (!$this->socket) {
            throw new \Exception('Not connected');
        }
        fwrite($this->socket, $data);
        Log::debug('XMPP TX', ['data' => $data]);
    }

    /**
     * Read from socket
     */
    protected function read(int $timeout = 5): string
    {
        if (!$this->socket) {
            throw new \Exception('Not connected');
        }

        $response = '';
        $startTime = time();

        stream_set_blocking($this->socket, false);

        while (time() - $startTime < $timeout) {
            $chunk = fread($this->socket, 4096);
            if ($chunk) {
                $response .= $chunk;
                // Check if we have a complete response
                if (strpos($response, '>') !== false) {
                    break;
                }
            }
            usleep(10000); // 10ms
        }

        stream_set_blocking($this->socket, true);
        Log::debug('XMPP RX', ['data' => $response]);
        return $response;
    }

    /**
     * Disconnect from the XMPP server
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            try {
                if ($this->connected) {
                    @fwrite($this->socket, '</stream:stream>');
                }
                @fclose($this->socket);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $this->socket = null;
        $this->connected = false;
        $this->authenticated = false;
    }

    /**
     * Check if connected to XMPP server
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->authenticated && $this->socket !== null;
    }

    /**
     * Get last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Send a connection request to a device via XMPP
     */
    public function sendConnectionRequest(Device $device): array
    {
        if (!$device->xmpp_enabled || !$device->xmpp_jid) {
            return [
                'success' => false,
                'message' => 'Device does not have XMPP enabled or JID configured',
            ];
        }

        // Connect if not already connected
        if (!$this->isConnected()) {
            if (!$this->connect()) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to XMPP server: ' . ($this->lastError ?? 'Unknown error'),
                ];
            }
        }

        try {
            $jid = $device->xmpp_jid;
            $username = config('xmpp.connection_request.username', 'admin');
            $password = config('xmpp.connection_request.password', 'admin');

            // Build TR-069 XMPP connection request XML (Annex K format)
            $connectionRequestXml = $this->buildConnectionRequestXml($username, $password);

            Log::info('Sending XMPP connection request', [
                'device_id' => $device->id,
                'jid' => $jid,
            ]);

            // Send message
            $messageId = 'msg' . time();
            $message = "<message type='normal' id='{$messageId}' to='" . htmlspecialchars($jid) . "'>" .
                "<body>{$connectionRequestXml}</body>" .
                "</message>";

            $this->write($message);

            Log::info('XMPP connection request sent successfully', [
                'device_id' => $device->id,
                'jid' => $jid,
            ]);

            return [
                'success' => true,
                'message' => 'XMPP connection request sent successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send XMPP connection request', [
                'device_id' => $device->id,
                'jid' => $device->xmpp_jid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send XMPP connection request: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build TR-069 Annex K connection request XML
     */
    protected function buildConnectionRequestXml(string $username, string $password): string
    {
        return '<connectionRequest xmlns="urn:broadband-forum-org:cwmp:xmppConnReq-1-0">'
            . '<username>' . htmlspecialchars($username, ENT_XML1, 'UTF-8') . '</username>'
            . '<password>' . htmlspecialchars($password, ENT_XML1, 'UTF-8') . '</password>'
            . '</connectionRequest>';
    }

    /**
     * Generate JabberID for a device based on configured format
     */
    public function generateJid(Device $device): string
    {
        $format = config('xmpp.jid_format', '{serial_number}@{domain}/cwmp');
        $domain = config('xmpp.domain', 'hayacs.hay.net');

        return str_replace(
            ['{serial_number}', '{oui}', '{product_class}', '{domain}'],
            [
                $device->serial_number,
                $device->oui,
                str_replace(' ', '-', $device->product_class ?? ''),
                $domain,
            ],
            $format
        );
    }

    /**
     * Check if a device supports XMPP
     */
    public function deviceSupportsXmpp(Device $device): bool
    {
        $supported = $device->parameters()
            ->where('name', 'LIKE', '%ManagementServer.SupportedConnReqMethods%')
            ->first();

        if ($supported && stripos($supported->value, 'XMPP') !== false) {
            return true;
        }

        return $device->parameters()
            ->where('name', 'LIKE', '%XMPP.Connection.%')
            ->exists();
    }

    /**
     * Get XMPP connection info from device parameters
     */
    public function getDeviceXmppInfo(Device $device): array
    {
        $info = [
            'supported' => false,
            'enabled' => false,
            'jid' => null,
            'status' => null,
            'domain' => null,
            'port' => null,
            'use_tls' => null,
        ];

        $supported = $device->parameters()
            ->where('name', 'LIKE', '%ManagementServer.SupportedConnReqMethods%')
            ->first();

        if ($supported && stripos($supported->value, 'XMPP') !== false) {
            $info['supported'] = true;
        }

        $xmppParams = $device->parameters()
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%XMPP.Connection.%')
                    ->orWhere('name', 'LIKE', '%ManagementServer.X_ALU%XMPP%');
            })
            ->get();

        foreach ($xmppParams as $param) {
            $name = strtolower($param->name);

            if (str_contains($name, 'enable')) {
                $info['enabled'] = in_array(strtolower($param->value), ['true', '1']);
            }
            if (str_contains($name, 'jabberid') || str_contains($name, 'jid')) {
                $info['jid'] = $param->value;
            }
            if (str_contains($name, 'status')) {
                $info['status'] = $param->value;
            }
            if (str_contains($name, 'domain')) {
                $info['domain'] = $param->value;
            }
            if (str_contains($name, 'port')) {
                $info['port'] = $param->value;
            }
            if (str_contains($name, 'usetls')) {
                $info['use_tls'] = in_array(strtolower($param->value), ['true', '1']);
            }
        }

        return $info;
    }

    /**
     * Destructor - ensure we disconnect
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
