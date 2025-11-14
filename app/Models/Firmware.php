<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Firmware extends Model
{
    protected $table = 'firmware';

    protected $fillable = [
        'device_type_id',
        'version',
        'file_name',
        'file_path',
        'file_size',
        'file_hash',
        'release_notes',
        'is_active',
        'download_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * Get the device type this firmware belongs to
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    /**
     * Generate the full download URL for TR-069
     */
    public function getFullDownloadUrl(): string
    {
        if ($this->download_url) {
            return $this->download_url;
        }

        // Generate URL based on Laravel storage
        return url('/storage/firmware/' . $this->file_path);
    }
}
