<?php
/**
 * Manual WiFi Restore Script for TR-098 to TR-181 Migration
 *
 * This script takes WiFi parameters from a TR-098 backup and converts them to TR-181
 * format for restoration on a migrated Nokia Beacon G6 device.
 *
 * Usage: php scripts/wifi-restore.php <new_device_id> <old_device_id>
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;
use App\Models\ConfigBackup;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

$newDeviceId = $argv[1] ?? null;
$oldDeviceId = $argv[2] ?? null;

if (!$newDeviceId || !$oldDeviceId) {
    echo "Usage: php scripts/wifi-restore.php <new_device_id> <old_device_id>\n";
    echo "Example: php scripts/wifi-restore.php '0C7C28-Beacon G6-ALCLFD0A7959' '80AB4D-Beacon G6-ALCLFD0A7959'\n";
    exit(1);
}

// Find the new TR-181 device
$newDevice = Device::find($newDeviceId);
if (!$newDevice) {
    echo "ERROR: New TR-181 device not found: {$newDeviceId}\n";
    exit(1);
}

// Find the old TR-098 device
$oldDevice = Device::find($oldDeviceId);
if (!$oldDevice) {
    echo "ERROR: Old TR-098 device not found: {$oldDeviceId}\n";
    exit(1);
}

echo "=== Devices Found ===\n";
echo "New device (TR-181): {$newDevice->id}\n";
echo "  Serial: {$newDevice->serial_number}\n";
echo "  Data Model: {$newDevice->getDataModel()}\n";
echo "Old device (TR-098): {$oldDevice->id}\n";
echo "  Serial: {$oldDevice->serial_number}\n";
echo "  Data Model: {$oldDevice->getDataModel()}\n";

// Find the transition backup on old device
$backup = ConfigBackup::where('device_id', $oldDevice->id)
    ->where(function($q) {
        $q->where('name', 'like', '%Transition%')
          ->orWhere('description', 'like', '%corteca_transition%')
          ->orWhere('description', 'like', '%Corteca%');
    })
    ->latest()
    ->first();

if (!$backup) {
    echo "ERROR: No transition backup found for old device\n";
    exit(1);
}

echo "\n=== Backup Found ===\n";
echo "Backup ID: {$backup->id}\n";
echo "Name: {$backup->name}\n";
echo "Parameter count: {$backup->parameter_count}\n";

// Get backup data - check both backup_data and parameters columns
$backupData = null;
if ($backup->backup_data) {
    $backupData = is_array($backup->backup_data) ? $backup->backup_data : json_decode($backup->backup_data, true);
}
if (!$backupData && $backup->parameters) {
    $backupData = is_array($backup->parameters) ? $backup->parameters : json_decode($backup->parameters, true);
}
if (!$backupData) {
    echo "ERROR: Cannot parse backup data\n";
    echo "backup_data type: " . gettype($backup->backup_data) . "\n";
    echo "parameters type: " . gettype($backup->parameters) . "\n";
    exit(1);
}

echo "Backup data entries: " . count($backupData) . "\n";

// TR-098 to TR-181 WiFi Parameter Mapping for Nokia Beacon G6
// Note: WiFi instance numbers stay the same between data models
$wifiMapping = [
    // 2.4GHz Primary (Instance 1)
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => 'Device.WiFi.SSID.1.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable' => 'Device.WiFi.SSID.1.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.1.SSIDAdvertisementEnabled',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',

    // 2.4GHz Guest (Instance 2)
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID' => 'Device.WiFi.SSID.2.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.Enable' => 'Device.WiFi.SSID.2.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.2.SSIDAdvertisementEnabled',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.KeyPassphrase' => 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase',

    // 5GHz Primary (Instance 5)
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID' => 'Device.WiFi.SSID.5.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable' => 'Device.WiFi.SSID.5.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.5.SSIDAdvertisementEnabled',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.5.Security.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.KeyPassphrase' => 'Device.WiFi.AccessPoint.5.Security.KeyPassphrase',

    // 5GHz Guest (Instance 6)
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSID' => 'Device.WiFi.SSID.6.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.Enable' => 'Device.WiFi.SSID.6.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.6.SSIDAdvertisementEnabled',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.6.Security.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.6.KeyPassphrase' => 'Device.WiFi.AccessPoint.6.Security.KeyPassphrase',

    // 6GHz Primary (Instance 9) - if present
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.SSID' => 'Device.WiFi.SSID.9.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.Enable' => 'Device.WiFi.SSID.9.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.SSIDAdvertisementEnabled' => 'Device.WiFi.AccessPoint.9.SSIDAdvertisementEnabled',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.PreSharedKey.1.KeyPassphrase' => 'Device.WiFi.AccessPoint.9.Security.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.KeyPassphrase' => 'Device.WiFi.AccessPoint.9.Security.KeyPassphrase',
];

echo "\n=== WiFi Parameters from TR-098 Backup ===\n";

$tr181Params = [];
$skippedPasswords = 0;
foreach ($backupData as $name => $param) {
    $value = is_array($param) ? ($param['value'] ?? '') : $param;
    $type = is_array($param) ? ($param['type'] ?? 'xsd:string') : 'xsd:string';

    // Check if this is a WiFi parameter we can map
    if (isset($wifiMapping[$name])) {
        $tr181Name = $wifiMapping[$name];

        // Skip empty password fields (TR-069 returns masked/empty values)
        if (preg_match('/KeyPassphrase|PreSharedKey/', $tr181Name) && empty($value)) {
            $skippedPasswords++;
            continue;
        }

        $tr181Params[$tr181Name] = [
            'value' => $value,
            'type' => $type,
        ];

        // Show SSID mappings
        if (preg_match('/\.SSID$/', $name)) {
            echo "  {$name} -> {$tr181Name}\n";
            echo "    Value: {$value}\n";
        }
    }
}
if ($skippedPasswords > 0) {
    echo "\n  NOTE: Skipped {$skippedPasswords} password fields (empty/masked in TR-069 backup)\n";
    echo "        To restore passwords, run SSH extraction before migration.\n";
}

// Also look for password/passphrase parameters (may be masked in TR-069)
$passwordParams = [];
foreach ($backupData as $name => $param) {
    $value = is_array($param) ? ($param['value'] ?? '') : $param;
    if (preg_match('/KeyPassphrase|PreSharedKey/', $name) && !empty($value) && $value !== '**********') {
        $passwordParams[$name] = $value;
        echo "  Password param found: {$name} = {$value}\n";
    }
}

echo "\n=== Parameters to Restore (TR-181 Format) ===\n";
echo "Total WiFi parameters mapped: " . count($tr181Params) . "\n";

if (empty($tr181Params)) {
    echo "\nWARNING: No WiFi parameters were mapped!\n";
    echo "Checking backup structure...\n";
    echo "First 3 keys: " . implode(', ', array_slice(array_keys($backupData), 0, 3)) . "\n";
    exit(1);
}

// Show all mapped parameters
foreach ($tr181Params as $name => $param) {
    $displayValue = $param['value'];
    // Mask passwords for display
    if (preg_match('/Passphrase|PreSharedKey/', $name) && strlen($displayValue) > 3) {
        $displayValue = substr($displayValue, 0, 3) . '***';
    }
    echo "  {$name} = {$displayValue}\n";
}

// Ask for confirmation
echo "\n=== Ready to Create WiFi Restore Task ===\n";
echo "Target device: {$newDevice->id}\n";
echo "Parameters to set: " . count($tr181Params) . "\n";

if (isset($argv[3]) && $argv[3] === '--execute') {
    // Create the task
    $task = Task::create([
        'device_id' => $newDevice->id,
        'task_type' => 'set_parameter_values',
        'status' => 'pending',
        'description' => 'Manual WiFi restore from TR-098 backup (post-migration)',
        'parameters' => $tr181Params,
    ]);

    echo "\n=== Task Created ===\n";
    echo "Task ID: {$task->id}\n";
    echo "Status: {$task->status}\n";
    echo "The task will be sent to the device on next inform.\n";

    Log::info('Manual WiFi restore task created', [
        'task_id' => $task->id,
        'new_device_id' => $newDevice->id,
        'old_device_id' => $oldDevice->id,
        'backup_id' => $backup->id,
        'param_count' => count($tr181Params),
    ]);
} else {
    echo "\nDry run complete. Add --execute to create the task.\n";
    echo "Example: php scripts/wifi-restore.php '{$newDeviceId}' '{$oldDeviceId}' --execute\n";
}
