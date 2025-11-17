#!/usr/bin/env php
<?php

/**
 * Check recent tasks for a device
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$deviceId = $argv[1] ?? 'D0768F-ENT-CXNK0083728A';

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get recent tasks
$tasks = App\Models\Task::where('device_id', $deviceId)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "Recent tasks for device: $deviceId\n\n";

foreach ($tasks as $task) {
    echo "Task ID: {$task->id}\n";
    echo "Type: {$task->task_type}\n";
    echo "Status: {$task->status}\n";
    echo "Created: {$task->created_at}\n";
    echo "Updated: {$task->updated_at}\n";

    if ($task->error) {
        echo "Error: {$task->error}\n";
    }

    echo "\n";
}
