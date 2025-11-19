<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskMetric extends Model
{
    protected $fillable = [
        'device_id',
        'task_type',
        'period_start',
        'period_end',
        'total_tasks',
        'successful_tasks',
        'failed_tasks',
        'avg_execution_time_ms',
        'most_common_error',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_tasks === 0) {
            return 0;
        }
        return round(($this->successful_tasks / $this->total_tasks) * 100, 2);
    }

    public function getFailureRateAttribute(): float
    {
        if ($this->total_tasks === 0) {
            return 0;
        }
        return round(($this->failed_tasks / $this->total_tasks) * 100, 2);
    }
}
