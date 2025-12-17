<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'password_changed_at',
        'must_change_password',
        'two_factor_secret',
        'two_factor_enabled_at',
        'two_factor_grace_started_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_enabled_at' => 'datetime',
            'two_factor_grace_started_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Check if the user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a support user
     */
    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    /**
     * Check if the user has admin or support role
     */
    public function isAdminOrSupport(): bool
    {
        return in_array($this->role, ['admin', 'support']);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_enabled_at) && !is_null($this->two_factor_secret);
    }

    /**
     * Check if user is still within 2FA grace period
     */
    public function isInTwoFactorGracePeriod(): bool
    {
        if ($this->hasTwoFactorEnabled()) {
            return false;
        }

        $graceStart = $this->two_factor_grace_started_at ?? $this->created_at;

        if (!$graceStart) {
            return true;
        }

        return $graceStart->addDays(14)->isFuture();
    }

    /**
     * Check if 2FA setup is required (grace period expired)
     */
    public function requiresTwoFactorSetup(): bool
    {
        return !$this->hasTwoFactorEnabled() && !$this->isInTwoFactorGracePeriod();
    }

    /**
     * Get days remaining in grace period
     */
    public function getTwoFactorGraceDaysRemaining(): int
    {
        if ($this->hasTwoFactorEnabled()) {
            return 0;
        }

        $graceStart = $this->two_factor_grace_started_at ?? $this->created_at;

        if (!$graceStart) {
            return 14;
        }

        $graceEnd = $graceStart->addDays(14);

        return max(0, (int) now()->diffInDays($graceEnd, false));
    }

    /**
     * Enable 2FA for user (called after first successful verification)
     */
    public function enableTwoFactor(string $secret): void
    {
        $this->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ]);
    }

    /**
     * Disable 2FA for user (admin reset)
     */
    public function disableTwoFactor(): void
    {
        $this->update([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
            'two_factor_grace_started_at' => now(),
        ]);
    }

    /**
     * Get the trusted devices for this user.
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * Get active (non-revoked, non-expired) trusted devices.
     */
    public function activeTrustedDevices(): HasMany
    {
        return $this->trustedDevices()
            ->where('revoked', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Get tasks initiated by this user.
     */
    public function initiatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'initiated_by_user_id');
    }
}
