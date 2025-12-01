<?php
/**
 * Check Calix Radio status
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

echo "=== Calix Radio Status for {$device->serial_number} ===\n\n";

// Check the Radio objects
echo "=== X_000631 Radio Parameters ===\n";
$radioParams = $device->parameters()
    ->where('name', 'LIKE', 'InternetGatewayDevice.X_000631_Device.WiFi.Radio.%')
    ->where('name', 'NOT LIKE', '%Stats%')
    ->orderBy('name')
    ->get(['name', 'value']);

foreach ($radioParams as $p) {
    // Only show key params
    if (strpos($p->name, 'Enable') !== false ||
        strpos($p->name, 'OperatingFrequency') !== false ||
        strpos($p->name, 'Status') !== false ||
        (strpos($p->name, 'Channel') !== false && strpos($p->name, 'Bandwidth') === false)) {
        echo "{$p->name} = {$p->value}\n";
    }
}

echo "\n=== All Radio Parameters ===\n";
foreach ($radioParams as $p) {
    echo "{$p->name} = {$p->value}\n";
}
