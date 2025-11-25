<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = [
        'customer',
        'account',
        'agreement',
        'name',
        'service_type',
        'connection_date',
    ];

    protected $casts = [
        'connection_date' => 'date',
    ];

    /**
     * Get the equipment for this subscriber.
     */
    public function equipment()
    {
        return $this->hasMany(SubscriberEquipment::class);
    }

    /**
     * Get the devices for this subscriber.
     */
    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
