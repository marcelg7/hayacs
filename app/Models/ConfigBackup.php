<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigBackup extends Model
{
    protected $fillable = [
        'device_id',
        'name',
        'description',
        'backup_data',
        'is_auto',
        'parameter_count',
    ];

    protected $casts = [
        'backup_data' => 'array',
        'is_auto' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the device that owns this backup
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    /**
     * Get human-readable size of backup data
     */
    public function getSizeAttribute(): string
    {
        $bytes = strlen(json_encode($this->backup_data));
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
