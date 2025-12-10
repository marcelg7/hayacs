<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TwoFactorRememberedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'device_name',
        'ip_address',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this remembered device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this remembered device is still valid.
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Generate a new secure token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Create a remembered device for a user.
     */
    public static function createForUser(User $user, string $deviceName, string $ipAddress): self
    {
        // Clean up expired devices for this user
        self::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        return self::create([
            'user_id' => $user->id,
            'token' => self::generateToken(),
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addDays(48),
        ]);
    }

    /**
     * Find a valid remembered device by token.
     */
    public static function findValidByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Update last used timestamp.
     */
    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Revoke all remembered devices for a user (used when 2FA is reset).
     */
    public static function revokeAllForUser(User $user): void
    {
        self::where('user_id', $user->id)->delete();
    }
}
