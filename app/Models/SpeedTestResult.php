<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeedTestResult extends Model
{
    protected $fillable = [
        'device_id',
        'download_speed_mbps',
        'upload_speed_mbps',
        'download_bytes',
        'upload_bytes',
        'download_duration_ms',
        'upload_duration_ms',
        'download_state',
        'upload_state',
        'download_start_time',
        'download_end_time',
        'upload_start_time',
        'upload_end_time',
        'test_type',
    ];

    protected $casts = [
        'download_speed_mbps' => 'decimal:2',
        'upload_speed_mbps' => 'decimal:2',
        'download_start_time' => 'datetime',
        'download_end_time' => 'datetime',
        'upload_start_time' => 'datetime',
        'upload_end_time' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }
}
