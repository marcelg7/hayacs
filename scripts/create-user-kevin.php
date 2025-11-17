#!/usr/bin/env php
<?php

/**
 * Create user account for Kevin Gingerich
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check if user already exists
$existingUser = App\Models\User::where('email', 'kevin@haymail.ca')->first();

if ($existingUser) {
    echo "User with email kevin@haymail.ca already exists!\n";
    echo "User ID: {$existingUser->id}\n";
    echo "Name: {$existingUser->name}\n";
    echo "Email: {$existingUser->email}\n";
    echo "Must change password: " . ($existingUser->must_change_password ? 'Yes' : 'No') . "\n";
    echo "\nTo reset the password, delete this user first and run this script again.\n";
    exit(1);
}

// Create the user
$tempPassword = 'HayACS2025!';

$user = App\Models\User::create([
    'name' => 'Kevin Gingerich',
    'email' => 'kevin@haymail.ca',
    'password' => $tempPassword,
    'must_change_password' => true,
]);

echo "✓ User created successfully!\n\n";
echo "Details:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Name:              Kevin Gingerich\n";
echo "Email:             kevin@haymail.ca\n";
echo "Temporary Password: {$tempPassword}\n";
echo "Must Change Password: Yes\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Kevin will be prompted to change the password on first login.\n";
