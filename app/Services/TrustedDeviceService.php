<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    const COOKIE_NAME = 'trusted_device_token';
    const TRUST_DURATION_DAYS = 90;

    /**
     * Generate a device fingerprint hash from request headers.
     * Uses stable browser characteristics that persist across sessions.
     */
    public function generateFingerprint(Request $request): string
    {
        $components = [
            $request->userAgent() ?? '',
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
            // Screen resolution comes from JavaScript via hidden form field
            $request->input('screen_resolution', ''),
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Check if the current request has a valid trusted device token.
     * Returns the TrustedDevice model if valid, null otherwise.
     */
    public function getValidTrustedDevice(Request $request): ?TrustedDevice
    {
        $token = $request->cookie(self::COOKIE_NAME);
        if (!$token) {
            return null;
        }

        $trustedDevice = TrustedDevice::where('token', $token)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$trustedDevice) {
            return null;
        }

        return $trustedDevice;
    }

    /**
     * Verify that the current request's fingerprint matches the trusted device.
     * Allows for some tolerance since browser fingerprints can vary slightly.
     */
    public function verifyFingerprint(Request $request, TrustedDevice $trustedDevice): bool
    {
        $currentFingerprint = $this->generateFingerprint($request);

        // For now, exact match required. Could implement fuzzy matching later.
        return hash_equals($trustedDevice->fingerprint_hash, $currentFingerprint);
    }

    /**
     * Check if user has ANY valid trusted device (for displaying option in UI).
     */
    public function userHasTrustedDevices(User $user): bool
    {
        return $user->trustedDevices()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Create a new trusted device for the user.
     * Returns the device model and the token to store in cookie.
     */
    public function trustDevice(User $user, Request $request): TrustedDevice
    {
        $token = Str::random(64);
        $fingerprint = $this->generateFingerprint($request);

        $trustedDevice = TrustedDevice::create([
            'user_id' => $user->id,
            'token' => $token,
            'fingerprint_hash' => $fingerprint,
            'device_name' => TrustedDevice::parseDeviceName($request->userAgent() ?? 'Unknown'),
            'ip_address' => $request->ip(),
            'trusted_at' => now(),
            'expires_at' => now()->addDays(self::TRUST_DURATION_DAYS),
            'last_used_at' => now(),
        ]);

        $trustedDevice->logs()->create([
            'action' => 'created',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fingerprint_matched' => true,
            'created_at' => now(),
        ]);

        return $trustedDevice;
    }

    /**
     * Get the cookie to set for trusting this device.
     */
    public function getTrustCookie(TrustedDevice $trustedDevice): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $trustedDevice->token,
            self::TRUST_DURATION_DAYS * 24 * 60, // minutes
            '/',
            null,
            true,  // secure (HTTPS only)
            true,  // httpOnly (not accessible via JavaScript)
            false, // raw
            'Lax' // SameSite - must be Lax (not Strict) to work with login redirects
        );
    }

    /**
     * Get the cookie to clear the trusted device.
     */
    public function getForgetCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->forget(self::COOKIE_NAME);
    }

    /**
     * Check if the current request comes from a trusted device for the given user.
     * This is the main entry point for authentication bypass logic.
     */
    public function isTrustedRequest(Request $request, User $user): bool
    {
        $trustedDevice = $this->getValidTrustedDevice($request);

        if (!$trustedDevice) {
            return false;
        }

        // Must belong to this user
        if ($trustedDevice->user_id !== $user->id) {
            return false;
        }

        // Fingerprint verification (optional - can be strict or lenient)
        // For now, we'll be lenient and just check token validity
        // The fingerprint is logged but doesn't block access
        $fingerprintMatched = $this->verifyFingerprint($request, $trustedDevice);

        // Record the use
        $trustedDevice->recordUse('two_fa_skip', $fingerprintMatched);

        return true;
    }

    /**
     * Get all trusted devices for a user.
     */
    public function getUserTrustedDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->trustedDevices()
            ->orderByDesc('last_used_at')
            ->get();
    }

    /**
     * Revoke all trusted devices for a user.
     */
    public function revokeAllUserDevices(User $user, string $revokedBy = 'admin'): int
    {
        $count = 0;
        $devices = $user->trustedDevices()
            ->where('revoked', false)
            ->get();

        foreach ($devices as $device) {
            $device->revoke($revokedBy);
            $count++;
        }

        return $count;
    }

    /**
     * Check if request IP is in the allowed range.
     */
    public function isAllowedIp(string $ip): bool
    {
        $allowedRanges = [
            '163.182.0.0/16',
            '104.247.0.0/16',
            '45.59.0.0/16',
            '136.175.0.0/16',
            '206.130.0.0/16',
            '23.155.0.0/16',
        ];

        foreach ($allowedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    private function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
}
