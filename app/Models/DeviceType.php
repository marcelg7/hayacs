<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceType extends Model
{
    protected $fillable = [
        'name',
        'manufacturer',
        'product_class',
        'oui',
        'description',
    ];

    /**
     * Get all firmware versions for this device type
     */
    public function firmware(): HasMany
    {
        return $this->hasMany(Firmware::class);
    }

    /**
     * Get the active firmware version for this device type
     */
    public function activeFirmware()
    {
        return $this->hasMany(Firmware::class)->where('is_active', true)->first();
    }

    /**
     * Get all devices matching this device type
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'product_class', 'product_class');
    }
}
