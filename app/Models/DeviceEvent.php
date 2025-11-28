<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEvent extends Model
{
    protected $fillable = [
        'device_id',
        'event_code',
        'event_type',
        'command_key',
        'details',
        'source_ip',
        'session_id',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Standard TR-069 event codes mapped to normalized types.
     */
    public const EVENT_TYPES = [
        '0 BOOTSTRAP' => 'bootstrap',
        '1 BOOT' => 'boot',
        '2 PERIODIC' => 'periodic',
        '3 SCHEDULED' => 'scheduled',
        '4 VALUE CHANGE' => 'value_change',
        '5 KICKED' => 'kicked',
        '6 CONNECTION REQUEST' => 'connection_request',
        '7 TRANSFER COMPLETE' => 'transfer_complete',
        '8 DIAGNOSTICS COMPLETE' => 'diagnostics_complete',
        '9 REQUEST DOWNLOAD' => 'request_download',
        '10 AUTONOMOUS TRANSFER COMPLETE' => 'autonomous_transfer_complete',
        '11 DU STATE CHANGE COMPLETE' => 'du_state_change',
        '12 AUTONOMOUS DU STATE CHANGE COMPLETE' => 'autonomous_du_state_change',
    ];

    /**
     * Human-readable labels for event types.
     */
    public const EVENT_LABELS = [
        'bootstrap' => 'Factory Reset / First Boot',
        'boot' => 'Reboot',
        'periodic' => 'Periodic Check-in',
        'scheduled' => 'Scheduled Inform',
        'value_change' => 'Parameter Changed',
        'kicked' => 'Kicked',
        'connection_request' => 'Connection Request',
        'transfer_complete' => 'Transfer Complete',
        'diagnostics_complete' => 'Diagnostics Complete',
        'request_download' => 'Firmware Request',
        'autonomous_transfer_complete' => 'Auto Transfer Complete',
        'du_state_change' => 'Software Module Change',
        'autonomous_du_state_change' => 'Auto Software Module Change',
    ];

    /**
     * Get the device that owns this event.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the normalized event type from an event code.
     */
    public static function normalizeEventCode(string $eventCode): string
    {
        // Check standard events first
        if (isset(self::EVENT_TYPES[$eventCode])) {
            return self::EVENT_TYPES[$eventCode];
        }

        // Handle manufacturer-specific events (M <OUI> <Event>)
        if (str_starts_with($eventCode, 'M ')) {
            return 'manufacturer_specific';
        }

        // Handle vendor extension events (X_<VENDOR>_Event)
        if (str_starts_with($eventCode, 'X_')) {
            return 'vendor_extension';
        }

        return 'unknown';
    }

    /**
     * Get human-readable label for this event.
     */
    public function getLabelAttribute(): string
    {
        return self::EVENT_LABELS[$this->event_type] ?? $this->event_code;
    }

    /**
     * Scope to get events of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to get recent events.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get boot events (for reboot frequency analysis).
     */
    public function scopeBoots($query)
    {
        return $query->whereIn('event_type', ['boot', 'bootstrap']);
    }

    /**
     * Check if this is a reboot-related event.
     */
    public function isRebootEvent(): bool
    {
        return in_array($this->event_type, ['boot', 'bootstrap']);
    }

    /**
     * Check if this is a transfer-related event.
     */
    public function isTransferEvent(): bool
    {
        return in_array($this->event_type, ['transfer_complete', 'autonomous_transfer_complete']);
    }
}
