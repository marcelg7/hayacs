<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportStatus extends Model
{
    protected $fillable = [
        'type',
        'status',
        'filename',
        'total_rows',
        'processed_rows',
        'subscribers_created',
        'subscribers_updated',
        'equipment_created',
        'devices_linked',
        'message',
        'started_at',
        'completed_at',
        'user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
