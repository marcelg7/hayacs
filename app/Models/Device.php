<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'subscriber_id',
        'manufacturer',
        'oui',
        'product_class',
        'model_name',
        'serial_number',
        'hardware_version',
        'software_version',
        'ip_address',
        'connection_request_url',
        'connection_request_username',
        'connection_request_password',
        'udp_connection_request_address',
        'stun_enabled',
        'online',
        'last_inform',
        'remote_gui_enabled_at',
        'initial_backup_created',
        'last_backup_at',
        'last_refresh_at',
        'auto_provisioned',
        'tags',
    ];

    protected $casts = [
        'online' => 'boolean',
        'stun_enabled' => 'boolean',
        'initial_backup_created' => 'boolean',
        'auto_provisioned' => 'boolean',
        'last_inform' => 'datetime',
        'remote_gui_enabled_at' => 'datetime',
        'last_backup_at' => 'datetime',
        'last_refresh_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Get all parameters for this device
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class, 'device_id');
    }

    /**
     * Get all tasks for this device
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'device_id');
    }

    /**
     * Get all sessions for this device
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CwmpSession::class, 'device_id');
    }

    /**
     * Get all configuration backups for this device
     */
    public function configBackups(): HasMany
    {
        return $this->hasMany(ConfigBackup::class, 'device_id');
    }

    /**
     * Get the subscriber that owns this device
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get health snapshots for this device
     */
    public function healthSnapshots(): HasMany
    {
        return $this->hasMany(DeviceHealthSnapshot::class, 'device_id');
    }

    /**
     * Get task metrics for this device
     */
    public function taskMetrics(): HasMany
    {
        return $this->hasMany(TaskMetric::class, 'device_id');
    }

    /**
     * Get all events for this device
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }

    /**
     * Get recent boot events for reboot frequency analysis
     */
    public function recentBoots(int $hours = 24)
    {
        return $this->events()
            ->boots()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get parameter history for this device
     */
    public function parameterHistory(): HasMany
    {
        return $this->hasMany(ParameterHistory::class, 'device_id');
    }

    /**
     * Get speed test results for this device
     */
    public function speedTestResults(): HasMany
    {
        return $this->hasMany(SpeedTestResult::class, 'device_id');
    }

    /**
     * Get pending tasks for this device
     */
    public function pendingTasks(): HasMany
    {
        return $this->tasks()->where('status', 'pending');
    }

    /**
     * Detect which TR-069 data model this device uses
     * Returns 'TR-098' or 'TR-181'
     */
    public function getDataModel(): string
    {
        $hasIgdParams = $this->parameters()
            ->where('name', 'like', 'InternetGatewayDevice.%')
            ->exists();

        return $hasIgdParams ? 'TR-098' : 'TR-181';
    }

    /**
     * Mark device as online and update last inform time
     */
    public function markOnline(): void
    {
        $this->update([
            'online' => true,
            'last_inform' => now(),
        ]);
    }

    /**
     * Get a specific parameter value
     */
    public function getParameter(string $name): ?string
    {
        return $this->parameters()
            ->where('name', $name)
            ->value('value');
    }

    /**
     * Set or update a parameter
     */
    public function setParameter(string $name, string $value, ?string $type = null, bool $writable = false): Parameter
    {
        return $this->parameters()->updateOrCreate(
            ['name' => $name],
            [
                'value' => $value,
                'type' => $type,
                'writable' => $writable,
                'last_updated' => now(),
            ]
        );
    }
}
