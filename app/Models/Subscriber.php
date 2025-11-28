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

    /**
     * Check if this subscriber has Cable Internet service.
     */
    public function isCableInternet(): bool
    {
        return stripos($this->service_type ?? '', 'Cable Internet') !== false;
    }

    /**
     * Get the cable portal URL for a given MAC address.
     * Formats MAC as xxxx.xxxx.xxxx format.
     */
    public function getCablePortalUrl(string $macAddress): string
    {
        $formattedMac = self::formatMacForCablePortal($macAddress);
        return "https://noms.ezlink.ca:8001/info_all.cgi?SubscriberId={$this->customer}&J=CableModem%20{$formattedMac}";
    }

    /**
     * Format a MAC address to xxxx.xxxx.xxxx format for cable portal.
     */
    public static function formatMacForCablePortal(string $macAddress): string
    {
        // Remove any existing separators (colons, dashes, dots)
        $clean = strtolower(preg_replace('/[:\-\.]/', '', $macAddress));

        // Ensure we have exactly 12 hex characters
        if (strlen($clean) !== 12) {
            return $macAddress; // Return original if invalid
        }

        // Format as xxxx.xxxx.xxxx
        return substr($clean, 0, 4) . '.' . substr($clean, 4, 4) . '.' . substr($clean, 8, 4);
    }
}
