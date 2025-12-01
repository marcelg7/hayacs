<?php
/**
 * Check Calix WiFi structure
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;

$serial = $argv[1] ?? 'CXNK0083728A';
$device = Device::where('serial_number', $serial)->first();

if (!$device) {
    echo "Device not found: $serial\n";
    exit(1);
}

echo "=== Calix Device: {$device->serial_number} ===\n";
echo "OUI: {$device->oui}\n";
echo "Manufacturer: {$device->manufacturer}\n";
echo "Data Model: {$device->getDataModel()}\n\n";

// Get WiFi SSID params
$ssidParams = $device->parameters()
    ->where('name', 'LIKE', 'Device.WiFi.SSID.%')
    ->where('name', 'NOT LIKE', '%Stats%')
    ->orderBy('name')
    ->get(['name', 'value']);

echo "=== WiFi SSID Parameters (TR-181) ===\n";
foreach ($ssidParams as $p) {
    if (strpos($p->name, '.SSID') !== false ||
        strpos($p->name, '.Enable') !== false ||
        strpos($p->name, '.LowerLayers') !== false) {
        echo "{$p->name} = {$p->value}\n";
    }
}

// Also check TR-098 style
$wlanParams = $device->parameters()
    ->where('name', 'LIKE', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%')
    ->where('name', 'NOT LIKE', '%Stats%')
    ->where('name', 'NOT LIKE', '%AssociatedDevice%')
    ->orderBy('name')
    ->get(['name', 'value']);

echo "\n=== TR-098 WLAN Parameters ===\n";
foreach ($wlanParams as $p) {
    if (strpos($p->name, '.SSID') !== false ||
        strpos($p->name, '.Enable') !== false ||
        strpos($p->name, '.Channel') !== false ||
        strpos($p->name, '.BeaconType') !== false ||
        strpos($p->name, '.KeyPassphrase') !== false ||
        strpos($p->name, '.Standard') !== false) {
        echo "{$p->name} = {$p->value}\n";
    }
}

// Check for Calix vendor-specific parameters
echo "\n=== Calix Vendor Parameters (X_000631) ===\n";
$vendorParams = $device->parameters()
    ->where('name', 'LIKE', '%X_000631%')
    ->orderBy('name')
    ->get(['name', 'value']);

foreach ($vendorParams as $p) {
    echo "{$p->name} = {$p->value}\n";
}

echo "\n=== WiFi AccessPoint Parameters ===\n";
$apParams = $device->parameters()
    ->where('name', 'LIKE', 'Device.WiFi.AccessPoint.%')
    ->where('name', 'NOT LIKE', '%Stats%')
    ->where('name', 'NOT LIKE', '%AC.%')
    ->orderBy('name')
    ->get(['name', 'value']);

foreach ($apParams as $p) {
    if (strpos($p->name, '.Enable') !== false ||
        strpos($p->name, '.ModeEnabled') !== false ||
        strpos($p->name, '.KeyPassphrase') !== false ||
        strpos($p->name, '.SSIDAdvertisement') !== false ||
        strpos($p->name, '.IsolationEnable') !== false) {
        echo "{$p->name} = {$p->value}\n";
    }
}

echo "\n=== WiFi Radio Parameters ===\n";
$radioParams = $device->parameters()
    ->where('name', 'LIKE', 'Device.WiFi.Radio.%')
    ->where('name', 'NOT LIKE', '%Stats%')
    ->orderBy('name')
    ->get(['name', 'value']);

foreach ($radioParams as $p) {
    if (strpos($p->name, '.Enable') !== false ||
        strpos($p->name, '.OperatingFrequency') !== false ||
        strpos($p->name, '.Channel') !== false) {
        echo "{$p->name} = {$p->value}\n";
    }
}
