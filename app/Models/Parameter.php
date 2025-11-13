<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parameter extends Model
{
    protected $fillable = [
        'device_id',
        'name',
        'value',
        'type',
        'writable',
        'last_updated',
    ];

    protected $casts = [
        'writable' => 'boolean',
        'last_updated' => 'datetime',
    ];

    /**
     * Get the device that owns this parameter
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
