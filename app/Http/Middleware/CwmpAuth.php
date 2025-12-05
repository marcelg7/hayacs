<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * CWMP Authentication Middleware
 *
 * Supports both Digest and Basic authentication for TR-069 CWMP sessions.
 * USS uses Digest auth challenge first, which is required for GigaSpire devices.
 *
 * Flow:
 * 1. Device sends request (no auth or with auth)
 * 2. If no auth header: Challenge with 401 + Digest (and Basic fallback)
 * 3. If Digest auth: Validate response
 * 4. If Basic auth: Validate credentials (fallback for other devices)
 */
class CwmpAuth
{
    /**
     * Nonce validity period in seconds
     */
    private const NONCE_VALIDITY = 300; // 5 minutes

    /**
     * Realm for authentication
     */
    private const REALM = 'TR-069 ACS';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        // No auth header - send challenge
        if (!$authHeader) {
            return $this->sendAuthChallenge($request);
        }

        // Digest authentication
        if (str_starts_with($authHeader, 'Digest ')) {
            return $this->handleDigestAuth($request, $authHeader, $next);
        }

        // Basic authentication (fallback)
        if (str_starts_with($authHeader, 'Basic ')) {
            return $this->handleBasicAuth($request, $authHeader, $next);
        }

        // Unknown auth type
        Log::warning('CWMP: Unknown authentication type', [
            'auth_header' => substr($authHeader, 0, 50),
        ]);
        return $this->sendAuthChallenge($request);
    }

    /**
     * Send 401 Unauthorized with Digest and Basic challenges
     */
    private function sendAuthChallenge(Request $request): Response
    {
        $nonce = $this->generateNonce();
        $opaque = $this->generateOpaque();

        // Store nonce for validation (keyed by client IP to allow concurrent sessions)
        $clientIp = $request->ip();
        Cache::put("cwmp_nonce_{$clientIp}_{$nonce}", [
            'created_at' => time(),
            'opaque' => $opaque,
        ], self::NONCE_VALIDITY);

        Log::debug('CWMP: Sending auth challenge', [
            'client_ip' => $clientIp,
            'nonce' => $nonce,
        ]);

        // Match USS format: Digest first, then Basic
        $digestChallenge = sprintf(
            'Digest realm="%s", qop="auth", nonce="%s", opaque="%s"',
            self::REALM,
            $nonce,
            $opaque
        );
        $basicChallenge = sprintf('Basic realm="%s"', self::REALM);

        return response('', 401)
            ->header('WWW-Authenticate', $digestChallenge)
            ->header('WWW-Authenticate', $basicChallenge, false) // false = append, don't replace
            ->header('Content-Type', 'text/plain')
            ->header('Connection', 'close')
            ->header('Content-Length', '0');
    }

    /**
     * Handle Digest authentication
     */
    private function handleDigestAuth(Request $request, string $authHeader, Closure $next): Response
    {
        $digestParams = $this->parseDigestAuth($authHeader);

        if (!$digestParams) {
            Log::warning('CWMP: Failed to parse Digest auth header');
            return $this->sendAuthChallenge($request);
        }

        // Validate required parameters
        $required = ['username', 'realm', 'nonce', 'uri', 'response'];
        foreach ($required as $param) {
            if (empty($digestParams[$param])) {
                Log::warning('CWMP: Missing Digest parameter', ['missing' => $param]);
                return $this->sendAuthChallenge($request);
            }
        }

        // Validate nonce
        $clientIp = $request->ip();
        $nonceData = Cache::get("cwmp_nonce_{$clientIp}_{$digestParams['nonce']}");

        if (!$nonceData) {
            Log::warning('CWMP: Invalid or expired nonce', [
                'nonce' => $digestParams['nonce'],
                'client_ip' => $clientIp,
            ]);
            return $this->sendAuthChallenge($request);
        }

        // Validate opaque if provided
        if (!empty($digestParams['opaque']) && $digestParams['opaque'] !== $nonceData['opaque']) {
            Log::warning('CWMP: Opaque mismatch');
            return $this->sendAuthChallenge($request);
        }

        // Find matching credentials
        $credentials = $this->findCredentials($digestParams['username']);
        if (!$credentials) {
            Log::warning('CWMP: Unknown username', ['username' => $digestParams['username']]);
            return $this->sendAuthChallenge($request);
        }

        // Calculate expected response
        $expectedResponse = $this->calculateDigestResponse(
            $digestParams,
            $credentials['password'],
            $request->method()
        );

        if ($expectedResponse !== $digestParams['response']) {
            Log::warning('CWMP: Digest response mismatch', [
                'username' => $digestParams['username'],
                'expected' => $expectedResponse,
                'received' => $digestParams['response'],
            ]);
            return $this->sendAuthChallenge($request);
        }

        Log::debug('CWMP: Digest auth successful', [
            'username' => $digestParams['username'],
            'client_ip' => $clientIp,
        ]);

        return $next($request);
    }

    /**
     * Handle Basic authentication (fallback for devices that don't support Digest)
     */
    private function handleBasicAuth(Request $request, string $authHeader, Closure $next): Response
    {
        $credentials = base64_decode(substr($authHeader, 6));
        if (!$credentials || !str_contains($credentials, ':')) {
            Log::warning('CWMP: Invalid Basic auth format');
            return $this->sendAuthChallenge($request);
        }

        [$username, $password] = explode(':', $credentials, 2);

        if (!$this->validateCredentials($username, $password)) {
            Log::warning('CWMP: Basic auth failed', ['username' => $username]);
            return $this->sendAuthChallenge($request);
        }

        Log::debug('CWMP: Basic auth successful', [
            'username' => $username,
            'client_ip' => $request->ip(),
        ]);

        return $next($request);
    }

    /**
     * Parse Digest authentication header
     */
    private function parseDigestAuth(string $header): ?array
    {
        // Remove "Digest " prefix
        $header = substr($header, 7);

        // Parse key="value" pairs
        $params = [];
        $pattern = '/(\w+)=(?:"([^"]+)"|([^\s,]+))/';

        if (preg_match_all($pattern, $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2] !== '' ? $match[2] : $match[3];
                $params[$key] = $value;
            }
        }

        return !empty($params) ? $params : null;
    }

    /**
     * Calculate expected Digest response
     */
    private function calculateDigestResponse(array $params, string $password, string $method): string
    {
        $username = $params['username'];
        $realm = $params['realm'];
        $nonce = $params['nonce'];
        $uri = $params['uri'];
        $nc = $params['nc'] ?? '';
        $cnonce = $params['cnonce'] ?? '';
        $qop = $params['qop'] ?? '';

        // HA1 = MD5(username:realm:password)
        $ha1 = md5("{$username}:{$realm}:{$password}");

        // HA2 = MD5(method:uri)
        $ha2 = md5("{$method}:{$uri}");

        // Response calculation depends on qop
        if ($qop === 'auth' || $qop === 'auth-int') {
            // response = MD5(HA1:nonce:nc:cnonce:qop:HA2)
            return md5("{$ha1}:{$nonce}:{$nc}:{$cnonce}:{$qop}:{$ha2}");
        } else {
            // response = MD5(HA1:nonce:HA2) - RFC 2069 compatibility
            return md5("{$ha1}:{$nonce}:{$ha2}");
        }
    }

    /**
     * Find credentials by username
     */
    private function findCredentials(string $username): ?array
    {
        $validPairs = config('cwmp.credentials', [
            [
                'username' => 'acs-user',
                'password' => 'acs-password',
            ],
        ]);

        foreach ($validPairs as $pair) {
            if (!empty($pair['username']) && $pair['username'] === $username) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * Validate credentials (for Basic auth)
     */
    private function validateCredentials(string $username, string $password): bool
    {
        $credentials = $this->findCredentials($username);
        return $credentials && $credentials['password'] === $password;
    }

    /**
     * Generate a unique nonce
     */
    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate an opaque value
     */
    private function generateOpaque(): string
    {
        return bin2hex(random_bytes(8));
    }
}
