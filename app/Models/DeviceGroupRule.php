<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceGroupRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_group_id',
        'field',
        'operator',
        'value',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Available fields for matching
     */
    public const MATCHABLE_FIELDS = [
        'oui' => 'Manufacturer OUI',
        'manufacturer' => 'Manufacturer Name',
        'product_class' => 'Product Class/Model',
        'software_version' => 'Firmware Version',
        'hardware_version' => 'Hardware Version',
        'data_model' => 'Data Model (TR-098/TR-181)',
        'serial_number' => 'Serial Number',
        'ip_address' => 'IP Address',
        'online' => 'Online Status',
        'subscriber_id' => 'Subscriber ID',
        'tags' => 'Tags',
        'last_inform' => 'Last Inform Time',
        'created_at' => 'First Seen Date',
    ];

    /**
     * Available operators
     */
    public const OPERATORS = [
        'equals' => 'Equals',
        'not_equals' => 'Not Equals',
        'contains' => 'Contains',
        'not_contains' => 'Does Not Contain',
        'starts_with' => 'Starts With',
        'ends_with' => 'Ends With',
        'less_than' => 'Less Than',
        'greater_than' => 'Greater Than',
        'less_than_or_equals' => 'Less Than or Equals',
        'greater_than_or_equals' => 'Greater Than or Equals',
        'regex' => 'Matches Regex',
        'in' => 'In List',
        'not_in' => 'Not In List',
        'is_null' => 'Is Empty',
        'is_not_null' => 'Is Not Empty',
    ];

    /**
     * The device group this rule belongs to
     */
    public function deviceGroup(): BelongsTo
    {
        return $this->belongsTo(DeviceGroup::class);
    }

    /**
     * Check if a device matches this rule
     */
    public function matchesDevice(Device $device): bool
    {
        $deviceValue = $this->getDeviceFieldValue($device, $this->field);
        $ruleValue = $this->value;

        return match ($this->operator) {
            'equals' => $this->compareEquals($deviceValue, $ruleValue),
            'not_equals' => !$this->compareEquals($deviceValue, $ruleValue),
            'contains' => $this->compareContains($deviceValue, $ruleValue),
            'not_contains' => !$this->compareContains($deviceValue, $ruleValue),
            'starts_with' => $this->compareStartsWith($deviceValue, $ruleValue),
            'ends_with' => $this->compareEndsWith($deviceValue, $ruleValue),
            'less_than' => $this->compareLessThan($deviceValue, $ruleValue),
            'greater_than' => $this->compareGreaterThan($deviceValue, $ruleValue),
            'less_than_or_equals' => $this->compareLessThanOrEquals($deviceValue, $ruleValue),
            'greater_than_or_equals' => $this->compareGreaterThanOrEquals($deviceValue, $ruleValue),
            'regex' => $this->compareRegex($deviceValue, $ruleValue),
            'in' => $this->compareIn($deviceValue, $ruleValue),
            'not_in' => !$this->compareIn($deviceValue, $ruleValue),
            'is_null' => $this->compareIsNull($deviceValue),
            'is_not_null' => !$this->compareIsNull($deviceValue),
            default => false,
        };
    }

    /**
     * Get the value of a field from a device
     */
    private function getDeviceFieldValue(Device $device, string $field): mixed
    {
        return match ($field) {
            'oui' => $device->oui,
            'manufacturer' => $device->manufacturer,
            'product_class' => $device->product_class,
            'software_version' => $device->software_version,
            'hardware_version' => $device->hardware_version,
            'data_model' => $device->getDataModel(),
            'serial_number' => $device->serial_number,
            'ip_address' => $device->ip_address,
            'online' => $device->online ? 'true' : 'false',
            'subscriber_id' => $device->subscriber_id,
            'tags' => $device->tags,
            'last_inform' => $device->last_inform,
            'created_at' => $device->created_at,
            default => null,
        };
    }

    private function compareEquals($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        return strtolower((string) $deviceValue) === strtolower((string) $ruleValue);
    }

    private function compareContains($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null || $ruleValue === null) {
            return false;
        }
        return str_contains(strtolower((string) $deviceValue), strtolower((string) $ruleValue));
    }

    private function compareStartsWith($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null || $ruleValue === null) {
            return false;
        }
        return str_starts_with(strtolower((string) $deviceValue), strtolower((string) $ruleValue));
    }

    private function compareEndsWith($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null || $ruleValue === null) {
            return false;
        }
        return str_ends_with(strtolower((string) $deviceValue), strtolower((string) $ruleValue));
    }

    private function compareLessThan($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        // Handle version comparison
        if ($this->field === 'software_version' || $this->field === 'hardware_version') {
            return version_compare((string) $deviceValue, (string) $ruleValue, '<');
        }
        // Handle date comparison
        if ($deviceValue instanceof \DateTimeInterface) {
            return $deviceValue < new \DateTime($ruleValue);
        }
        return $deviceValue < $ruleValue;
    }

    private function compareGreaterThan($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        // Handle version comparison
        if ($this->field === 'software_version' || $this->field === 'hardware_version') {
            return version_compare((string) $deviceValue, (string) $ruleValue, '>');
        }
        // Handle date comparison
        if ($deviceValue instanceof \DateTimeInterface) {
            return $deviceValue > new \DateTime($ruleValue);
        }
        return $deviceValue > $ruleValue;
    }

    private function compareLessThanOrEquals($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        if ($this->field === 'software_version' || $this->field === 'hardware_version') {
            return version_compare((string) $deviceValue, (string) $ruleValue, '<=');
        }
        if ($deviceValue instanceof \DateTimeInterface) {
            return $deviceValue <= new \DateTime($ruleValue);
        }
        return $deviceValue <= $ruleValue;
    }

    private function compareGreaterThanOrEquals($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        if ($this->field === 'software_version' || $this->field === 'hardware_version') {
            return version_compare((string) $deviceValue, (string) $ruleValue, '>=');
        }
        if ($deviceValue instanceof \DateTimeInterface) {
            return $deviceValue >= new \DateTime($ruleValue);
        }
        return $deviceValue >= $ruleValue;
    }

    private function compareRegex($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null || $ruleValue === null) {
            return false;
        }
        return (bool) preg_match('/' . $ruleValue . '/i', (string) $deviceValue);
    }

    private function compareIn($deviceValue, $ruleValue): bool
    {
        if ($deviceValue === null) {
            return false;
        }
        $list = json_decode($ruleValue, true) ?? explode(',', $ruleValue);
        $list = array_map(fn($v) => strtolower(trim($v)), $list);
        return in_array(strtolower((string) $deviceValue), $list);
    }

    private function compareIsNull($deviceValue): bool
    {
        return $deviceValue === null || $deviceValue === '';
    }

    /**
     * Get human-readable description of this rule
     */
    public function getDescription(): string
    {
        $fieldLabel = self::MATCHABLE_FIELDS[$this->field] ?? $this->field;
        $operatorLabel = self::OPERATORS[$this->operator] ?? $this->operator;

        if (in_array($this->operator, ['is_null', 'is_not_null'])) {
            return "{$fieldLabel} {$operatorLabel}";
        }

        return "{$fieldLabel} {$operatorLabel} \"{$this->value}\"";
    }
}
