<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDeviceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trusted_device_id',
        'action',
        'ip_address',
        'user_agent',
        'fingerprint_matched',
        'created_at',
    ];

    protected $casts = [
        'fingerprint_matched' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the trusted device this log belongs to.
     */
    public function trustedDevice(): BelongsTo
    {
        return $this->belongsTo(TrustedDevice::class);
    }

    /**
     * Get the action label for display.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'login_bypass' => 'Logged in (bypassed IP restriction)',
            'two_fa_skip' => 'Skipped 2FA',
            'created' => 'Device trusted',
            'revoked' => 'Trust revoked',
            default => $this->action,
        };
    }
}
