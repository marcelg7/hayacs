<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHealthSnapshot extends Model
{
    protected $fillable = [
        'device_id',
        'is_online',
        'last_inform_at',
        'connection_uptime_seconds',
        'inform_interval',
        'snapshot_at',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_inform_at' => 'datetime',
        'snapshot_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }
}
