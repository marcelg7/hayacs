<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Task;
use App\Services\ConnectionRequestService;
use Illuminate\Console\Command;

class CloseStaleRemoteGui extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:close-stale-remote-gui
                            {--minutes=60 : Close remote GUI sessions older than this many minutes}
                            {--dry-run : Show what would be closed without actually closing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close remote GUI access on devices that have been open too long';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionRequestService $connectionRequestService)
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        $this->info("Finding devices with remote GUI enabled for more than {$minutes} minutes...");

        // Find all devices with remote_gui_enabled_at older than the threshold
        $devices = Device::whereNotNull('remote_gui_enabled_at')
            ->where('remote_gui_enabled_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No stale remote GUI sessions found.');
            return 0;
        }

        $this->info("Found {$devices->count()} device(s) with stale remote GUI sessions.");

        $closed = 0;
        foreach ($devices as $device) {
            $enabledAt = $device->remote_gui_enabled_at;
            $duration = $enabledAt->diffForHumans();

            $this->line("  - {$device->id} (open since {$duration})");

            if ($dryRun) {
                continue;
            }

            // Determine the disable parameters based on device type
            $dataModel = $device->getDataModel();
            $nokiaOuis = ['80AB4D', '80:AB:4D', '0C7C28'];
            $isNokia = in_array(strtoupper($device->oui ?? ''), $nokiaOuis) ||
                       stripos($device->manufacturer ?? '', 'Nokia') !== false ||
                       stripos($device->manufacturer ?? '', 'Alcatel') !== false ||
                       stripos($device->manufacturer ?? '', 'ALCL') !== false;

            $disableParams = [];

            if ($isNokia && $dataModel === 'TR-181') {
                $disableParams = [
                    'Device.UserInterface.RemoteAccess.Enable' => [
                        'value' => false,
                        'type' => 'xsd:boolean',
                    ],
                ];
            } elseif ($isNokia && $dataModel === 'TR-098') {
                $disableParams = [
                    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ALU-COM_WanAccessCfg.HttpsDisabled' => [
                        'value' => true,
                        'type' => 'xsd:boolean',
                    ],
                ];
            } elseif ($dataModel === 'TR-181') {
                $disableParams = [
                    'Device.UserInterface.RemoteAccess.Enable' => [
                        'value' => false,
                        'type' => 'xsd:boolean',
                    ],
                ];
            } else {
                $disableParams = [
                    'InternetGatewayDevice.UserInterface.RemoteAccess.Enable' => [
                        'value' => false,
                        'type' => 'xsd:boolean',
                    ],
                    'InternetGatewayDevice.User.2.RemoteAccessCapable' => [
                        'value' => false,
                        'type' => 'xsd:boolean',
                    ],
                ];
            }

            // Create a task to disable remote access
            Task::create([
                'device_id' => $device->id,
                'task_type' => 'set_parameter_values',
                'status' => 'pending',
                'parameters' => $disableParams,
            ]);

            // Clear the flag
            $device->remote_gui_enabled_at = null;
            $device->save();

            // Trigger connection request to apply the change
            try {
                $connectionRequestService->sendConnectionRequest($device);
            } catch (\Exception $e) {
                $this->warn("    Could not send connection request: {$e->getMessage()}");
            }

            $closed++;
        }

        if ($dryRun) {
            $this->info("Dry run complete. Would have closed {$devices->count()} session(s).");
        } else {
            $this->info("Closed {$closed} remote GUI session(s).");
        }

        return 0;
    }
}
