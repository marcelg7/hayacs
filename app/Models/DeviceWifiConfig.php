<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeviceWifiConfig extends Model
{
    protected $fillable = [
        'device_id',
        'interface_name',
        'radio',
        'band',
        'ssid',
        'password_encrypted',
        'encryption',
        'hidden',
        'enabled',
        'network_type',
        'is_mesh_backhaul',
        'max_clients',
        'client_isolation',
        'wps_enabled',
        'mac_address',
        'raw_uci_config',
        'extracted_at',
        'extraction_method',
        'data_model',
        'migrated_to_tr181',
        'migrated_at',
    ];

    protected $casts = [
        'hidden' => 'boolean',
        'enabled' => 'boolean',
        'is_mesh_backhaul' => 'boolean',
        'client_isolation' => 'boolean',
        'wps_enabled' => 'boolean',
        'migrated_to_tr181' => 'boolean',
        'max_clients' => 'integer',
        'extracted_at' => 'datetime',
        'migrated_at' => 'datetime',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * Get the device that owns this WiFi config
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Set the WiFi password (encrypts automatically)
     */
    public function setPassword(string $password): void
    {
        $this->password_encrypted = Crypt::encryptString($password);
    }

    /**
     * Get the decrypted WiFi password
     */
    public function getPassword(): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if this is a primary network (not guest, backhaul, etc.)
     */
    public function isPrimary(): bool
    {
        return $this->network_type === 'primary';
    }

    /**
     * Check if this is a guest network
     */
    public function isGuest(): bool
    {
        return $this->network_type === 'guest';
    }

    /**
     * Check if this is a mesh backhaul network
     */
    public function isBackhaul(): bool
    {
        return $this->is_mesh_backhaul || $this->network_type === 'backhaul';
    }

    /**
     * Get display-friendly band name
     */
    public function getBandDisplayAttribute(): string
    {
        return $this->band;
    }

    /**
     * Get the TR-098 parameter path for this interface's SSID
     */
    public function getTr098SsidPath(): string
    {
        $wlanIndex = $this->getWlanConfigurationIndex();
        return "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.SSID";
    }

    /**
     * Get the TR-098 parameter path for this interface's password
     */
    public function getTr098PasswordPath(): string
    {
        $wlanIndex = $this->getWlanConfigurationIndex();
        return "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.PreSharedKey.1.KeyPassphrase";
    }

    /**
     * Get the TR-181 parameter path for this interface's SSID
     */
    public function getTr181SsidPath(): string
    {
        $ssidIndex = $this->getSsidIndex();
        return "Device.WiFi.SSID.{$ssidIndex}.SSID";
    }

    /**
     * Get the TR-181 parameter path for this interface's password
     */
    public function getTr181PasswordPath(): string
    {
        $apIndex = $this->getAccessPointIndex();
        return "Device.WiFi.AccessPoint.{$apIndex}.Security.KeyPassphrase";
    }

    /**
     * Map interface name to TR-098 WLANConfiguration index
     * Based on Nokia Beacon G6 mapping:
     * wifi0 (5GHz): ath0=1, ath01=2, ath02=3, ath03=4, ath04=5, ath05=6
     * wifi1 (2.4GHz): ath1=5, ath11=6, ath12=7, ath13=8
     */
    protected function getWlanConfigurationIndex(): int
    {
        $mapping = [
            // 5GHz (wifi0)
            'ath0' => 5,   // Primary 5GHz
            'ath01' => 6,  // Secondary 5GHz
            'ath02' => 7,  // Reserved 5GHz
            'ath03' => 8,  // Guest 5GHz
            'ath04' => 9,  // Backhaul 5GHz
            'ath05' => 10, // Backhaul 5GHz (VLAN)
            // 2.4GHz (wifi1)
            'ath1' => 1,   // Primary 2.4GHz
            'ath11' => 2,  // Secondary 2.4GHz
            'ath12' => 3,  // Reserved 2.4GHz
            'ath13' => 4,  // Guest 2.4GHz
        ];

        return $mapping[$this->interface_name] ?? 1;
    }

    /**
     * Map to TR-181 SSID index (simplified, may need adjustment per device)
     */
    protected function getSsidIndex(): int
    {
        return $this->getWlanConfigurationIndex();
    }

    /**
     * Map to TR-181 AccessPoint index
     */
    protected function getAccessPointIndex(): int
    {
        return $this->getWlanConfigurationIndex();
    }

    /**
     * Mark this config as migrated to TR-181
     */
    public function markMigrated(): void
    {
        $this->update([
            'migrated_to_tr181' => true,
            'migrated_at' => now(),
        ]);
    }

    /**
     * Scope: Only enabled networks
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: Only primary networks (not backhaul)
     */
    public function scopePrimary($query)
    {
        return $query->where('is_mesh_backhaul', false)
            ->whereNotIn('network_type', ['backhaul']);
    }

    /**
     * Scope: Customer-facing networks (primary, secondary, guest - not backhaul)
     */
    public function scopeCustomerFacing($query)
    {
        return $query->whereIn('network_type', ['primary', 'secondary', 'guest']);
    }
}
