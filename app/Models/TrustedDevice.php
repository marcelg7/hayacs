<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'fingerprint_hash',
        'device_name',
        'ip_address',
        'trusted_at',
        'expires_at',
        'last_used_at',
        'revoked',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    /**
     * Get the user that owns the trusted device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the access logs for this device.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(TrustedDeviceLog::class);
    }

    /**
     * Check if the device trust is still valid.
     */
    public function isValid(): bool
    {
        return !$this->revoked
            && $this->expires_at->isFuture();
    }

    /**
     * Revoke this trusted device.
     */
    public function revoke(string $revokedBy = 'user'): void
    {
        $this->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy,
        ]);

        $this->logs()->create([
            'action' => 'revoked',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fingerprint_matched' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a login bypass using this trusted device.
     */
    public function recordUse(string $action = 'login_bypass', bool $fingerprintMatched = true): void
    {
        $this->update([
            'last_used_at' => now(),
            'ip_address' => request()->ip(),
        ]);

        $this->logs()->create([
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fingerprint_matched' => $fingerprintMatched,
            'created_at' => now(),
        ]);
    }

    /**
     * Get human-readable device name from User-Agent.
     */
    public static function parseDeviceName(string $userAgent): string
    {
        $device = 'Unknown Device';

        // Check for mobile devices
        if (preg_match('/iPhone/', $userAgent)) {
            $device = 'iPhone';
        } elseif (preg_match('/iPad/', $userAgent)) {
            $device = 'iPad';
        } elseif (preg_match('/Android/', $userAgent)) {
            if (preg_match('/Mobile/', $userAgent)) {
                $device = 'Android Phone';
            } else {
                $device = 'Android Tablet';
            }
        } elseif (preg_match('/Windows/', $userAgent)) {
            $device = 'Windows PC';
        } elseif (preg_match('/Macintosh/', $userAgent)) {
            $device = 'Mac';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $device = 'Linux PC';
        }

        // Add browser
        if (preg_match('/Chrome\/[\d.]+/', $userAgent) && !preg_match('/Edg/', $userAgent)) {
            $device .= ' (Chrome)';
        } elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
            $device .= ' (Firefox)';
        } elseif (preg_match('/Safari\/[\d.]+/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $device .= ' (Safari)';
        } elseif (preg_match('/Edg\/[\d.]+/', $userAgent)) {
            $device .= ' (Edge)';
        }

        return $device;
    }
}
