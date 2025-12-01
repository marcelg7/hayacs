<?php
/**
 * Create test WiFi tasks for Calix with RadioEnabled and optimizations
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;
use App\Models\Task;
use App\Services\ConnectionRequestService;

$serial = $argv[1] ?? 'CXNK0083728A';
$device = Device::where('serial_number', $serial)->first();

if (!$device) {
    echo "Device not found: $serial\n";
    exit(1);
}

echo "=== Creating WiFi Test Tasks for {$device->serial_number} ===\n\n";

// TR-098 parameter path prefix
$prefix = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';
$beaconType = 'WPAand11i';

// Use standard KeyPassphrase for WRITING (X_000631_KeyPassphrase is READ-ONLY)
$passwordParam = 'PreSharedKey.1.KeyPassphrase';

$ssid = 'CalixTest-v3';
$password = 'TestPassword123!';
$guestPassword = 'GuestPass456!';
$enableGuest = true;

echo "SSID: {$ssid}\n";
echo "Password: {$password}\n";
echo "Guest Enabled: " . ($enableGuest ? 'Yes' : 'No') . "\n\n";

// Build 2.4GHz task parameters - includes RadioEnabled and optimizations
$params24 = [
    // Instance 1 - Primary 2.4GHz
    "{$prefix}.1.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.1.SSID" => $ssid,
    "{$prefix}.1.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.1.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.1.BeaconType" => $beaconType,
    "{$prefix}.1.{$passwordParam}" => $password,
    // Disable legacy features that hurt performance
    "{$prefix}.1.X_000631_AirtimeFairness" => ['value' => false, 'type' => 'xsd:boolean'],
    "{$prefix}.1.X_000631_MulticastForwardEnable" => ['value' => false, 'type' => 'xsd:boolean'],
    // Instance 2 - Guest 2.4GHz
    "{$prefix}.2.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.2.SSID" => $ssid . '-Guest',
    "{$prefix}.2.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
    "{$prefix}.2.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
    "{$prefix}.2.BeaconType" => $beaconType,
    "{$prefix}.2.{$passwordParam}" => $guestPassword,
    "{$prefix}.2.X_000631_IntraSsidIsolation" => ['value' => true, 'type' => 'xsd:boolean'],
];

// Build 5GHz task parameters
$params5 = [
    // Instance 9 - Primary 5GHz
    "{$prefix}.9.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.9.SSID" => $ssid,
    "{$prefix}.9.Enable" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.9.SSIDAdvertisementEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.9.BeaconType" => $beaconType,
    "{$prefix}.9.{$passwordParam}" => $password,
    // Instance 10 - Guest 5GHz
    "{$prefix}.10.RadioEnabled" => ['value' => true, 'type' => 'xsd:boolean'],
    "{$prefix}.10.SSID" => $ssid . '-Guest',
    "{$prefix}.10.Enable" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
    "{$prefix}.10.SSIDAdvertisementEnabled" => ['value' => $enableGuest, 'type' => 'xsd:boolean'],
    "{$prefix}.10.BeaconType" => $beaconType,
    "{$prefix}.10.{$passwordParam}" => $guestPassword,
    "{$prefix}.10.X_000631_IntraSsidIsolation" => ['value' => true, 'type' => 'xsd:boolean'],
];

// Create 2.4GHz task
$task1 = Task::create([
    'device_id' => $device->id,
    'task_type' => 'set_parameter_values',
    'description' => 'WiFi: Configure 2.4GHz (RadioEnabled + Optimizations)',
    'parameters' => $params24,
    'status' => 'pending',
]);

// Create 5GHz task
$task2 = Task::create([
    'device_id' => $device->id,
    'task_type' => 'set_parameter_values',
    'description' => 'WiFi: Configure 5GHz (RadioEnabled)',
    'parameters' => $params5,
    'status' => 'pending',
]);

echo "Created Task #{$task1->id} (2.4GHz)\n";
echo "Created Task #{$task2->id} (5GHz)\n\n";

// Show parameters
echo "2.4GHz task parameters:\n";
foreach ($params24 as $key => $val) {
    $valStr = is_array($val) ? json_encode($val) : $val;
    echo "  {$key} = {$valStr}\n";
}

echo "\n5GHz task parameters:\n";
foreach ($params5 as $key => $val) {
    $valStr = is_array($val) ? json_encode($val) : $val;
    echo "  {$key} = {$valStr}\n";
}

// Send connection request
echo "\nSending connection request...\n";
$service = app(ConnectionRequestService::class);
$result = $service->sendConnectionRequest($device);
echo "Result: " . json_encode($result) . "\n";
