<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'template_data',
        'parameter_patterns',
        'device_model_filter',
        'tags',
        'created_by_device_id',
        'is_public',
    ];

    protected $casts = [
        'template_data' => 'array',
        'parameter_patterns' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the device that this template was created from
     */
    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'created_by_device_id', 'id');
    }

    /**
     * Get human-readable size of template data
     */
    public function getSizeAttribute(): string
    {
        $bytes = strlen(json_encode($this->template_data));
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get the count of parameters in this template
     */
    public function getParameterCountAttribute(): int
    {
        return count($this->template_data);
    }
}
