<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Task;
use App\Services\XmppService;
use Illuminate\Console\Command;

class EnableXmpp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:enable-xmpp
                            {--device= : Device ID or serial number to enable XMPP on}
                            {--type= : Device type (product_class) to enable XMPP on}
                            {--nokia-beacons : Enable on all Nokia Beacon mesh APs}
                            {--dry-run : Show what would be done without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable XMPP connection requests on devices via TR-069';

    protected XmppService $xmppService;

    public function __construct(XmppService $xmppService)
    {
        parent::__construct();
        $this->xmppService = $xmppService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deviceId = $this->option('device');
        $deviceType = $this->option('type');
        $nokiaBeacons = $this->option('nokia-beacons');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Get XMPP configuration
        $domain = config('xmpp.domain');
        $port = config('xmpp.port', 5222);

        if (empty($domain)) {
            $this->error('XMPP domain not configured. Set XMPP_DOMAIN in .env');
            return 1;
        }

        $this->info("XMPP Configuration:");
        $this->line("  Domain: {$domain}");
        $this->line("  Port: {$port}");
        $this->newLine();

        // Build device query
        $query = Device::query();

        if ($deviceId) {
            // Single device
            $device = Device::find($deviceId);
            if (!$device) {
                $device = Device::where('serial_number', $deviceId)->first();
            }
            if (!$device) {
                $this->error("Device not found: {$deviceId}");
                return 1;
            }
            $devices = collect([$device]);
        } elseif ($nokiaBeacons) {
            // All Nokia Beacon mesh APs (Beacon 2, Beacon 3, Beacon 3.1)
            $devices = Device::where(function ($q) {
                    foreach (Device::NOKIA_OUIS as $oui) {
                        $q->orWhere('oui', $oui);
                    }
                })
                ->where('product_class', 'LIKE', '%Beacon%')
                ->where('product_class', 'NOT LIKE', '%Beacon G6%')
                ->where('product_class', 'NOT LIKE', '%Beacon 24%')
                ->get();
        } elseif ($deviceType) {
            // All devices of a specific type
            $devices = Device::where('product_class', 'LIKE', "%{$deviceType}%")->get();
        } else {
            $this->error('Please specify --device, --type, or --nokia-beacons');
            return 1;
        }

        if ($devices->isEmpty()) {
            $this->warn('No devices found matching criteria.');
            return 0;
        }

        // Show devices that will be configured
        $this->info("Devices to configure ({$devices->count()}):");
        $this->newLine();

        $table = [];
        foreach ($devices as $device) {
            $xmppInfo = $this->xmppService->getDeviceXmppInfo($device);
            $supportsXmpp = $device->supportsXmpp() ? 'Yes' : 'Unknown';
            $currentStatus = $xmppInfo['enabled'] ? 'Enabled' : 'Disabled';
            $currentJid = $xmppInfo['jid'] ?? 'Not set';

            // Generate new JID
            $newJid = $this->xmppService->generateJid($device);

            $table[] = [
                'ID' => substr($device->id, 0, 30) . (strlen($device->id) > 30 ? '...' : ''),
                'Serial' => $device->serial_number,
                'Type' => $device->product_class,
                'Supports XMPP' => $supportsXmpp,
                'Current Status' => $currentStatus,
                'New JID' => $newJid,
            ];
        }

        $this->table(['ID', 'Serial', 'Type', 'Supports XMPP', 'Current Status', 'New JID'], $table);
        $this->newLine();

        if ($dryRun) {
            $this->info('[DRY RUN] Would create TR-069 tasks to enable XMPP on ' . $devices->count() . ' device(s)');
            return 0;
        }

        // Confirm
        if (!$force && !$this->confirm("Create TR-069 tasks to enable XMPP on {$devices->count()} device(s)?")) {
            $this->info('Cancelled.');
            return 0;
        }

        // Create tasks for each device
        $tasksCreated = 0;
        $errors = [];

        // Load credentials from generate-accounts command if available
        $credentialsFile = storage_path('app/xmpp_credentials.json');
        $savedCredentials = [];
        if (file_exists($credentialsFile)) {
            $savedCredentials = collect(json_decode(file_get_contents($credentialsFile), true))
                ->keyBy('serial')
                ->toArray();
            $this->info("Loaded " . count($savedCredentials) . " saved credentials from xmpp_credentials.json");
        } else {
            $this->warn("No saved credentials found. Run 'php artisan xmpp:generate-accounts' first to create Prosody accounts.");
        }

        $bar = $this->output->createProgressBar($devices->count());
        $bar->start();

        foreach ($devices as $device) {
            try {
                // Generate JID (serial@domain/cwmp)
                $newJid = $this->xmppService->generateJid($device);

                // Get credentials - prefer saved ones, fallback to generated
                $username = $device->serial_number;
                if (isset($savedCredentials[$device->serial_number])) {
                    $password = $savedCredentials[$device->serial_number]['password'];
                } else {
                    $this->warn("No saved credentials for {$device->serial_number} - using generated password");
                    $password = substr(hash('sha256', $device->serial_number . config('app.key')), 0, 16);
                }

                $params = $device->getXmppEnableParameters($domain, $username, $password, $port);

                // Create SetParameterValues task
                $task = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_parameter_values',
                    'status' => 'pending',
                    'description' => 'Enable XMPP connection request',
                    'parameters' => $params,
                ]);

                // Update device with the new JID
                $device->update([
                    'xmpp_jid' => $newJid,
                    'xmpp_enabled' => false, // Will be true once device confirms
                    'xmpp_status' => 'pending_enable',
                ]);

                $tasksCreated++;
            } catch (\Exception $e) {
                $errors[] = "{$device->id}: {$e->getMessage()}";
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Created {$tasksCreated} task(s) to enable XMPP.");

        if (!empty($errors)) {
            $this->warn('Errors:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        $this->newLine();
        $this->line('Tasks will execute when devices next connect to the ACS.');
        $this->line('Use "php artisan devices:xmpp-status" to check XMPP status.');

        return 0;
    }
}
