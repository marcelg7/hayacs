<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'device_id',
        'task_type',
        'parameters',
        'status',
        'result',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'result' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the device that owns this task
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Mark task as sent
     */
    public function markAsSent(): void
    {
        $this->update(['status' => 'sent']);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(?array $result = null): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if task is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
