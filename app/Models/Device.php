<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'subscriber_id',
        'manufacturer',
        'oui',
        'product_class',
        'model_name',
        'serial_number',
        'password_suffix',
        'hardware_version',
        'software_version',
        'ip_address',
        'connection_request_url',
        'connection_request_username',
        'connection_request_password',
        'udp_connection_request_address',
        'stun_enabled',
        'online',
        'last_inform',
        'remote_support_expires_at',
        'remote_support_enabled_by',
        'initial_backup_created',
        'last_backup_at',
        'last_refresh_at',
        'auto_provisioned',
        'tags',
    ];

    protected $casts = [
        'online' => 'boolean',
        'stun_enabled' => 'boolean',
        'initial_backup_created' => 'boolean',
        'auto_provisioned' => 'boolean',
        'last_inform' => 'datetime',
        'remote_support_expires_at' => 'datetime',
        'last_backup_at' => 'datetime',
        'last_refresh_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Get all parameters for this device
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class, 'device_id');
    }

    /**
     * Get all tasks for this device
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'device_id');
    }

    /**
     * Get all sessions for this device
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CwmpSession::class, 'device_id');
    }

    /**
     * Get all configuration backups for this device
     */
    public function configBackups(): HasMany
    {
        return $this->hasMany(ConfigBackup::class, 'device_id');
    }

    /**
     * Get the subscriber that owns this device
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the device type for this device (matched by product_class)
     */
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class, 'product_class', 'product_class');
    }

    /**
     * Get health snapshots for this device
     */
    public function healthSnapshots(): HasMany
    {
        return $this->hasMany(DeviceHealthSnapshot::class, 'device_id');
    }

    /**
     * Get task metrics for this device
     */
    public function taskMetrics(): HasMany
    {
        return $this->hasMany(TaskMetric::class, 'device_id');
    }

    /**
     * Get all events for this device
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }

    /**
     * Get recent boot events for reboot frequency analysis
     */
    public function recentBoots(int $hours = 24)
    {
        return $this->events()
            ->boots()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get parameter history for this device
     */
    public function parameterHistory(): HasMany
    {
        return $this->hasMany(ParameterHistory::class, 'device_id');
    }

    /**
     * Get speed test results for this device
     */
    public function speedTestResults(): HasMany
    {
        return $this->hasMany(SpeedTestResult::class, 'device_id');
    }

    /**
     * Get SSH credentials for this device
     */
    public function sshCredentials(): HasOne
    {
        return $this->hasOne(DeviceSshCredential::class, 'device_id');
    }

    /**
     * Get WiFi configurations extracted via SSH
     */
    public function wifiConfigs(): HasMany
    {
        return $this->hasMany(DeviceWifiConfig::class, 'device_id');
    }

    /**
     * Check if this device has SSH credentials configured
     */
    public function hasSshCredentials(): bool
    {
        return $this->sshCredentials()->exists();
    }

    /**
     * Check if this device has WiFi configs extracted
     */
    public function hasWifiConfigs(): bool
    {
        return $this->wifiConfigs()->exists();
    }

    /**
     * Get pending tasks for this device
     */
    public function pendingTasks(): HasMany
    {
        return $this->tasks()->where('status', 'pending');
    }

    /**
     * Detect which TR-069 data model this device uses
     * Returns 'TR-098' or 'TR-181'
     */
    public function getDataModel(): string
    {
        $hasIgdParams = $this->parameters()
            ->where('name', 'like', 'InternetGatewayDevice.%')
            ->exists();

        return $hasIgdParams ? 'TR-098' : 'TR-181';
    }

    /**
     * Mark device as online and update last inform time
     */
    public function markOnline(): void
    {
        $this->update([
            'online' => true,
            'last_inform' => now(),
        ]);
    }

    /**
     * Check if device is currently online based on last inform time
     * Device is considered online if it informed within the expected window
     * (2x periodic inform interval, or 15 minutes if no interval known)
     */
    public function isOnline(): bool
    {
        if (!$this->last_inform) {
            return false;
        }

        // Get the device's periodic inform interval from parameters
        $intervalParam = $this->getParameter('Device.ManagementServer.PeriodicInformInterval')
            ?? $this->getParameter('InternetGatewayDevice.ManagementServer.PeriodicInformInterval');

        // Default to 10 minutes if not found, use 2x interval as grace period
        $intervalSeconds = $intervalParam ? (int) $intervalParam : 600;
        $gracePeriodMinutes = max(15, ($intervalSeconds * 2) / 60);

        return $this->last_inform->gt(now()->subMinutes($gracePeriodMinutes));
    }

    /**
     * Accessor to compute online status dynamically
     * This overrides the stored 'online' column value
     */
    public function getOnlineAttribute($value): bool
    {
        return $this->isOnline();
    }

    /**
     * Get a specific parameter value
     */
    public function getParameter(string $name): ?string
    {
        return $this->parameters()
            ->where('name', $name)
            ->value('value');
    }

    /**
     * Set or update a parameter
     */
    public function setParameter(string $name, string $value, ?string $type = null, bool $writable = false): Parameter
    {
        return $this->parameters()->updateOrCreate(
            ['name' => $name],
            [
                'value' => $value,
                'type' => $type,
                'writable' => $writable,
                'last_updated' => now(),
            ]
        );
    }

    /**
     * User who enabled remote support
     */
    public function remoteSupportEnabledBy()
    {
        return $this->belongsTo(User::class, 'remote_support_enabled_by');
    }

    // =========================================================================
    // Password Management for Nokia Beacon G6 Devices
    // =========================================================================

    /**
     * Check if this device is a Nokia Beacon (supports password management)
     */
    public function isNokiaBeacon(): bool
    {
        // Nokia Beacon devices have Nokia OUI and "Beacon" in product class
        return in_array(strtoupper($this->oui ?? ''), self::NOKIA_OUIS)
            && stripos($this->product_class ?? '', 'Beacon') !== false;
    }

    /**
     * Get or generate the device's password suffix
     * This is generated once per device and stored permanently
     */
    public function getPasswordSuffix(): string
    {
        if (empty($this->password_suffix)) {
            // Generate 8-character alphanumeric suffix
            $this->password_suffix = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 8);
            $this->save();
        }

        return $this->password_suffix;
    }

    /**
     * Get the standard device-specific password
     * Format: {SerialNumber}_{RandomSuffix}_stay$away
     */
    public function getDevicePassword(): string
    {
        return $this->serial_number . '_' . $this->getPasswordSuffix() . '_stay$away';
    }

    /**
     * Get the support password from environment
     */
    public static function getSupportPassword(): string
    {
        return env('BEACON_G6_PASSWORD', 'keepOut-72863!!!');
    }

    /**
     * Get the TR-069 parameter path for the WebAccount/superadmin password
     */
    public function getPasswordParameterPath(): ?string
    {
        if (!$this->isNokiaBeacon()) {
            return null;
        }

        $dataModel = $this->getDataModel();

        if ($dataModel === 'TR-098') {
            return 'InternetGatewayDevice.X_Authentication.WebAccount.Password';
        } else {
            // TR-181: User.2 is superadmin
            return 'Device.Users.User.2.Password';
        }
    }

    /**
     * Check if remote support is currently active (not expired)
     */
    public function isRemoteSupportActive(): bool
    {
        return $this->remote_support_expires_at !== null
            && $this->remote_support_expires_at->isFuture();
    }

    /**
     * Get time remaining for remote support session
     */
    public function getRemoteSupportTimeRemaining(): ?string
    {
        if (!$this->isRemoteSupportActive()) {
            return null;
        }

        return $this->remote_support_expires_at->diffForHumans(['parts' => 2]);
    }

    /**
     * Create a task to set the device password
     * Returns the created task or null if device doesn't support password management
     */
    public function createSetPasswordTask(string $password, string $description = 'Set device password'): ?Task
    {
        $paramPath = $this->getPasswordParameterPath();

        if (!$paramPath) {
            return null;
        }

        return Task::create([
            'device_id' => $this->id,
            'task_type' => 'set_parameter_values',
            'status' => 'pending',
            'description' => $description,
            'parameters' => [
                $paramPath => [
                    'value' => $password,
                    'type' => 'xsd:string',
                ],
            ],
        ]);
    }

    /**
     * Enable remote support - sets password to known support password for 1 hour
     */
    public function enableRemoteSupport(?int $userId = null, int $durationMinutes = 60): ?Task
    {
        if (!$this->isNokiaBeacon()) {
            return null;
        }

        // Create task to set support password
        $task = $this->createSetPasswordTask(
            self::getSupportPassword(),
            'Enable remote support - set known password'
        );

        if ($task) {
            // Update device with expiration time
            $this->update([
                'remote_support_expires_at' => now()->addMinutes($durationMinutes),
                'remote_support_enabled_by' => $userId,
            ]);
        }

        return $task;
    }

    /**
     * Disable remote support - resets password to device-specific password
     */
    public function disableRemoteSupport(): ?Task
    {
        if (!$this->isNokiaBeacon()) {
            return null;
        }

        // Create task to reset to device-specific password
        $task = $this->createSetPasswordTask(
            $this->getDevicePassword(),
            'Disable remote support - reset to device password'
        );

        if ($task) {
            // Clear remote support tracking
            $this->update([
                'remote_support_expires_at' => null,
                'remote_support_enabled_by' => null,
            ]);
        }

        return $task;
    }

    /**
     * Set the initial device-specific password (for provisioning)
     */
    public function setInitialPassword(): ?Task
    {
        if (!$this->isNokiaBeacon()) {
            return null;
        }

        return $this->createSetPasswordTask(
            $this->getDevicePassword(),
            'Initial provisioning - set device-specific password'
        );
    }

    // =========================================================================
    // Manufacturer Detection - Centralized OUI and Manufacturer Checks
    // =========================================================================

    /**
     * Known Calix OUIs - add new OUIs here as devices are discovered
     * This is the SINGLE SOURCE OF TRUTH for Calix device detection
     * Source: IEEE OUI registry + observed devices
     */
    public const CALIX_OUIS = [
        '1C8B76',  // Calix
        'B89470',  // Calix
        '000631',  // Calix (vendor extension prefix X_000631_)
        'E04934',  // Calix
        '4C4341',  // Calix
        'E46CD1',  // Calix
        '142103',  // Calix
        '04BC9F',  // Calix
        '1074C5',  // Calix
        '84D343',  // Calix
        '5CDB36',  // Calix
        '60DB98',  // Calix
        'CCBE59',  // Calix
        'F885F9',  // Calix
        'D0768F',  // Calix (common, 844E/854G)
        '487746',  // Calix
        '44657F',  // Calix
        'EC4F82',  // Calix
        '88DA36',  // Calix
    ];

    /**
     * Known Nokia/Alcatel-Lucent OUIs (Nokia Solutions and Networks)
     * This is the SINGLE SOURCE OF TRUTH for Nokia device detection
     * Source: IEEE OUI registry + observed devices
     * Note: OUI does NOT determine data model (TR-098 vs TR-181) - use getDataModel() for that
     */
    public const NOKIA_OUIS = [
        '80AB4D',  // Nokia - Beacon G6
        'AC8FA9',  // Nokia
        '2874F5',  // Nokia
        'A4FCA1',  // Nokia
        'B8977A',  // Nokia
        '48417B',  // Nokia
        'D0484F',  // Nokia
        '608FA4',  // Nokia
        'DC8D8A',  // Nokia
        'C04121',  // Nokia
        '0C7C28',  // Nokia - Beacon G6
        '207852',  // Nokia
        '54FA96',  // Nokia
        '1455B9',  // Nokia
        'A091CA',  // Nokia
        'A8FB40',  // Nokia
        'F89B6E',  // Nokia
        'E01F2B',  // Nokia
        '6CF712',  // Nokia
        '78F9B4',  // Nokia
        '60A8FE',  // Nokia
        'D8EFCD',  // Nokia
        '40486E',  // Nokia
        'B4636F',  // Nokia
        '34CE69',  // Nokia
        '40E1E4',  // Nokia
        '38A067',  // Nokia
        '0077E4',  // Nokia
        '48EC5B',  // Nokia
        '5807F8',  // Nokia
        'A0C98B',  // Nokia
        '089BB9',  // Nokia
        '24DE8A',  // Nokia
        'C02E1D',  // Nokia
        '980A4B',  // Nokia
        'D0542D',  // Nokia (X_D0542D_ vendor extensions)
    ];

    /**
     * Known SmartRG OUIs (actual SmartRG, not Sagemcom-branded)
     */
    public const SMARTRG_OUIS = [
        // SmartRG devices often report via manufacturer name
    ];

    /**
     * Known Sagemcom OUIs (branded as SmartRG SR505N, SR515ac, etc.)
     */
    public const SAGEMCOM_OUIS = [
        // Sagemcom devices typically identify via manufacturer name
    ];

    /**
     * Check if this device is a Calix device
     */
    public function isCalix(): bool
    {
        return in_array(strtoupper($this->oui ?? ''), self::CALIX_OUIS)
            || strtolower($this->manufacturer ?? '') === 'calix';
    }

    /**
     * Check if this device is a Calix GigaSpire (GS4220E, GS2020E, GM1028, etc.)
     * IMPORTANT: GigaSpires have different TR-069 behavior than GigaCenters
     * GigaSpire code is handled by Claude Instance 1
     */
    public function isGigaSpire(): bool
    {
        if (!$this->isCalix()) {
            return false;
        }
        $productClass = $this->product_class ?? '';
        return stripos($productClass, 'GigaSpire') !== false
            || preg_match('/^GS\d/i', $productClass)
            || preg_match('/^GM\d/i', $productClass);
    }

    /**
     * Check if this device is a Calix GigaCenter (844E, 844G, 854G, 812G, 804Mesh)
     * IMPORTANT: GigaCenters are WORKING - do not modify their code paths
     * GigaCenter code is handled by Claude Instance 2
     */
    public function isGigaCenter(): bool
    {
        if (!$this->isCalix()) {
            return false;
        }
        // GigaCenter if it's Calix but NOT a GigaSpire
        // and matches known GigaCenter model patterns
        if ($this->isGigaSpire()) {
            return false;
        }
        $productClass = $this->product_class ?? '';
        return stripos($productClass, '844') !== false
            || stripos($productClass, '854') !== false
            || stripos($productClass, '812') !== false
            || stripos($productClass, '804') !== false;
    }

    /**
     * Check if this device is a Nokia/Alcatel-Lucent device
     */
    public function isNokia(): bool
    {
        return in_array(strtoupper($this->oui ?? ''), self::NOKIA_OUIS)
            || stripos($this->manufacturer ?? '', 'Nokia') !== false
            || stripos($this->manufacturer ?? '', 'Alcatel') !== false
            || stripos($this->manufacturer ?? '', 'ALCL') !== false;
    }

    /**
     * Check if this device is a SmartRG device (including Sagemcom-branded)
     */
    public function isSmartRG(): bool
    {
        return stripos($this->manufacturer ?? '', 'SmartRG') !== false
            || stripos($this->manufacturer ?? '', 'Sagemcom') !== false
            || stripos($this->product_class ?? '', 'SR5') !== false;
    }

    /**
     * Check if this device is a "one task per session" device
     * These devices only process ONE TR-069 RPC per CWMP session
     */
    public function isOneTaskPerSession(): bool
    {
        return $this->isSmartRG();
    }

    /**
     * Get manufacturer type as a string for display/logging
     */
    public function getManufacturerType(): string
    {
        if ($this->isCalix()) {
            return 'Calix';
        }
        if ($this->isNokia()) {
            return 'Nokia';
        }
        if ($this->isSmartRG()) {
            return 'SmartRG';
        }
        return $this->manufacturer ?? 'Unknown';
    }
}
