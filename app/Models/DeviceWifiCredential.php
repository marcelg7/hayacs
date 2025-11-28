<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceWifiCredential extends Model
{
    protected $fillable = [
        'device_id',
        'ssid',
        'main_password',
        'guest_ssid',
        'guest_password',
        'guest_enabled',
        'set_by',
    ];

    protected $casts = [
        'guest_enabled' => 'boolean',
    ];

    /**
     * Get the device that owns the credentials.
     */
    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }
}
