<?php

namespace App\Http\Middleware;

use App\Services\TrustedDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAllowedAccess
{
    protected TrustedDeviceService $trustedDeviceService;

    public function __construct(TrustedDeviceService $trustedDeviceService)
    {
        $this->trustedDeviceService = $trustedDeviceService;
    }

    /**
     * Handle an incoming request.
     *
     * Validates that the user is either:
     * 1. From an allowed IP range (VPN/office)
     * 2. Has a valid trusted device cookie
     *
     * This middleware runs AFTER authentication, so we have access to the user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not authenticated, let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Check if IP is in allowed range
        if ($this->trustedDeviceService->isAllowedIp($request->ip())) {
            return $next($request);
        }

        // Check for trusted device cookie
        $trustedDevice = $this->trustedDeviceService->getValidTrustedDevice($request);

        if ($trustedDevice && $trustedDevice->user_id === $user->id) {
            // Valid trusted device - record the access and allow
            $fingerprintMatched = $this->trustedDeviceService->verifyFingerprint($request, $trustedDevice);
            $trustedDevice->recordUse('login_bypass', $fingerprintMatched);

            return $next($request);
        }

        // Not allowed - return 403
        return response()->view('errors.untrusted-access', [
            'message' => 'Access denied. You must either connect via VPN or use a trusted device.',
        ], 403);
    }
}
