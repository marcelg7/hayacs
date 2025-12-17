<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class GenerateProsodyAccounts extends Command
{
    protected $signature = 'xmpp:generate-accounts
                            {--nokia-beacons : Generate for Nokia Beacon mesh APs only}
                            {--all-xmpp : Generate for all devices that support XMPP}
                            {--device= : Generate for a single device}
                            {--output= : Output file for the script (default: stdout)}
                            {--password-length=16 : Length of generated passwords}';

    protected $description = 'Generate prosodyctl commands to create XMPP accounts for devices';

    public function handle()
    {
        $domain = config('xmpp.domain', 'hayacs.hay.net');
        $passwordLength = (int) $this->option('password-length');
        $outputFile = $this->option('output');

        // Build device query
        if ($deviceId = $this->option('device')) {
            $device = Device::find($deviceId) ?? Device::where('serial_number', $deviceId)->first();
            if (!$device) {
                $this->error("Device not found: {$deviceId}");
                return 1;
            }
            $devices = collect([$device]);
        } elseif ($this->option('nokia-beacons')) {
            // Nokia Beacon mesh APs (not G6 or 24 which are gateways)
            $devices = Device::where(function ($q) {
                    foreach (Device::NOKIA_OUIS as $oui) {
                        $q->orWhere('oui', $oui);
                    }
                })
                ->where('product_class', 'LIKE', '%Beacon%')
                ->where('product_class', 'NOT LIKE', '%Beacon G6%')
                ->where('product_class', 'NOT LIKE', '%Beacon 24%')
                ->orderBy('serial_number')
                ->get();
        } elseif ($this->option('all-xmpp')) {
            // All devices with XMPP support
            $devices = Device::whereHas('parameters', function ($q) {
                    $q->where('name', 'LIKE', '%SupportedConnReqMethods%')
                      ->where('value', 'LIKE', '%XMPP%');
                })
                ->orderBy('serial_number')
                ->get();
        } else {
            $this->error('Please specify --nokia-beacons, --all-xmpp, or --device=ID');
            return 1;
        }

        if ($devices->isEmpty()) {
            $this->warn('No devices found.');
            return 0;
        }

        $this->info("Generating Prosody accounts for {$devices->count()} device(s)...");
        $this->newLine();

        $script = "#!/bin/bash\n";
        $script .= "# Prosody account creation script\n";
        $script .= "# Generated: " . now()->toDateTimeString() . "\n";
        $script .= "# Domain: {$domain}\n";
        $script .= "# Devices: {$devices->count()}\n\n";

        $credentials = [];

        foreach ($devices as $device) {
            $username = $device->serial_number;
            $password = $this->generatePassword($passwordLength);

            $script .= "# {$device->product_class} - {$device->serial_number}\n";
            $script .= "prosodyctl register {$username} {$domain} '{$password}' 2>/dev/null && echo 'Created: {$username}@{$domain}' || echo 'User {$username} may already exist'\n";
            $script .= "\n";

            $credentials[] = [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'product_class' => $device->product_class,
                'username' => $username,
                'password' => $password,
                'jid' => "{$username}@{$domain}",
            ];
        }

        $script .= "\necho 'Done! Created accounts for {$devices->count()} devices.'\n";

        // Output script
        if ($outputFile) {
            file_put_contents($outputFile, $script);
            chmod($outputFile, 0700);
            $this->info("Script written to: {$outputFile}");
            $this->line("Run with: sudo bash {$outputFile}");
        } else {
            $this->line($script);
        }

        // Also save credentials to a JSON file for use by enable-xmpp command
        $credentialsFile = storage_path('app/xmpp_credentials.json');
        file_put_contents($credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT));
        chmod($credentialsFile, 0600);
        $this->newLine();
        $this->info("Credentials saved to: {$credentialsFile}");
        $this->warn("Keep this file secure - it contains passwords!");

        // Show summary table
        $this->newLine();
        $this->info("Summary:");
        $this->table(
            ['Serial', 'Type', 'JID'],
            collect($credentials)->take(20)->map(fn($c) => [
                $c['serial'],
                substr($c['product_class'], 0, 15),
                $c['jid'],
            ])->toArray()
        );

        if (count($credentials) > 20) {
            $this->line("... and " . (count($credentials) - 20) . " more");
        }

        return 0;
    }

    protected function generatePassword(int $length): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
