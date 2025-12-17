<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\XmppService;
use Illuminate\Console\Command;

class XmppStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:xmpp-status
                            {--device= : Device ID or serial number to check}
                            {--enabled-only : Show only devices with XMPP enabled}
                            {--supports-xmpp : Show devices that support XMPP}
                            {--nokia : Show only Nokia devices}
                            {--test : Test connection to XMPP server}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check XMPP status for devices and server connectivity';

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
        $enabledOnly = $this->option('enabled-only');
        $supportsXmpp = $this->option('supports-xmpp');
        $nokiaOnly = $this->option('nokia');
        $testConnection = $this->option('test');
        $jsonOutput = $this->option('json');

        // Show server configuration
        $this->showServerConfig();

        // Test server connection if requested
        if ($testConnection) {
            $this->testServerConnection();
            if (!$deviceId && !$enabledOnly && !$supportsXmpp) {
                return 0;
            }
        }

        // Build device query
        $query = Device::query();

        if ($deviceId) {
            $device = Device::find($deviceId);
            if (!$device) {
                $device = Device::where('serial_number', $deviceId)->first();
            }
            if (!$device) {
                $this->error("Device not found: {$deviceId}");
                return 1;
            }
            $this->showDeviceXmppInfo($device, $jsonOutput);
            return 0;
        }

        if ($nokiaOnly) {
            $query->where(function ($q) {
                foreach (Device::NOKIA_OUIS as $oui) {
                    $q->orWhere('oui', $oui);
                }
            });
        }

        if ($enabledOnly) {
            $query->where('xmpp_enabled', true);
        }

        if ($supportsXmpp) {
            // Check for devices with XMPP parameters
            $query->whereHas('parameters', function ($q) {
                $q->where('name', 'LIKE', '%XMPP%')
                    ->orWhere('name', 'LIKE', '%SupportedConnReqMethods%');
            });
        }

        $devices = $query->orderBy('product_class')->get();

        if ($devices->isEmpty()) {
            $this->warn('No devices found matching criteria.');
            return 0;
        }

        if ($jsonOutput) {
            $this->outputJson($devices);
            return 0;
        }

        // Summary statistics
        $stats = $this->calculateStats($devices);
        $this->showStats($stats);

        // Show devices table
        $this->showDevicesTable($devices);

        return 0;
    }

    /**
     * Show XMPP server configuration
     */
    protected function showServerConfig(): void
    {
        $this->newLine();
        $this->info('XMPP Server Configuration:');
        $this->line(str_repeat('-', 50));

        $enabled = config('xmpp.enabled', false);
        $server = config('xmpp.server', 'Not configured');
        $port = config('xmpp.port', 5222);
        $domain = config('xmpp.domain', 'Not configured');
        $username = config('xmpp.username', 'Not configured');

        $this->line("  Enabled:  " . ($enabled ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("  Server:   {$server}");
        $this->line("  Port:     {$port}");
        $this->line("  Domain:   {$domain}");
        $this->line("  Username: {$username}");
        $this->newLine();
    }

    /**
     * Test connection to XMPP server
     */
    protected function testServerConnection(): void
    {
        $this->info('Testing XMPP Server Connection...');

        if (!$this->xmppService->isEnabled()) {
            $this->error('XMPP is not enabled in configuration. Set XMPP_ENABLED=true');
            return;
        }

        $this->line('Attempting to connect...');

        $result = $this->xmppService->connect();

        if ($result) {
            $this->info('<fg=green>Successfully connected to XMPP server!</>');
            $this->xmppService->disconnect();
        } else {
            $this->error('Failed to connect: ' . ($this->xmppService->getLastError() ?? 'Unknown error'));
        }

        $this->newLine();
    }

    /**
     * Show detailed XMPP info for a single device
     */
    protected function showDeviceXmppInfo(Device $device, bool $jsonOutput): void
    {
        $xmppInfo = $this->xmppService->getDeviceXmppInfo($device);
        $dbInfo = [
            'xmpp_jid' => $device->xmpp_jid,
            'xmpp_enabled' => $device->xmpp_enabled,
            'xmpp_last_seen' => $device->xmpp_last_seen?->toDateTimeString(),
            'xmpp_status' => $device->xmpp_status,
        ];

        if ($jsonOutput) {
            $this->line(json_encode([
                'device' => [
                    'id' => $device->id,
                    'serial' => $device->serial_number,
                    'product_class' => $device->product_class,
                ],
                'xmpp_db' => $dbInfo,
                'xmpp_params' => $xmppInfo,
            ], JSON_PRETTY_PRINT));
            return;
        }

        $this->info("Device: {$device->id}");
        $this->line("Serial: {$device->serial_number}");
        $this->line("Type:   {$device->product_class}");
        $this->newLine();

        $this->info('Database XMPP Info:');
        $this->line("  JID:       " . ($dbInfo['xmpp_jid'] ?? 'Not set'));
        $this->line("  Enabled:   " . ($dbInfo['xmpp_enabled'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("  Last Seen: " . ($dbInfo['xmpp_last_seen'] ?? 'Never'));
        $this->line("  Status:    " . ($dbInfo['xmpp_status'] ?? 'Unknown'));
        $this->newLine();

        $this->info('Device Parameters:');
        $this->line("  Supported: " . ($xmppInfo['supported'] ? '<fg=green>Yes</>' : '<fg=yellow>Unknown</>'));
        $this->line("  Enabled:   " . ($xmppInfo['enabled'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("  JID:       " . ($xmppInfo['jid'] ?? 'Not set'));
        $this->line("  Domain:    " . ($xmppInfo['domain'] ?? 'Not set'));
        $this->line("  Port:      " . ($xmppInfo['port'] ?? 'Not set'));
        $this->line("  Use TLS:   " . ($xmppInfo['use_tls'] === null ? 'Unknown' : ($xmppInfo['use_tls'] ? 'Yes' : 'No')));
        $this->line("  Status:    " . ($xmppInfo['status'] ?? 'Unknown'));
        $this->newLine();

        // Show relevant parameters
        $this->info('XMPP Parameters from Device:');
        $params = $device->parameters()
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%XMPP%')
                    ->orWhere('name', 'LIKE', '%SupportedConnReqMethods%');
            })
            ->get();

        if ($params->isEmpty()) {
            $this->line('  <fg=yellow>No XMPP parameters found</>');
        } else {
            foreach ($params as $param) {
                $value = strlen($param->value) > 50 ? substr($param->value, 0, 47) . '...' : $param->value;
                $this->line("  {$param->name} = {$value}");
            }
        }
    }

    /**
     * Calculate statistics for devices
     */
    protected function calculateStats($devices): array
    {
        $total = $devices->count();
        $xmppEnabled = $devices->where('xmpp_enabled', true)->count();
        $hasJid = $devices->whereNotNull('xmpp_jid')->count();

        // Count by support status
        $supportsXmpp = 0;
        foreach ($devices as $device) {
            if ($device->supportsXmpp()) {
                $supportsXmpp++;
            }
        }

        // Count by device type
        $byType = $devices->groupBy('product_class')->map->count();

        return [
            'total' => $total,
            'xmpp_enabled' => $xmppEnabled,
            'has_jid' => $hasJid,
            'supports_xmpp' => $supportsXmpp,
            'by_type' => $byType,
        ];
    }

    /**
     * Show statistics
     */
    protected function showStats(array $stats): void
    {
        $this->info('XMPP Statistics:');
        $this->line(str_repeat('-', 50));
        $this->line("  Total Devices:     {$stats['total']}");
        $this->line("  XMPP Enabled:      {$stats['xmpp_enabled']}");
        $this->line("  Has JID Configured:{$stats['has_jid']}");
        $this->line("  Supports XMPP:     {$stats['supports_xmpp']}");
        $this->newLine();

        $this->info('By Device Type:');
        foreach ($stats['by_type'] as $type => $count) {
            $this->line("  {$type}: {$count}");
        }
        $this->newLine();
    }

    /**
     * Show devices table
     */
    protected function showDevicesTable($devices): void
    {
        $table = [];

        foreach ($devices->take(50) as $device) {
            $table[] = [
                'Serial' => $device->serial_number,
                'Type' => substr($device->product_class, 0, 20),
                'XMPP Enabled' => $device->xmpp_enabled ? 'Yes' : 'No',
                'JID' => $device->xmpp_jid ? substr($device->xmpp_jid, 0, 30) : '-',
                'Last Seen' => $device->xmpp_last_seen?->diffForHumans() ?? 'Never',
                'Status' => $device->xmpp_status ?? '-',
            ];
        }

        $this->table(['Serial', 'Type', 'XMPP Enabled', 'JID', 'Last Seen', 'Status'], $table);

        if ($devices->count() > 50) {
            $this->line("... and " . ($devices->count() - 50) . " more devices");
        }
    }

    /**
     * Output as JSON
     */
    protected function outputJson($devices): void
    {
        $data = [];
        foreach ($devices as $device) {
            $data[] = [
                'id' => $device->id,
                'serial' => $device->serial_number,
                'product_class' => $device->product_class,
                'xmpp_enabled' => $device->xmpp_enabled,
                'xmpp_jid' => $device->xmpp_jid,
                'xmpp_last_seen' => $device->xmpp_last_seen?->toDateTimeString(),
                'xmpp_status' => $device->xmpp_status,
                'supports_xmpp' => $device->supportsXmpp(),
            ];
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }
}
