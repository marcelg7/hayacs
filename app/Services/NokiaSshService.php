<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSshCredential;
use App\Models\DeviceWifiConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class NokiaSshService
{
    /**
     * Connect to device via SSH and extract WiFi configuration
     */
    public function extractWifiConfig(Device $device): array
    {
        $credentials = $device->sshCredentials;

        if (!$credentials || !$credentials->isComplete()) {
            throw new \Exception('Device does not have complete SSH credentials');
        }

        $ipAddress = $device->ip_address;
        if (!$ipAddress) {
            throw new \Exception('Device has no IP address');
        }

        $sshPassword = $credentials->getSshPassword();
        $shellPassword = $credentials->getShellPassword();

        // Execute SSH command to get wireless config
        $wirelessConfig = $this->executeShellCommand(
            $ipAddress,
            $credentials->ssh_port,
            $credentials->ssh_username,
            $sshPassword,
            $shellPassword,
            'cat /etc/config/wireless'
        );

        if (empty($wirelessConfig)) {
            $credentials->markFailed('Failed to retrieve wireless config');
            throw new \Exception('Failed to retrieve wireless configuration');
        }

        // Mark credentials as verified
        $credentials->markVerified();

        // Parse the UCI config
        $wifiConfigs = $this->parseUciWirelessConfig($wirelessConfig);

        // Store in database
        $savedConfigs = $this->storeWifiConfigs($device, $wifiConfigs, $wirelessConfig);

        Log::info('Extracted WiFi config via SSH', [
            'device_id' => $device->id,
            'networks_found' => count($wifiConfigs),
        ]);

        return $savedConfigs;
    }

    /**
     * Execute a command via SSH with two-layer authentication
     * Layer 1: SSH login to Quagga VTY
     * Layer 2: 'shell' command with Password2 to get BusyBox shell
     */
    protected function executeShellCommand(
        string $host,
        int $port,
        string $username,
        string $sshPassword,
        string $shellPassword,
        string $command
    ): ?string {
        // Build the SSH command with heredoc for two-layer auth
        // Note: username and host should not be escaped with quotes as they're part of user@host format
        $sshCommand = sprintf(
            "sshpass -p %s ssh -o StrictHostKeyChecking=no -o ConnectTimeout=15 -p %d %s@%s << 'SSHEOF'\n" .
            "shell\n" .
            "%s\n" .
            "%s\n" .
            "SSHEOF",
            escapeshellarg($sshPassword),
            $port,
            $username,  // No escaping - username is sanitized by caller
            $host,      // No escaping - IP address format
            $shellPassword,
            $command
        );

        $result = Process::timeout(30)->run($sshCommand);

        if (!$result->successful()) {
            Log::error('SSH command failed', [
                'host' => $host,
                'error' => $result->errorOutput(),
            ]);
            return null;
        }

        $output = $result->output();

        // Clean up the output - remove Quagga VTY prompt and banner lines
        $lines = explode("\n", $output);
        $cleanLines = [];
        $foundPassword2Prompt = false;

        foreach ($lines as $line) {
            // Check for Password2 prompt FIRST (it may appear on same line as Cortina#)
            if (str_contains($line, 'Password2:')) {
                $foundPassword2Prompt = true;
                continue;
            }

            // Skip Quagga prompt and banner lines
            if (str_contains($line, 'Unknown command')) {
                continue;
            }
            if (str_contains($line, 'Hello, this is Quagga')) {
                continue;
            }
            if (str_contains($line, 'Copyright 1996-2005')) {
                continue;
            }
            if (str_contains($line, 'Cortina#')) {
                continue;
            }
            if (str_contains($line, 'Pseudo-terminal')) {
                continue;
            }

            // Once we've seen the Password2 prompt, capture all remaining lines
            // (this is the actual command output from the BusyBox shell)
            if ($foundPassword2Prompt) {
                $cleanLines[] = $line;
            }
        }

        return implode("\n", $cleanLines);
    }

    /**
     * Parse OpenWrt UCI wireless configuration
     */
    protected function parseUciWirelessConfig(string $config): array
    {
        $networks = [];
        $currentBlock = null;
        $currentType = null;

        $lines = explode("\n", $config);

        foreach ($lines as $line) {
            $line = trim($line);

            // New config block - use [\w-]+ to match types like "wifi-iface" and "wifi-device"
            if (preg_match("/^config\s+([\w-]+)(?:\s+'([^']+)')?/", $line, $matches)) {
                if ($currentBlock !== null && $currentType === 'wifi-iface') {
                    $networks[] = $currentBlock;
                }

                $currentType = $matches[1];
                $currentBlock = [
                    'type' => $currentType,
                    'name' => $matches[2] ?? null,
                ];
                continue;
            }

            // Option within block (with quotes)
            if (preg_match("/^option\s+(\w+)\s+'([^']*)'/", $line, $matches) && $currentBlock !== null) {
                $currentBlock[$matches[1]] = $matches[2];
                continue;
            }

            // Option without quotes
            if (preg_match("/^option\s+(\w+)\s+(\S+)/", $line, $matches) && $currentBlock !== null) {
                $currentBlock[$matches[1]] = $matches[2];
            }
        }

        // Don't forget the last block
        if ($currentBlock !== null && $currentType === 'wifi-iface') {
            $networks[] = $currentBlock;
        }

        return $networks;
    }

    /**
     * Store WiFi configs in database
     */
    protected function storeWifiConfigs(Device $device, array $wifiConfigs, string $rawConfig): array
    {
        $savedConfigs = [];
        $dataModel = $device->getDataModel();

        foreach ($wifiConfigs as $config) {
            if ($config['type'] !== 'wifi-iface') {
                continue;
            }

            $interfaceName = $config['name'] ?? $config['ifname'] ?? null;
            if (!$interfaceName) {
                continue;
            }

            // Determine band from radio device
            $radio = $config['device'] ?? '';
            $band = $this->determineBand($radio);

            // Determine network type
            $networkType = $this->classifyNetworkType($config);
            $isBackhaul = $this->isBackhaulNetwork($config);

            // Create or update the WiFi config record
            $wifiConfig = DeviceWifiConfig::updateOrCreate(
                [
                    'device_id' => $device->id,
                    'interface_name' => $interfaceName,
                ],
                [
                    'radio' => $radio,
                    'band' => $band,
                    'ssid' => $config['ssid'] ?? '',
                    'encryption' => $config['encryption'] ?? null,
                    'hidden' => ($config['hidden'] ?? '0') === '1',
                    'enabled' => ($config['disabled'] ?? '0') !== '1',
                    'network_type' => $networkType,
                    'is_mesh_backhaul' => $isBackhaul,
                    'max_clients' => isset($config['maxsta']) ? (int)$config['maxsta'] : null,
                    'client_isolation' => ($config['isolate'] ?? '0') === '1',
                    'wps_enabled' => ($config['wps_pbc'] ?? '0') === '1',
                    'mac_address' => $config['vapmac'] ?? null,
                    'raw_uci_config' => json_encode($config),
                    'extracted_at' => now(),
                    'extraction_method' => 'ssh',
                    'data_model' => $dataModel,
                ]
            );

            // Set the password (encrypted)
            if (!empty($config['key'])) {
                $wifiConfig->setPassword($config['key']);
                $wifiConfig->save();
            }

            $savedConfigs[] = $wifiConfig;
        }

        return $savedConfigs;
    }

    /**
     * Determine band from radio device name
     */
    protected function determineBand(string $radio): string
    {
        // wifi0 = 5GHz, wifi1 = 2.4GHz on Nokia Beacon G6
        if ($radio === 'wifi0') {
            return '5GHz';
        }
        if ($radio === 'wifi1') {
            return '2.4GHz';
        }
        return '5GHz'; // Default
    }

    /**
     * Classify network type based on config
     */
    protected function classifyNetworkType(array $config): string
    {
        $ssid = strtolower($config['ssid'] ?? '');
        $ifname = $config['name'] ?? $config['ifname'] ?? '';

        // Check for guest networks
        if (str_contains($ssid, 'guest')) {
            return 'guest';
        }

        // Check for backhaul (hidden + wds or map setting)
        if ($this->isBackhaulNetwork($config)) {
            return 'backhaul';
        }

        // Primary networks are typically ath0 (5GHz) and ath1 (2.4GHz)
        if (in_array($ifname, ['ath0', 'ath1'])) {
            return 'primary';
        }

        // Secondary networks
        if (in_array($ifname, ['ath01', 'ath11'])) {
            return 'secondary';
        }

        return 'other';
    }

    /**
     * Check if this is a mesh backhaul network
     */
    protected function isBackhaulNetwork(array $config): bool
    {
        // Check MapBSSType - 68 and 72 are backhaul types
        $mapBssType = $config['MapBSSType'] ?? '';
        if (in_array($mapBssType, ['68', '72'])) {
            return true;
        }

        // Hidden + WDS typically indicates backhaul
        $hidden = ($config['hidden'] ?? '0') === '1';
        $wds = ($config['wds'] ?? '0') === '1';
        if ($hidden && $wds) {
            return true;
        }

        // Map setting = 2 indicates backhaul
        if (($config['map'] ?? '') === '2') {
            return true;
        }

        return false;
    }

    /**
     * Test SSH connection to device
     */
    public function testConnection(Device $device): array
    {
        $credentials = $device->sshCredentials;

        if (!$credentials) {
            return [
                'success' => false,
                'error' => 'No SSH credentials configured for this device',
            ];
        }

        $ipAddress = $device->ip_address;
        if (!$ipAddress) {
            return [
                'success' => false,
                'error' => 'Device has no IP address',
            ];
        }

        // Test port connectivity first
        $portCheck = @fsockopen($ipAddress, $credentials->ssh_port, $errno, $errstr, 5);
        if (!$portCheck) {
            $credentials->markFailed("Port {$credentials->ssh_port} not reachable: $errstr");
            return [
                'success' => false,
                'error' => "SSH port not reachable: $errstr",
            ];
        }
        fclose($portCheck);

        // Test SSH login
        $sshPassword = $credentials->getSshPassword();
        $shellPassword = $credentials->getShellPassword();

        $testOutput = $this->executeShellCommand(
            $ipAddress,
            $credentials->ssh_port,
            $credentials->ssh_username,
            $sshPassword,
            $shellPassword ?? '',
            'echo "SSH_TEST_SUCCESS"'
        );

        if ($testOutput && str_contains($testOutput, 'SSH_TEST_SUCCESS')) {
            $credentials->markVerified();
            return [
                'success' => true,
                'message' => 'SSH connection successful',
            ];
        }

        $credentials->markFailed('SSH authentication failed');
        return [
            'success' => false,
            'error' => 'SSH authentication failed',
        ];
    }

    /**
     * Store SSH credentials for a device
     */
    public function storeCredentials(
        Device $device,
        string $sshPassword,
        ?string $shellPassword = null,
        string $username = 'superadmin',
        int $port = 22,
        string $source = 'manual'
    ): DeviceSshCredential {
        // Encrypt passwords before insert (ssh_password_encrypted is NOT NULL)
        $data = [
            'serial_number' => $device->serial_number,
            'ssh_username' => $username,
            'ssh_password_encrypted' => Crypt::encryptString($sshPassword),
            'ssh_port' => $port,
            'credential_source' => $source,
            'imported_at' => now(),
        ];

        if ($shellPassword) {
            $data['shell_password_encrypted'] = Crypt::encryptString($shellPassword);
        }

        return DeviceSshCredential::updateOrCreate(
            ['device_id' => $device->id],
            $data
        );
    }

    /**
     * Get WiFi passwords for support display
     */
    public function getWifiPasswordsForSupport(Device $device): array
    {
        $wifiConfigs = $device->wifiConfigs()
            ->customerFacing()
            ->enabled()
            ->get();

        $passwords = [];
        foreach ($wifiConfigs as $config) {
            $passwords[] = [
                'ssid' => $config->ssid,
                'password' => $config->getPassword(),
                'band' => $config->band,
                'network_type' => $config->network_type,
                'interface' => $config->interface_name,
            ];
        }

        return $passwords;
    }

    /**
     * Import SSH credentials from Nokia spreadsheet data
     */
    public function importCredentialsFromSpreadsheet(array $data): int
    {
        $imported = 0;

        foreach ($data as $row) {
            $serialNumber = $row['serial_number'] ?? $row['SerialNumber'] ?? null;
            if (!$serialNumber) {
                continue;
            }

            // Find device by serial number
            $device = Device::where('serial_number', $serialNumber)->first();
            if (!$device) {
                Log::warning('Device not found for SSH credential import', ['serial' => $serialNumber]);
                continue;
            }

            $sshPassword = $row['factory_password'] ?? $row['FactoryPassword'] ?? $row['ssh_password'] ?? null;
            $shellPassword = $row['password2'] ?? $row['Password2'] ?? $row['shell_password'] ?? null;

            if (!$sshPassword) {
                Log::warning('No SSH password in import data', ['serial' => $serialNumber]);
                continue;
            }

            $this->storeCredentials(
                $device,
                $sshPassword,
                $shellPassword,
                'superadmin',
                22,
                'nokia_spreadsheet'
            );

            $imported++;
        }

        return $imported;
    }
}
