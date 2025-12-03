<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeviceSshCredential extends Model
{
    protected $fillable = [
        'device_id',
        'serial_number',
        'ssh_username',
        'ssh_password_encrypted',
        'shell_password_encrypted',
        'ssh_port',
        'verified',
        'last_ssh_success',
        'last_ssh_failure',
        'last_error',
        'credential_source',
        'imported_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'last_ssh_success' => 'datetime',
        'last_ssh_failure' => 'datetime',
        'imported_at' => 'datetime',
        'ssh_port' => 'integer',
    ];

    protected $hidden = [
        'ssh_password_encrypted',
        'shell_password_encrypted',
    ];

    /**
     * Get the device that owns these credentials
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Set the SSH password (encrypts automatically)
     */
    public function setSshPassword(string $password): void
    {
        $this->ssh_password_encrypted = Crypt::encryptString($password);
    }

    /**
     * Get the decrypted SSH password
     */
    public function getSshPassword(): ?string
    {
        if (empty($this->ssh_password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->ssh_password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the shell password (encrypts automatically)
     */
    public function setShellPassword(string $password): void
    {
        $this->shell_password_encrypted = Crypt::encryptString($password);
    }

    /**
     * Get the decrypted shell password
     */
    public function getShellPassword(): ?string
    {
        if (empty($this->shell_password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->shell_password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mark credentials as verified after successful SSH connection
     */
    public function markVerified(): void
    {
        $this->update([
            'verified' => true,
            'last_ssh_success' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Record SSH connection failure
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'last_ssh_failure' => now(),
            'last_error' => $error,
        ]);
    }

    /**
     * Check if credentials have SSH access (Layer 1)
     */
    public function hasSshAccess(): bool
    {
        return !empty($this->ssh_password_encrypted);
    }

    /**
     * Check if credentials have shell access (Layer 2)
     */
    public function hasShellAccess(): bool
    {
        return !empty($this->shell_password_encrypted);
    }

    /**
     * Check if credentials are complete (both layers)
     */
    public function isComplete(): bool
    {
        return $this->hasSshAccess() && $this->hasShellAccess();
    }
}
