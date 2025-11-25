<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberEquipment extends Model
{
    protected $table = 'subscriber_equipment';

    protected $fillable = [
        'subscriber_id',
        'customer',
        'account',
        'agreement',
        'equip_item',
        'equip_desc',
        'start_date',
        'manufacturer',
        'model',
        'serial',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    /**
     * Get the subscriber that owns the equipment.
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the device that matches this equipment serial.
     */
    public function device()
    {
        return $this->hasOne(Device::class, 'serial_number', 'serial');
    }
}
