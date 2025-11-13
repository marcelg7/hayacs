<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CwmpSession extends Model
{
    protected $table = 'cwmp_sessions';

    protected $fillable = [
        'device_id',
        'inform_events',
        'messages_exchanged',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'inform_events' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the device that owns this session
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * End the session
     */
    public function end(): void
    {
        $this->update(['ended_at' => now()]);
    }

    /**
     * Increment messages exchanged count
     */
    public function incrementMessages(): void
    {
        $this->increment('messages_exchanged');
    }
}
