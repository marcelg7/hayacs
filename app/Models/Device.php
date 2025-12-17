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
        'parent_device_id',
        'parent_mac_address',
        'mesh_updated_at',
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
        'mesh_forwarded_url',
        'mesh_forward_port',
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
        'xmpp_jid',
        'xmpp_enabled',
        'xmpp_last_seen',
        'xmpp_status',
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
        'xmpp_enabled' => 'boolean',
        'xmpp_last_seen' => 'datetime',
        'mesh_updated_at' => 'datetime',
    ];

    /**
     * Appended attributes for JSON serialization
     */
    protected $appends = ['display_name'];

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
     * Get the parent device (gateway) for mesh APs
     * Nokia Beacon 2/3.1 APs connect to Beacon G6 gateways
     */
    public function parentDevice()
    {
        return $this->belongsTo(Device::class, 'parent_device_id');
    }

    /**
     * Get child devices (mesh APs) connected to this gateway
     */
    public function childDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'parent_device_id');
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
     *
     * Priority:
     * 1. OUI-based detection for known manufacturers (most reliable)
     * 2. Parameter-based detection (fallback)
     */
    public function getDataModel(): string
    {
        // Known OUI -> Data Model mappings
        // Nokia/Alcatel-Lucent has different OUIs for different data models
        $ouiDataModels = [
            '0C7C28' => 'TR-181',  // Nokia Beacon G6 TR-181
            '80AB4D' => 'TR-098',  // Nokia Beacon G6 TR-098 (older firmware)
        ];

        // Check OUI first for known manufacturers
        $oui = strtoupper($this->oui ?? '');
        if (isset($ouiDataModels[$oui])) {
            return $ouiDataModels[$oui];
        }

        // Fallback to parameter-based detection
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
     * Bulk set/update parameters - optimized for high traffic
     * Uses 2 queries instead of 2N queries (massive reduction)
     *
     * @param array $parameters Array of ['name' => [..., 'value' => ..., 'type' => ...]]
     */
    public function setParametersBulk(array $parameters): void
    {
        if (empty($parameters)) {
            return;
        }

        $now = now();

        // Get ALL existing parameters for this device - uses device_id index efficiently
        // Much faster than whereIn('name', ...) with hundreds of names
        $existing = $this->parameters()
            ->pluck('id', 'name')
            ->toArray();

        $toUpdate = [];
        $toInsert = [];

        foreach ($parameters as $name => $param) {
            $value = $param['value'] ?? '';
            $type = $param['type'] ?? null;

            if (isset($existing[$name])) {
                // Will update existing
                $toUpdate[] = [
                    'id' => $existing[$name],
                    'value' => $value,
                    'type' => $type,
                    'last_updated' => $now,
                ];
            } else {
                // Will insert new
                $toInsert[] = [
                    'device_id' => $this->id,
                    'name' => $name,
                    'value' => $value,
                    'type' => $type,
                    'writable' => false,
                    'last_updated' => $now,
                ];
            }
        }

        // Bulk insert new parameters
        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                Parameter::insert($chunk);
            }
        }

        // Bulk update existing parameters using raw query for efficiency
        if (!empty($toUpdate)) {
            foreach (array_chunk($toUpdate, 500) as $chunk) {
                $ids = array_column($chunk, 'id');
                $cases = [];
                $types = [];
                foreach ($chunk as $row) {
                    $escapedValue = addslashes($row['value']);
                    $escapedType = $row['type'] ? "'" . addslashes($row['type']) . "'" : 'NULL';
                    $cases[] = "WHEN {$row['id']} THEN '{$escapedValue}'";
                    $types[] = "WHEN {$row['id']} THEN {$escapedType}";
                }
                $idList = implode(',', $ids);
                $valueCases = implode(' ', $cases);
                $typeCases = implode(' ', $types);

                \DB::statement("
                    UPDATE parameters
                    SET value = CASE id {$valueCases} END,
                        type = CASE id {$typeCases} END,
                        last_updated = ?
                    WHERE id IN ({$idList})
                ", [$now]);
            }
        }
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
     * Get the TR-069 parameter path for the support/superadmin password
     * Returns the password parameter path based on device type
     */
    public function getPasswordParameterPath(): ?string
    {
        $dataModel = $this->getDataModel();

        // Nokia devices
        if ($this->isNokia()) {
            if ($dataModel === 'TR-098') {
                return 'InternetGatewayDevice.X_Authentication.WebAccount.Password';
            } else {
                // TR-181: User.2 is superadmin
                return 'Device.Users.User.2.Password';
            }
        }

        // Calix devices (GigaSpire, GigaCenter) - User.2 is support account
        if ($this->isCalix()) {
            return 'InternetGatewayDevice.User.2.Password';
        }

        // SmartRG devices don't need password management (use MER network)
        if ($this->isSmartRG()) {
            return null;
        }

        // Generic TR-098 fallback - assume User.2 is support
        return 'InternetGatewayDevice.User.2.Password';
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
     * Disable remote support - resets password to random value and disables remote access
     */
    public function disableRemoteSupport(): ?Task
    {
        // SmartRG devices don't need password management (use MER network access)
        if ($this->isSmartRG()) {
            // Just clear the expiry time
            $this->update([
                'remote_support_expires_at' => null,
                'remote_support_enabled_by' => null,
            ]);
            return null;
        }

        $paramPath = $this->getPasswordParameterPath();
        if (!$paramPath) {
            return null;
        }

        // Generate random password (16 chars alphanumeric + special)
        $randomPassword = bin2hex(random_bytes(8)) . '!' . rand(10, 99);

        // Build parameters to disable remote access and reset password
        $dataModel = $this->getDataModel();
        $disableParams = [];

        if ($this->isNokia() && $dataModel === 'TR-181') {
            $disableParams = [
                'Device.UserInterface.RemoteAccess.Enable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                $paramPath => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        } elseif ($this->isNokia() && $dataModel === 'TR-098') {
            $disableParams = [
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ALU-COM_WanAccessCfg.HttpsDisabled' => [
                    'value' => true,
                    'type' => 'xsd:boolean',
                ],
                $paramPath => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        } else {
            // Calix and generic TR-098 devices
            $disableParams = [
                'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                    'value' => false,
                    'type' => 'xsd:boolean',
                ],
                $paramPath => [
                    'value' => $randomPassword,
                    'type' => 'xsd:string',
                ],
            ];
        }

        // Create task to disable remote access and reset password
        $task = Task::create([
            'device_id' => $this->id,
            'task_type' => 'set_parameter_values',
            'description' => 'Disable remote support - reset password to random',
            'status' => 'pending',
            'parameters' => $disableParams,
        ]);

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

    /**
     * Product class display name mappings
     * Maps device-reported product_class to user-friendly display names
     */
    public const PRODUCT_CLASS_DISPLAY_NAMES = [
        // Calix mappings
        'ENT' => '844E',           // Calix 844E-1 reports as "ENT"
        'ONT' => '854G',           // Calix 854G-1 reports as "ONT"
        'GigaSpire' => 'GigaSpire',
        '804Mesh' => '804Mesh',
        // Add more as discovered...
    ];

    /**
     * Static helper to translate product class to display name
     * Used for filter dropdowns where we don't have a model instance
     */
    public static function getDisplayNameForProductClass(?string $productClass): string
    {
        if (empty($productClass)) {
            return 'Unknown';
        }

        return self::PRODUCT_CLASS_DISPLAY_NAMES[$productClass] ?? $productClass;
    }

    /**
     * Get all product_class values that match a display name search term
     * Used to enable searching by friendly name (e.g., "844E") even when
     * the database stores the device-reported name (e.g., "ENT")
     *
     * @param string $searchTerm The user's search term
     * @return array Array of product_class values that match
     */
    public static function getProductClassesMatchingDisplayName(string $searchTerm): array
    {
        $matches = [];
        $searchLower = strtolower($searchTerm);

        foreach (self::PRODUCT_CLASS_DISPLAY_NAMES as $productClass => $displayName) {
            if (stripos($displayName, $searchTerm) !== false) {
                $matches[] = $productClass;
            }
        }

        return $matches;
    }

    /**
     * Get the display name for the device type
     * Maps cryptic product_class values (like "ENT") to user-friendly names (like "844E")
     */
    public function getDisplayName(): string
    {
        $productClass = $this->product_class ?? '';

        // Check for explicit mapping first
        if (isset(self::PRODUCT_CLASS_DISPLAY_NAMES[$productClass])) {
            return self::PRODUCT_CLASS_DISPLAY_NAMES[$productClass];
        }

        // If model_name is available and not empty, use it
        if (!empty($this->model_name)) {
            return $this->model_name;
        }

        // Fallback to product_class
        return $productClass ?: 'Unknown';
    }

    /**
     * Accessor for display_name attribute
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->getDisplayName();
    }

    // =========================================================================
    // Mesh Network Detection (Calix 804Mesh, GigaMesh, Nokia Beacon 2/3/3.1/24)
    // =========================================================================

    /**
     * Check if this device is a mesh access point
     * Calix: 804Mesh, GigaMesh, u4m
     * Nokia: Beacon 2, Beacon 3, Beacon 3.1, Beacon 24 (NOT Beacon G6 which is a gateway)
     */
    public function isMeshDevice(): bool
    {
        $productClass = strtolower($this->product_class ?? '');

        // Calix mesh APs
        if ($this->isCalix()) {
            return stripos($productClass, '804mesh') !== false
                || stripos($productClass, 'gigamesh') !== false
                || stripos($productClass, 'u4m') !== false;
        }

        // Nokia mesh APs (Beacon 2, Beacon 3, Beacon 3.1 - but NOT Beacon G6 or Beacon 24 which are gateways)
        if ($this->isNokia()) {
            // Check for Beacon but exclude gateways (Beacon G6, Beacon 24)
            if (stripos($productClass, 'beacon') !== false
                && stripos($productClass, 'beacon g6') === false
                && stripos($productClass, 'beacon 24') === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this device is a mesh satellite (connected to a gateway)
     * Calix: Has GatewayInfo.SerialNumber parameter
     * Nokia: Has WorkRole = Agent
     */
    public function isMeshSatellite(): bool
    {
        if (!$this->isMeshDevice()) {
            return false;
        }

        // Calix mesh APs have GatewayInfo.SerialNumber
        if ($this->isCalix()) {
            $gatewaySerial = $this->getParameter('InternetGatewayDevice.X_000631_Device.GatewayInfo.SerialNumber');
            return !empty($gatewaySerial);
        }

        // Nokia mesh APs have WorkRole = Agent (Controller is the gateway)
        if ($this->isNokia()) {
            $workRole = $this->getParameter('InternetGatewayDevice.X_ALU-COM_Wifi.WorkRole');
            return strtolower($workRole ?? '') === 'agent';
        }

        return false;
    }

    /**
     * Get the gateway serial number for a mesh satellite
     * Note: Nokia Beacons don't have a direct gateway serial reference
     */
    public function getMeshGatewaySerial(): ?string
    {
        if (!$this->isMeshDevice()) {
            return null;
        }

        // Calix has direct gateway serial reference
        if ($this->isCalix()) {
            return $this->getParameter('InternetGatewayDevice.X_000631_Device.GatewayInfo.SerialNumber');
        }

        // Nokia doesn't have a direct serial reference - would need IP/subnet matching
        return null;
    }

    /**
     * Get the gateway Device model for a mesh satellite
     * Returns null if not a satellite or gateway not found
     */
    public function getMeshGateway(): ?Device
    {
        $gatewaySerial = $this->getMeshGatewaySerial();
        if (!$gatewaySerial) {
            return null;
        }
        return Device::where('serial_number', $gatewaySerial)->first();
    }

    /**
     * Get the backhaul type for a mesh device
     * Returns "Ethernet", "WiFi", or null
     */
    public function getMeshBackhaulType(): ?string
    {
        if (!$this->isMeshDevice()) {
            return null;
        }
        $backhaul = $this->getParameter('InternetGatewayDevice.X_000631_Device.ExosMesh.WapBackhaul');
        if (!$backhaul) {
            return null;
        }
        // Normalize to consistent casing
        if (stripos($backhaul, 'wifi') !== false || stripos($backhaul, 'wireless') !== false) {
            return 'WiFi';
        }
        if (stripos($backhaul, 'ethernet') !== false || stripos($backhaul, 'wired') !== false) {
            return 'Ethernet';
        }
        return $backhaul;
    }

    /**
     * Get the WiFi backhaul signal strength in dBm
     * Only relevant for WiFi backhaul devices
     */
    public function getMeshSignalStrength(): ?int
    {
        if (!$this->isMeshDevice()) {
            return null;
        }
        $signal = $this->getParameter('InternetGatewayDevice.X_000631_Device.ExosMesh.Stats.SignalStrength');
        return $signal !== null ? (int) $signal : null;
    }

    /**
     * Get the mesh operational role (Controller or Satellite)
     */
    public function getMeshRole(): ?string
    {
        if (!$this->isMeshDevice()) {
            return null;
        }
        return $this->getParameter('InternetGatewayDevice.X_000631_Device.ExosMesh.OperationalRole');
    }

    /**
     * Get all satellite devices connected to this gateway
     * Returns a collection of Device models
     */
    public function getMeshSatellites()
    {
        if (!$this->serial_number) {
            return collect();
        }

        // Find all devices that have this device's serial number as their gateway (Calix)
        $satelliteIds = Parameter::where('name', 'InternetGatewayDevice.X_000631_Device.GatewayInfo.SerialNumber')
            ->where('value', $this->serial_number)
            ->pluck('device_id');

        return Device::whereIn('id', $satelliteIds)->get();
    }

    /**
     * Get Nokia Beacon satellites connected to this gateway
     * Nokia satellites don't connect to ACS directly - their info is embedded in the gateway's parameters
     * Returns array of satellite info objects
     */
    public function getNokiaBeaconSatellites(): array
    {
        // Only applies to Nokia devices (OUI 80AB4D or 0C7C28)
        $oui = strtoupper($this->oui ?? '');
        if (!in_array($oui, ['80AB4D', '0C7C28'])) {
            return [];
        }

        $satellites = [];

        // Find hosts with X_ALU-COM_IsBeacon = 1 (satellite beacons, not the gateway itself)
        $beaconParams = $this->parameters()
            ->where('name', 'like', '%Hosts.Host.%.X_ALU-COM_IsBeacon')
            ->where('value', '1')
            ->get();

        foreach ($beaconParams as $param) {
            // Extract host number from parameter name
            if (!preg_match('/Host\.(\d+)\.X_ALU-COM_IsBeacon/', $param->name, $matches)) {
                continue;
            }
            $hostNum = $matches[1];
            $prefix = 'InternetGatewayDevice.LANDevice.1.Hosts.Host.' . $hostNum;

            // Get host details
            $hostParams = $this->parameters()
                ->whereIn('name', [
                    $prefix . '.HostName',
                    $prefix . '.MACAddress',
                    $prefix . '.IPAddress',
                    $prefix . '.Active',
                ])
                ->get()
                ->keyBy('name');

            $mac = $hostParams->get($prefix . '.MACAddress')?->value ?? '';
            $name = $hostParams->get($prefix . '.HostName')?->value ?? 'Unknown Beacon';
            $ip = $hostParams->get($prefix . '.IPAddress')?->value ?? '';
            $active = $hostParams->get($prefix . '.Active')?->value ?? '0';

            // Try to find matching DataElements.Network.Device.{n} for more details
            $deviceInfo = $this->findNokiaDeviceByMac($mac);

            $satellites[] = [
                'host_num' => $hostNum,
                'name' => $name,
                'mac' => strtoupper($mac),
                'ip' => $ip,
                'active' => $active === 'true' || $active === '1',
                'model' => $deviceInfo['model'] ?? $this->detectBeaconModel($name),
                'backhaul' => $deviceInfo['backhaul'] ?? 'Unknown',
                'signal_strength' => $deviceInfo['signal_strength'] ?? null,
                'last_contact' => $deviceInfo['last_contact'] ?? null,
            ];
        }

        return $satellites;
    }

    /**
     * Find Nokia DataElements.Network.Device.{n} by MAC address
     */
    private function findNokiaDeviceByMac(string $mac): ?array
    {
        if (empty($mac)) {
            return null;
        }

        $macUpper = strtoupper($mac);

        // Find which Device.{n} has this MAC as its ID
        $deviceIdParam = $this->parameters()
            ->where('name', 'like', 'InternetGatewayDevice.DataElements.Network.Device.%.ID')
            ->whereRaw('UPPER(value) = ?', [$macUpper])
            ->first();

        if (!$deviceIdParam) {
            return null;
        }

        // Extract device number
        if (!preg_match('/Device\.(\d+)\.ID/', $deviceIdParam->name, $matches)) {
            return null;
        }
        $deviceNum = $matches[1];
        $prefix = 'InternetGatewayDevice.DataElements.Network.Device.' . $deviceNum;

        // Get device details
        $params = $this->parameters()
            ->where('name', 'like', $prefix . '.MultiAPDevice.Backhaul.%')
            ->orWhere('name', 'like', $prefix . '.MultiAPDevice.LastContactTime')
            ->get()
            ->keyBy('name');

        $backhaulType = $params->get($prefix . '.MultiAPDevice.Backhaul.LinkType')?->value ?? '';
        $signal = $params->get($prefix . '.MultiAPDevice.Backhaul.Stats.SignalStrength')?->value ?? null;
        $lastContact = $params->get($prefix . '.MultiAPDevice.LastContactTime')?->value ?? null;

        return [
            'device_num' => $deviceNum,
            'backhaul' => $backhaulType ?: 'Unknown',
            'signal_strength' => $signal && $signal != '0' ? (int)$signal : null,
            'last_contact' => $lastContact && $lastContact !== '0001-01-01T00:00:00Z' ? $lastContact : null,
            'model' => null, // Nokia doesn't expose model via DataElements
        ];
    }

    /**
     * Detect Beacon model from hostname
     */
    private function detectBeaconModel(string $name): string
    {
        $nameLower = strtolower($name);
        if (str_contains($nameLower, 'beacon 3')) {
            if (str_contains($nameLower, '3.1') || str_contains($nameLower, '3_1')) {
                return 'Beacon 3.1';
            }
            return 'Beacon 3';
        }
        if (str_contains($nameLower, 'beacon 2') || str_contains($nameLower, 'beacon2')) {
            return 'Beacon 2';
        }
        if (str_contains($nameLower, 'beacon')) {
            return 'Beacon';
        }
        return 'Mesh AP';
    }

    /**
     * Get mesh info as an array for API/display
     */
    public function getMeshInfo(): array
    {
        if (!$this->isMeshDevice()) {
            return ['is_mesh' => false];
        }

        $gateway = $this->getMeshGateway();
        $backhaul = $this->getMeshBackhaulType();
        $signal = $this->getMeshSignalStrength();

        return [
            'is_mesh' => true,
            'is_satellite' => $this->isMeshSatellite(),
            'role' => $this->getMeshRole(),
            'gateway_serial' => $this->getMeshGatewaySerial(),
            'gateway_id' => $gateway?->id,
            'gateway_display' => $gateway ? ($gateway->subscriber?->name ?? $gateway->serial_number) : null,
            'backhaul' => $backhaul,
            'signal_strength' => $signal,
            'signal_quality' => $signal !== null ? $this->getSignalQuality($signal) : null,
        ];
    }

    /**
     * Convert signal strength dBm to quality descriptor
     */
    private function getSignalQuality(int $dBm): string
    {
        if ($dBm >= -50) return 'Excellent';
        if ($dBm >= -60) return 'Good';
        if ($dBm >= -70) return 'Fair';
        if ($dBm >= -80) return 'Poor';
        return 'Very Poor';
    }

    // =========================================================================
    // Mesh Port Forward Management
    // =========================================================================

    /**
     * Base port for mesh AP port forwards (external ports start from here)
     */
    public const MESH_PORT_BASE = 20000;

    /**
     * Check if this mesh AP needs a port forward setup
     */
    public function needsMeshPortForward(): bool
    {
        if (!$this->isMeshDevice()) {
            return false;
        }

        // Already has a forwarded URL configured
        if (!empty($this->mesh_forwarded_url)) {
            return false;
        }

        // Check if the connection request URL is a private IP
        $crUrl = $this->connection_request_url;
        if (empty($crUrl)) {
            return false;
        }

        // Extract IP from URL
        if (preg_match('/https?:\/\/([0-9.]+)/', $crUrl, $matches)) {
            $ip = $matches[1];
            return $this->isPrivateIp($ip);
        }

        return false;
    }

    /**
     * Check if an IP address is private (RFC 1918)
     */
    private function isPrivateIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        // 10.0.0.0/8
        if (($long & 0xFF000000) === 0x0A000000) return true;
        // 172.16.0.0/12
        if (($long & 0xFFF00000) === 0xAC100000) return true;
        // 192.168.0.0/16
        if (($long & 0xFFFF0000) === 0xC0A80000) return true;

        return false;
    }

    /**
     * Get the internal IP of this mesh AP (from connection request URL)
     */
    public function getMeshInternalIp(): ?string
    {
        $crUrl = $this->connection_request_url;
        if (empty($crUrl)) {
            return null;
        }

        if (preg_match('/https?:\/\/([0-9.]+)/', $crUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Calculate a unique external port for this mesh AP
     * Uses the last two octets of the internal IP to generate a unique port
     */
    public function calculateMeshForwardPort(): int
    {
        // If already assigned, return that
        if ($this->mesh_forward_port) {
            return $this->mesh_forward_port;
        }

        $internalIp = $this->getMeshInternalIp();
        if ($internalIp) {
            $octets = explode('.', $internalIp);
            if (count($octets) === 4) {
                // Use last two octets to create unique port: 20000 + (3rd octet * 256) + 4th octet
                // This gives range 20000-85535, well within valid port range
                $port = self::MESH_PORT_BASE + ((int)$octets[2] * 256) + (int)$octets[3];
                // Cap at 65535 max
                return min($port, 65535);
            }
        }

        // Fallback: use a hash of the device ID
        return self::MESH_PORT_BASE + (crc32($this->id) % 10000);
    }

    /**
     * Get the WAN connection path for the gateway (for port mapping)
     */
    public function getWanConnectionPath(): ?string
    {
        // Find WANIPConnection with an external IP
        $wanParam = $this->parameters()
            ->where('name', 'LIKE', 'InternetGatewayDevice.WANDevice.%.WANConnectionDevice.%.WANIPConnection.%.ExternalIPAddress')
            ->where('value', '!=', '')
            ->where('value', 'NOT LIKE', '192.168.%')
            ->where('value', 'NOT LIKE', '10.%')
            ->where('value', 'NOT LIKE', '172.%')
            ->first();

        if ($wanParam && preg_match('/(InternetGatewayDevice\.WANDevice\.\d+\.WANConnectionDevice\.\d+\.WANIPConnection\.\d+)\./', $wanParam->name, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Create tasks to set up port forwarding for a mesh AP on its gateway
     * Returns array of created tasks or null if not applicable
     */
    public function setupMeshPortForward(): ?array
    {
        if (!$this->isMeshDevice()) {
            return null;
        }

        $gateway = $this->getMeshGateway();
        if (!$gateway) {
            return null;
        }

        $internalIp = $this->getMeshInternalIp();
        if (!$internalIp) {
            return null;
        }

        // Get gateway's WAN path for port mapping
        $wanPath = $gateway->getWanConnectionPath();
        if (!$wanPath) {
            return null;
        }

        $externalPort = $this->calculateMeshForwardPort();

        // Step 1: Create AddObject task to add new PortMapping entry
        $addTask = Task::create([
            'device_id' => $gateway->id,
            'task_type' => 'add_object',
            'status' => 'pending',
            'description' => "Add port forward for mesh AP {$this->serial_number}",
            'parameters' => [
                'object_name' => "{$wanPath}.PortMapping.",
            ],
        ]);

        // Store the port we're planning to use
        $this->update(['mesh_forward_port' => $externalPort]);

        // The SetParameterValues will be created after AddObject completes
        // Store the mesh AP ID in the task metadata for the follow-up
        $addTask->update([
            'result' => [
                'mesh_ap_id' => $this->id,
                'internal_ip' => $internalIp,
                'internal_port' => 30005,
                'external_port' => $externalPort,
                'wan_path' => $wanPath,
            ],
        ]);

        return ['add_object_task' => $addTask];
    }

    /**
     * Update the mesh forwarded URL after port forward is set up
     */
    public function updateMeshForwardedUrl(): void
    {
        $gateway = $this->getMeshGateway();
        if (!$gateway || !$this->mesh_forward_port) {
            return;
        }

        $gatewayIp = $gateway->ip_address;
        if (empty($gatewayIp) || $this->isPrivateIp($gatewayIp)) {
            // Try to get gateway's external IP from parameters
            $wanIp = $gateway->parameters()
                ->where('name', 'LIKE', '%WANIPConnection%.ExternalIPAddress')
                ->where('value', '!=', '')
                ->where('value', 'NOT LIKE', '192.168.%')
                ->where('value', 'NOT LIKE', '10.%')
                ->where('value', 'NOT LIKE', '172.%')
                ->first();

            if ($wanIp) {
                $gatewayIp = $wanIp->value;
            }
        }

        if (!empty($gatewayIp) && !$this->isPrivateIp($gatewayIp)) {
            // Construct the forwarded URL - use same path as original connection request
            $path = '/CWMP/ConnectionRequest';
            if (preg_match('/https?:\/\/[^\/]+(.*)/', $this->connection_request_url, $matches)) {
                $path = $matches[1] ?: '/';
            }

            $forwardedUrl = "http://{$gatewayIp}:{$this->mesh_forward_port}{$path}";
            $this->update(['mesh_forwarded_url' => $forwardedUrl]);
        }
    }

    /**
     * Get the effective connection request URL for this device
     * For mesh APs, returns the port-forwarded URL if available
     */
    public function getEffectiveConnectionRequestUrl(): ?string
    {
        // For mesh APs with port forward configured, use that
        if ($this->isMeshDevice() && !empty($this->mesh_forwarded_url)) {
            return $this->mesh_forwarded_url;
        }

        // For devices with UDP/STUN address, that takes priority
        if (!empty($this->udp_connection_request_address)) {
            return null; // Signal to use UDP instead
        }

        return $this->connection_request_url;
    }

    /**
     * Check if this device can receive connection requests
     */
    public function canReceiveConnectionRequest(): bool
    {
        // Has STUN/UDP address
        if (!empty($this->udp_connection_request_address)) {
            return true;
        }

        // Mesh AP with port forward
        if ($this->isMeshDevice() && !empty($this->mesh_forwarded_url)) {
            return true;
        }

        // Has a public connection request URL
        if (!empty($this->connection_request_url)) {
            if (preg_match('/https?:\/\/([0-9.]+)/', $this->connection_request_url, $matches)) {
                return !$this->isPrivateIp($matches[1]);
            }
        }

        return false;
    }

    // =========================================================================
    // XMPP Connection Request Support
    // =========================================================================

    /**
     * Check if this device has XMPP enabled
     */
    public function isXmppEnabled(): bool
    {
        return $this->xmpp_enabled && !empty($this->xmpp_jid);
    }

    /**
     * Get the device's XMPP JabberID
     */
    public function getXmppJid(): ?string
    {
        return $this->xmpp_jid;
    }

    /**
     * Check if this device supports XMPP connection requests
     * Based on SupportedConnReqMethods parameter
     */
    public function supportsXmpp(): bool
    {
        $supported = $this->getParameter('InternetGatewayDevice.ManagementServer.SupportedConnReqMethods')
            ?? $this->getParameter('Device.ManagementServer.SupportedConnReqMethods');

        if ($supported && stripos($supported, 'XMPP') !== false) {
            return true;
        }

        // Also check if device has XMPP connection parameters
        return $this->parameters()
            ->where('name', 'LIKE', '%XMPP.Connection.%')
            ->exists();
    }

    /**
     * Get current XMPP connection info from device parameters
     */
    public function getXmppConnectionInfo(): array
    {
        $info = [
            'supported' => $this->supportsXmpp(),
            'enabled' => false,
            'jid' => null,
            'domain' => null,
            'port' => null,
            'use_tls' => null,
            'status' => null,
        ];

        // Check various XMPP parameters
        $params = $this->parameters()
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%XMPP.Connection.%')
                    ->orWhere('name', 'LIKE', '%ManagementServer.X_ALU%XMPP%');
            })
            ->get();

        foreach ($params as $param) {
            $name = strtolower($param->name);

            if (str_contains($name, '.enable')) {
                $info['enabled'] = in_array(strtolower($param->value), ['true', '1']);
            }
            if (str_contains($name, 'jabberid')) {
                $info['jid'] = $param->value;
            }
            if (str_contains($name, '.domain')) {
                $info['domain'] = $param->value;
            }
            if (str_contains($name, 'serverport') || str_contains($name, '.port')) {
                $info['port'] = $param->value;
            }
            if (str_contains($name, 'usetls')) {
                $info['use_tls'] = in_array(strtolower($param->value), ['true', '1']);
            }
            if (str_contains($name, 'status')) {
                $info['status'] = $param->value;
            }
        }

        return $info;
    }

    /**
     * Update XMPP status from device parameters
     * Called after parsing Inform to keep local DB in sync
     */
    public function syncXmppStatus(): void
    {
        $info = $this->getXmppConnectionInfo();

        $updates = [];

        if ($info['jid']) {
            $updates['xmpp_jid'] = $info['jid'];
        }

        if ($info['enabled']) {
            $updates['xmpp_enabled'] = true;
            $updates['xmpp_last_seen'] = now();
        }

        if ($info['status']) {
            $updates['xmpp_status'] = $info['status'];
        }

        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Get TR-069 parameters to enable XMPP on this device
     * Returns array suitable for SetParameterValues task
     */
    public function getXmppEnableParameters(string $domain, string $username, string $password, int $port = 5222): array
    {
        $dataModel = $this->getDataModel();
        $prefix = $dataModel === 'TR-181' ? 'Device.' : 'InternetGatewayDevice.';

        // Nokia-specific parameters
        if ($this->isNokia()) {
            return [
                "{$prefix}ManagementServer.X_ALU_COM_XMPP_Enable" => [
                    'value' => 'true',
                    'type' => 'xsd:boolean',
                ],
                "{$prefix}XMPP.Connection.1.Enable" => [
                    'value' => 'true',
                    'type' => 'xsd:boolean',
                ],
                "{$prefix}XMPP.Connection.1.Domain" => [
                    'value' => $domain,
                    'type' => 'xsd:string',
                ],
                "{$prefix}XMPP.Connection.1.Username" => [
                    'value' => $username,
                    'type' => 'xsd:string',
                ],
                "{$prefix}XMPP.Connection.1.Password" => [
                    'value' => $password,
                    'type' => 'xsd:string',
                ],
                "{$prefix}XMPP.Connection.1.UseTLS" => [
                    'value' => 'true',
                    'type' => 'xsd:boolean',
                ],
                "{$prefix}XMPP.Connection.1.X_ALU_COM_XMPP_Port" => [
                    'value' => (string)$port,
                    'type' => 'xsd:unsignedInt',
                ],
            ];
        }

        // Generic TR-069 XMPP parameters (TR-181)
        return [
            "{$prefix}XMPP.Connection.1.Enable" => [
                'value' => 'true',
                'type' => 'xsd:boolean',
            ],
            "{$prefix}XMPP.Connection.1.Domain" => [
                'value' => $domain,
                'type' => 'xsd:string',
            ],
            "{$prefix}XMPP.Connection.1.Username" => [
                'value' => $username,
                'type' => 'xsd:string',
            ],
            "{$prefix}XMPP.Connection.1.Password" => [
                'value' => $password,
                'type' => 'xsd:string',
            ],
            "{$prefix}XMPP.Connection.1.UseTLS" => [
                'value' => 'true',
                'type' => 'xsd:boolean',
            ],
        ];
    }

    // =========================================================================
    // Mesh Topology from EasyMesh/MultiAP DataElements
    // =========================================================================

    /**
     * Check if this device is a Nokia mesh AP (Beacon 2, 3.1 - not G6)
     */
    public function isNokiaMeshAP(): bool
    {
        if (!$this->isNokia()) {
            return false;
        }
        $productClass = strtolower($this->product_class ?? '');
        // Beacon 2, Beacon 3, Beacon 3.1, etc. but NOT Beacon G6 or Beacon 24
        return stripos($productClass, 'beacon') !== false
            && stripos($productClass, 'g6') === false
            && stripos($productClass, '24') === false;
    }

    /**
     * Check if this device is a Nokia gateway (Beacon G6)
     */
    public function isNokiaGateway(): bool
    {
        if (!$this->isNokia()) {
            return false;
        }
        $productClass = strtolower($this->product_class ?? '');
        return stripos($productClass, 'beacon g6') !== false
            || stripos($productClass, 'beacon 24') !== false;
    }

    /**
     * Update mesh parent relationship from DataElements parameters
     * Called during Inform processing to track mesh topology
     *
     * Nokia mesh APs report their parent gateway in:
     * InternetGatewayDevice.DataElements.Network.Device.1.SerialNumber (TR-098)
     * Device.WiFi.DataElements.Network.Device.1.SerialNumber (TR-181)
     *
     * @return bool True if mesh parent was updated
     */
    public function updateMeshParentFromDataElements(): bool
    {
        // Only process mesh APs (Beacon 2, 3.1)
        if (!$this->isNokiaMeshAP()) {
            return false;
        }

        $dataModel = $this->getDataModel();
        $prefix = $dataModel === 'TR-181' ? 'Device.WiFi.' : 'InternetGatewayDevice.';

        // Get the gateway's serial number and MAC from DataElements.Network.Device.1
        $parentSerial = $this->getParameter("{$prefix}DataElements.Network.Device.1.SerialNumber");
        $parentMac = $this->getParameter("{$prefix}DataElements.Network.Device.1.ID");
        $parentModel = $this->getParameter("{$prefix}DataElements.Network.Device.1.ManufacturerModel");

        // If no parent info found, nothing to do
        if (empty($parentSerial) && empty($parentMac)) {
            return false;
        }

        // Don't update if it's reporting itself as the gateway (standalone mode)
        if ($parentSerial === $this->serial_number) {
            return false;
        }

        $updates = [
            'mesh_updated_at' => now(),
        ];

        // Store parent MAC for matching
        if ($parentMac) {
            // Normalize MAC format (uppercase, colons)
            $updates['parent_mac_address'] = strtoupper(str_replace(['-', '.'], ':', $parentMac));
        }

        // Try to find the parent device by serial number
        $parentDevice = null;
        if ($parentSerial) {
            $parentDevice = Device::where('serial_number', $parentSerial)->first();
        }

        // If not found by serial, try by MAC address
        if (!$parentDevice && $parentMac) {
            // Nokia devices store MAC in various formats, try to match
            $normalizedMac = strtoupper(str_replace(['-', '.', ':'], '', $parentMac));
            $parentDevice = Device::where(function ($q) use ($normalizedMac, $parentMac) {
                // Check against stored parent_mac_address of other devices
                $q->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(parent_mac_address), '-', ''), '.', ''), ':', '') = ?", [$normalizedMac])
                    // Or check against parameters that might contain the MAC
                    ->orWhereHas('parameters', function ($pq) use ($parentMac) {
                        $pq->where('name', 'LIKE', '%WiFi.DataElements.Network.Device.1.ID')
                            ->where('value', $parentMac);
                    });
            })->first();
        }

        if ($parentDevice) {
            $updates['parent_device_id'] = $parentDevice->id;
        }

        // Only update if something changed
        $changed = false;
        foreach ($updates as $key => $value) {
            if ($this->$key !== $value) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $this->update($updates);
            return true;
        }

        return false;
    }

    /**
     * Get mesh topology info for this device
     * Returns parent and children information
     */
    public function getMeshTopology(): array
    {
        $result = [
            'is_gateway' => $this->isNokiaGateway(),
            'is_mesh_ap' => $this->isNokiaMeshAP(),
            'parent' => null,
            'children' => [],
            'mesh_updated_at' => $this->mesh_updated_at?->toIso8601String(),
        ];

        // If this is a mesh AP, get parent info
        if ($this->isNokiaMeshAP() && $this->parent_device_id) {
            $parent = $this->parentDevice;
            if ($parent) {
                $result['parent'] = [
                    'id' => $parent->id,
                    'serial_number' => $parent->serial_number,
                    'product_class' => $parent->product_class,
                    'display_name' => $parent->getDisplayName(),
                    'subscriber' => $parent->subscriber?->name,
                    'online' => $parent->online,
                ];
            }
        }

        // If this is a gateway, get connected mesh APs
        if ($this->isNokiaGateway()) {
            $children = $this->childDevices()->with('subscriber')->get();
            foreach ($children as $child) {
                $result['children'][] = [
                    'id' => $child->id,
                    'serial_number' => $child->serial_number,
                    'product_class' => $child->product_class,
                    'display_name' => $child->getDisplayName(),
                    'online' => $child->online,
                    'last_inform' => $child->last_inform?->toIso8601String(),
                ];
            }
        }

        return $result;
    }

    /**
     * Count connected mesh APs (for gateways)
     */
    public function getConnectedMeshAPCount(): int
    {
        if (!$this->isNokiaGateway()) {
            return 0;
        }
        return $this->childDevices()->count();
    }

    /**
     * Count online connected mesh APs (for gateways)
     */
    public function getOnlineMeshAPCount(): int
    {
        if (!$this->isNokiaGateway()) {
            return 0;
        }
        // Need to check each child's online status since it's computed
        return $this->childDevices()
            ->get()
            ->filter(fn($d) => $d->online)
            ->count();
    }
}
