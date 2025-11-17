#!/usr/bin/env php
<?php

/**
 * Update TR-069 device STUN server configuration
 * Run this script on your production server to update the device to use your coturn server
 *
 * Usage: php update-stun-server.php <device-id> <stun-server> <stun-port>
 * Example: php update-stun-server.php D0768F-ENT-CXNK0083728A hayacs.hay.net 3478
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Validate arguments
if ($argc < 2) {
    echo "Usage: php update-stun-server.php <device-id> [stun-server] [stun-port]\n";
    echo "Example: php update-stun-server.php D0768F-ENT-CXNK0083728A hayacs.hay.net 3478\n";
    exit(1);
}

$deviceId = $argv[1];
$stunServer = $argv[2] ?? 'hayacs.hay.net';
$stunPort = $argv[3] ?? 3478;

echo "Updating STUN configuration for device: $deviceId\n";
echo "STUN Server: $stunServer:$stunPort\n\n";

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

echo "Device found: {$device->manufacturer} {$device->product_class}\n";
echo "Serial: {$device->serial_number}\n\n";

// Create SetParameterValues task
$task = App\Models\Task::create([
    'device_id' => $device->id,
    'task_type' => 'set_params',
    'parameters' => [
        'values' => [
            'InternetGatewayDevice.ManagementServer.STUNServerAddress' => $stunServer,
            'InternetGatewayDevice.ManagementServer.STUNServerPort' => [
                'value' => (int) $stunPort,
                'type' => 'xsd:unsignedInt',
            ],
        ],
    ],
    'status' => 'pending',
]);

echo "✓ Task created (ID: {$task->id})\n";
echo "  Task type: {$task->task_type}\n";
echo "  Status: {$task->status}\n\n";

// Trigger connection request
echo "Sending connection request to device...\n";
$connectionRequestService = app(App\Services\ConnectionRequestService::class);

try {
    $result = $connectionRequestService->sendConnectionRequestWithFallback($device);

    if ($result['success']) {
        echo "✓ {$result['message']}\n";
    } else {
        echo "✗ {$result['message']}\n";
        echo "  (Device will pick up the task on next periodic inform)\n";
    }
} catch (Exception $e) {
    echo "✗ Connection request failed: {$e->getMessage()}\n";
    echo "  (Device will pick up the task on next periodic inform)\n";
}

echo "\nNext steps:\n";
echo "1. Wait for device to connect and execute the task\n";
echo "2. Monitor logs: tail -f storage/logs/laravel.log | grep -i stun\n";
echo "3. Check for UDP address in device Inform messages\n";
echo "4. Monitor coturn logs: sudo tail -f /var/log/turnserver.log\n\n";
