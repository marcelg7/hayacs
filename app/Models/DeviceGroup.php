<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'match_type',
        'is_active',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Rules that determine group membership
     */
    public function rules(): HasMany
    {
        return $this->hasMany(DeviceGroupRule::class)->orderBy('order');
    }

    /**
     * Workflows assigned to this group
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(GroupWorkflow::class);
    }

    /**
     * User who created this group
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this group
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if a device matches this group's rules
     */
    public function matchesDevice(Device $device): bool
    {
        $rules = $this->rules;

        if ($rules->isEmpty()) {
            return false; // No rules = no matches
        }

        if ($this->match_type === 'all') {
            // AND logic - all rules must match
            foreach ($rules as $rule) {
                if (!$rule->matchesDevice($device)) {
                    return false;
                }
            }
            return true;
        } else {
            // OR logic - any rule must match
            foreach ($rules as $rule) {
                if ($rule->matchesDevice($device)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Get all devices that match this group's rules
     */
    public function getMatchingDevices()
    {
        return Device::all()->filter(fn($device) => $this->matchesDevice($device));
    }

    /**
     * Get count of devices that match this group's rules
     */
    public function getMatchingDeviceCount(): int
    {
        return Device::all()->filter(fn($device) => $this->matchesDevice($device))->count();
    }

    /**
     * Scope to get only active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
