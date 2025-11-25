<?php
/**
 * Send connection requests to all devices in the ACS
 * Usage: php send-connection-requests.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;

$devices = Device::all();
echo "Sending connection requests to {$devices->count()} devices...\n\n";

foreach ($devices as $device) {
    echo "{$device->id}: ";

    $url = $device->connection_request_url;
    if (!$url) {
        echo "No URL configured\n";
        continue;
    }

    $username = $device->connection_request_username ?? '';
    $password = $device->connection_request_password ?? '';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($username && $password) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode == 200 || $httpCode == 204) {
        echo "OK ($httpCode)\n";
    } elseif ($httpCode == 401) {
        echo "Auth required ($httpCode)\n";
    } elseif ($httpCode == 0) {
        echo "Timeout/Unreachable\n";
    } else {
        echo "Response: $httpCode\n";
    }
}

echo "\nDone! Devices should start checking in shortly.\n";
