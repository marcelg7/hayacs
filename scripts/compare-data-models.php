<?php
/**
 * Compare TR-098 vs TR-181 parameters for Nokia Beacon G6
 * Used for planning the migration restore mapping
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;
use App\Models\Parameter;

$tr098Id = '80AB4D-Beacon G6-ALCLFD0A7F1E';
$tr181Id = '0C7C28-Beacon G6-ALCLFD0A7F1E';

$tr098 = Device::find($tr098Id);
$tr181 = Device::find($tr181Id);

if (!$tr098 || !$tr181) {
    echo "Error: One or both devices not found\n";
    exit(1);
}

echo "=== Nokia Beacon G6: TR-098 vs TR-181 Parameter Comparison ===\n\n";
echo "TR-098 Device: {$tr098Id} ({$tr098->parameters()->count()} params)\n";
echo "TR-181 Device: {$tr181Id} ({$tr181->parameters()->count()} params)\n\n";

// Categories to analyze
$categories = [
    'WiFi SSID' => [
        'tr098' => '%WLANConfiguration.%.SSID',
        'tr181' => '%WiFi.SSID.%.SSID',
    ],
    'WiFi Password' => [
        'tr098' => '%WLANConfiguration.%.PreSharedKey.%.KeyPassphrase',
        'tr181' => '%WiFi.AccessPoint.%.Security.KeyPassphrase',
    ],
    'WiFi Radio Enable' => [
        'tr098' => '%WLANConfiguration.%.Enable',
        'tr181' => '%WiFi.Radio.%.Enable',
    ],
    'WiFi Channel' => [
        'tr098' => '%WLANConfiguration.%.Channel',
        'tr181' => '%WiFi.Radio.%.Channel',
    ],
    'Port Forwarding' => [
        'tr098' => '%PortMapping.%',
        'tr181' => '%NAT.PortMapping.%',
    ],
    'Time Zone' => [
        'tr098' => '%Time.LocalTimeZone%',
        'tr181' => '%Time.LocalTimeZone%',
    ],
    'NTP Server' => [
        'tr098' => '%Time.NTPServer%',
        'tr181' => '%Time.NTPServer%',
    ],
    'Trusted Networks' => [
        'tr098' => '%TrustedNetwork.%',
        'tr181' => '%TrustedNetwork.%',
    ],
    'DMZ' => [
        'tr098' => '%DmzIpHostCfg%',
        'tr181' => '%DMZ%',
    ],
    'DHCP Pool' => [
        'tr098' => '%LANHostConfigManagement.MinAddress',
        'tr181' => '%DHCPv4.Server.Pool.%.MinAddress',
    ],
];

foreach ($categories as $category => $patterns) {
    echo "=== {$category} ===\n";

    echo "TR-098:\n";
    $tr098Params = $tr098->parameters()
        ->where('name', 'LIKE', $patterns['tr098'])
        ->where('value', '!=', '')
        ->orderBy('name')
        ->get();

    if ($tr098Params->isEmpty()) {
        echo "  (no matches)\n";
    } else {
        foreach ($tr098Params->take(10) as $p) {
            $val = strlen($p->value) > 50 ? substr($p->value, 0, 50) . '...' : $p->value;
            echo "  {$p->name}\n    = {$val}\n";
        }
        if ($tr098Params->count() > 10) {
            echo "  ... and " . ($tr098Params->count() - 10) . " more\n";
        }
    }

    echo "\nTR-181:\n";
    $tr181Params = $tr181->parameters()
        ->where('name', 'LIKE', $patterns['tr181'])
        ->where('value', '!=', '')
        ->orderBy('name')
        ->get();

    if ($tr181Params->isEmpty()) {
        echo "  (no matches)\n";
    } else {
        foreach ($tr181Params->take(10) as $p) {
            $val = strlen($p->value) > 50 ? substr($p->value, 0, 50) . '...' : $p->value;
            echo "  {$p->name}\n    = {$val}\n";
        }
        if ($tr181Params->count() > 10) {
            echo "  ... and " . ($tr181Params->count() - 10) . " more\n";
        }
    }

    echo "\n";
}

// Now let's find key differences in the root structure
echo "=== Root Parameter Paths ===\n";
echo "TR-098 root paths:\n";
$tr098Roots = $tr098->parameters()
    ->selectRaw("SUBSTRING_INDEX(name, '.', 2) as root")
    ->groupBy('root')
    ->orderBy('root')
    ->pluck('root');

foreach ($tr098Roots->take(20) as $root) {
    echo "  {$root}\n";
}

echo "\nTR-181 root paths:\n";
$tr181Roots = $tr181->parameters()
    ->selectRaw("SUBSTRING_INDEX(name, '.', 2) as root")
    ->groupBy('root')
    ->orderBy('root')
    ->pluck('root');

foreach ($tr181Roots->take(20) as $root) {
    echo "  {$root}\n";
}
