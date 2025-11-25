<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CwmpBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get Authorization header
        $authHeader = $request->header('Authorization');

        // Check if Authorization header is present
        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return $this->unauthorizedResponse();
        }

        // Decode credentials
        $credentials = base64_decode(substr($authHeader, 6));
        [$username, $password] = explode(':', $credentials, 2);

        // Validate against allowed credential pairs
        if (!$this->validateCredentials($username, $password)) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    /**
     * Validate credentials against configured pairs
     */
    private function validateCredentials(string $username, string $password): bool
    {
        $validPairs = config('cwmp.credentials', [
            [
                'username' => 'acs-user',
                'password' => 'acs-password',
            ],
        ]);

        foreach ($validPairs as $pair) {
            // Skip empty credential pairs
            if (empty($pair['username']) || empty($pair['password'])) {
                continue;
            }

            if ($username === $pair['username'] && $password === $pair['password']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return 401 Unauthorized response
     */
    private function unauthorizedResponse(): Response
    {
        return response('Unauthorized', 401)
            ->header('WWW-Authenticate', 'Basic realm="TR-069 ACS"');
    }
}
