<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeedTestResult extends Model
{
    protected $fillable = [
        'device_id',
        'task_id',
        'download_speed_kbps',
        'upload_speed_kbps',
        'latency_ms',
        'jitter_ms',
        'packet_loss_percent',
        'test_duration_seconds',
        'test_server_url',
        'diagnostics_state',
        'rom_time',
    ];

    protected $casts = [
        'rom_time' => 'datetime',
        'packet_loss_percent' => 'decimal:2',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getDownloadSpeedMbpsAttribute(): float
    {
        return round($this->download_speed_kbps / 1000, 2);
    }

    public function getUploadSpeedMbpsAttribute(): float
    {
        return round($this->upload_speed_kbps / 1000, 2);
    }
}
