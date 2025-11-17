#!/usr/bin/env php
<?php

/**
 * Get task details
 * Usage: php get-task.php <task-id>
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

if ($argc < 2) {
    echo "Usage: php get-task.php <task-id>\n";
    exit(1);
}

$taskId = $argv[1];

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get task
$task = App\Models\Task::find($taskId);
if (!$task) {
    echo "Error: Task not found: $taskId\n";
    exit(1);
}

echo "Task ID: {$task->id}\n";
echo "Device ID: {$task->device_id}\n";
echo "Task Type: {$task->task_type}\n";
echo "Status: {$task->status}\n";
echo "Created: {$task->created_at}\n";
echo "Updated: {$task->updated_at}\n";
echo "\nParameters:\n";
echo json_encode($task->parameters, JSON_PRETTY_PRINT) . "\n";

if ($task->result) {
    echo "\nResult:\n";
    echo json_encode($task->result, JSON_PRETTY_PRINT) . "\n";
}

if ($task->error) {
    echo "\nError: {$task->error}\n";
}
