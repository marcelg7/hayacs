#!/usr/bin/env php
<?php

/**
 * Debug WiFi parameter retrieval
 * Checks what parameters were requested and received for a device
 *
 * Usage: php check-wifi-params.php <device-id>
 * Example: php check-wifi-params.php D0768F-ENT-CXNK0083728A
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Validate arguments
if ($argc < 2) {
    echo "Usage: php check-wifi-params.php <device-id>\n";
    echo "Example: php check-wifi-params.php D0768F-ENT-CXNK0083728A\n";
    exit(1);
}

$deviceId = $argv[1];

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get device
$device = App\Models\Device::find($deviceId);
if (!$device) {
    echo "Error: Device not found: $deviceId\n";
    exit(1);
}

echo "Device: {$device->id}\n";
echo "Manufacturer: {$device->manufacturer}\n";
echo "Product: {$device->product_class}\n\n";

// Check recent tasks
echo "=== Recent Tasks (last 10) ===\n";
$tasks = App\Models\Task::where('device_id', $device->id)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($tasks as $task) {
    echo "\nTask ID: {$task->id}\n";
    echo "Type: {$task->task_type}\n";
    echo "Status: {$task->status}\n";
    echo "Created: {$task->created_at}\n";

    if ($task->task_type === 'get_params' && isset($task->parameters['names'])) {
        $names = $task->parameters['names'];
        $wifiParams = array_filter($names, fn($n) => str_contains($n, 'WLANConfiguration'));
        $keyPassphraseParams = array_filter($names, fn($n) => str_contains($n, 'KeyPassphrase'));

        echo "  Parameters requested: " . count($names) . "\n";
        echo "  WiFi parameters: " . count($wifiParams) . "\n";
        echo "  KeyPassphrase parameters: " . count($keyPassphraseParams) . "\n";

        if (!empty($keyPassphraseParams)) {
            echo "  KeyPassphrase params requested:\n";
            foreach ($keyPassphraseParams as $param) {
                echo "    - $param\n";
            }
        }
    }

    if ($task->status === 'completed' && $task->result) {
        $keyPassphraseResults = array_filter(
            array_keys($task->result),
            fn($n) => str_contains($n, 'KeyPassphrase')
        );

        if (!empty($keyPassphraseResults)) {
            echo "  KeyPassphrase in results:\n";
            foreach ($keyPassphraseResults as $param) {
                $value = $task->result[$param]['value'] ?? 'N/A';
                echo "    - $param = $value\n";
            }
        }
    }
}

// Check parameters stored in database
echo "\n\n=== WiFi Parameters in Database ===\n";
$wifiParams = App\Models\Parameter::where('device_id', $device->id)
    ->where('name', 'like', '%WLANConfiguration%')
    ->orderBy('name')
    ->get();

echo "Total WiFi parameters: " . $wifiParams->count() . "\n\n";

// Group by instance
$byInstance = $wifiParams->groupBy(function ($param) {
    if (preg_match('/WLANConfiguration\.(\d+)\./', $param->name, $matches)) {
        return 'Instance ' . $matches[1];
    }
    return 'Unknown';
});

foreach ($byInstance as $instance => $params) {
    echo "$instance:\n";

    // Find key parameters
    $ssid = $params->firstWhere('name', 'like', '%.SSID');
    $enabled = $params->firstWhere('name', 'like', '%.Enable');
    $passphrase = $params->firstWhere('name', 'like', '%KeyPassphrase%');

    if ($ssid) {
        echo "  SSID: {$ssid->value}\n";
    }
    if ($enabled) {
        echo "  Enabled: {$enabled->value}\n";
    }
    if ($passphrase) {
        echo "  Password: {$passphrase->value}\n";
        echo "  Parameter: {$passphrase->name}\n";
    } else {
        // Check if any KeyPassphrase parameter exists for this instance
        $hasPassphrase = $params->filter(fn($p) => str_contains($p->name, 'KeyPassphrase'))->isNotEmpty();
        echo "  Password: " . ($hasPassphrase ? "Found but not matched" : "NOT FOUND") . "\n";
    }

    echo "  Total params: " . $params->count() . "\n\n";
}

// Check for ANY KeyPassphrase parameters
echo "\n=== All KeyPassphrase Parameters ===\n";
$keyPassphraseParams = App\Models\Parameter::where('device_id', $device->id)
    ->where('name', 'like', '%KeyPassphrase%')
    ->get();

if ($keyPassphraseParams->isEmpty()) {
    echo "No KeyPassphrase parameters found.\n";
    echo "\nPossible reasons:\n";
    echo "1. Haven't run 'Refresh Troubleshooting Info' since code update\n";
    echo "2. Device doesn't return password parameters (security restriction)\n";
    echo "3. Parameter name is different for this device model\n";
} else {
    foreach ($keyPassphraseParams as $param) {
        echo "Parameter: {$param->name}\n";
        echo "Value: {$param->value}\n";
        echo "Type: {$param->type}\n";
        echo "Last Updated: {$param->last_updated}\n\n";
    }
}

echo "\nNext steps:\n";
echo "1. If no KeyPassphrase params found, click 'Refresh Troubleshooting Info' button\n";
echo "2. Wait for tasks to complete\n";
echo "3. Run this script again to verify parameters were retrieved\n";
echo "4. Check storage/logs/laravel.log for detailed TR-069 session logs\n";
