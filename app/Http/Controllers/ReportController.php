<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Task;
use App\Models\CwmpSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    /**
     * Cache duration for report summaries (in seconds)
     */
    private const SUMMARY_CACHE_TTL = 300; // 5 minutes

    /**
     * Cache duration for expensive queries like duplicate MACs (in seconds)
     */
    private const EXPENSIVE_QUERY_CACHE_TTL = 1800; // 30 minutes

    /**
     * Reports index - show available reports
     * Uses caching to improve performance - summary counts are cached for 5 minutes
     */
    public function index()
    {
        // Get summary counts from cache (or compute and cache)
        $summaries = Cache::remember('reports.summaries', self::SUMMARY_CACHE_TTL, function () {
            return [
                'offline_devices' => Device::where('online', false)->count(),
                'inactive_30_days' => Device::where('last_inform', '<', now()->subDays(30))->count(),
                'no_subscriber' => Device::whereNull('subscriber_id')->count(),
                'duplicate_serials' => $this->getDuplicateSerialCount(),
                'duplicate_macs' => $this->getDuplicateMacCount(),
                'excessive_informs' => $this->getExcessiveInformsCount(),
                'smartrg_on_non_dsl' => $this->getSmartrgOnNonDslCount(),
                'total_devices' => Device::count(),
                'online_devices' => Device::where('online', true)->count(),
                'cached_at' => now()->toIso8601String(),
            ];
        });

        return view('reports.index', compact('summaries'));
    }

    /**
     * Refresh the reports cache and redirect back
     */
    public function refresh()
    {
        Cache::forget('reports.summaries');
        Cache::forget('reports.duplicate_macs_count');
        Cache::forget('reports.excessive_informs_count');
        return redirect()->route('reports.index');
    }

    /**
     * Offline Devices Report
     */
    public function offlineDevices(Request $request)
    {
        $query = Device::where('online', false)
            ->with('subscriber')
            ->orderBy('last_inform', 'desc');

        // Filter by hours offline
        if ($request->has('hours') && $request->hours) {
            $cutoff = now()->subHours((int) $request->hours);
            $query->where('last_inform', '<', $cutoff);
        }

        $devices = $query->paginate(50);

        // Add time offline calculation
        $devices->getCollection()->transform(function ($device) {
            $device->time_offline = $device->last_inform
                ? $device->last_inform->diffForHumans()
                : 'Never connected';
            $device->hours_offline = $device->last_inform
                ? $device->last_inform->diffInHours(now())
                : null;
            return $device;
        });

        return view('reports.offline-devices', compact('devices'));
    }

    /**
     * Inactive Devices Report (30+ days)
     */
    public function inactiveDevices(Request $request)
    {
        $days = $request->get('days', 30);
        $cutoff = now()->subDays($days);

        $devices = Device::where('last_inform', '<', $cutoff)
            ->with('subscriber')
            ->orderBy('last_inform', 'asc')
            ->paginate(50);

        // Add days inactive calculation
        $devices->getCollection()->transform(function ($device) {
            $device->days_inactive = $device->last_inform
                ? $device->last_inform->diffInDays(now())
                : null;
            return $device;
        });

        return view('reports.inactive-devices', [
            'devices' => $devices,
            'days' => $days,
        ]);
    }

    /**
     * Devices Without Subscriber Report
     */
    public function devicesWithoutSubscriber(Request $request)
    {
        $devices = Device::whereNull('subscriber_id')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('reports.devices-without-subscriber', compact('devices'));
    }

    /**
     * Duplicate Serial Numbers Report
     */
    public function duplicateSerials(Request $request)
    {
        $duplicates = DB::table('devices')
            ->select('serial_number', DB::raw('COUNT(*) as count'))
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->groupBy('serial_number')
            ->having('count', '>', 1)
            ->orderByDesc('count')
            ->get();

        // Get full device records for each duplicate
        $duplicateDevices = [];
        foreach ($duplicates as $dup) {
            $duplicateDevices[$dup->serial_number] = Device::where('serial_number', $dup->serial_number)
                ->with('subscriber')
                ->orderBy('last_inform', 'desc')
                ->get();
        }

        return view('reports.duplicate-serials', [
            'duplicates' => $duplicates,
            'duplicateDevices' => $duplicateDevices,
        ]);
    }

    /**
     * Duplicate MAC Addresses Report
     * Uses fulltext search for performance (15x faster than LIKE %...%)
     */
    public function duplicateMacs(Request $request)
    {
        // Get MAC addresses from parameters table (WAN MAC)
        // Using fulltext search for better performance
        $duplicates = DB::table('parameters')
            ->select('value', DB::raw('COUNT(DISTINCT device_id) as count'))
            ->whereRaw("MATCH(name) AGAINST('+MACAddress +WAN*' IN BOOLEAN MODE)")
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->where('value', '!=', '00:00:00:00:00:00')
            ->groupBy('value')
            ->having('count', '>', 1)
            ->orderByDesc('count')
            ->get();

        // Get devices for each duplicate MAC
        $duplicateDevices = [];
        foreach ($duplicates as $dup) {
            $deviceIds = DB::table('parameters')
                ->whereRaw("MATCH(name) AGAINST('+MACAddress +WAN*' IN BOOLEAN MODE)")
                ->where('value', $dup->value)
                ->pluck('device_id')
                ->unique();

            $duplicateDevices[$dup->value] = Device::whereIn('id', $deviceIds)
                ->with('subscriber')
                ->orderBy('last_inform', 'desc')
                ->get();
        }

        return view('reports.duplicate-macs', [
            'duplicates' => $duplicates,
            'duplicateDevices' => $duplicateDevices,
        ]);
    }

    /**
     * Excessive Device Informs Report
     */
    public function excessiveInforms(Request $request)
    {
        $hours = $request->get('hours', 24);
        $threshold = $request->get('threshold', 50); // More than 50 informs is excessive

        $cutoff = now()->subHours($hours);

        // Count sessions per device in the time period
        $excessiveDevices = DB::table('cwmp_sessions')
            ->select('device_id', DB::raw('COUNT(*) as session_count'))
            ->where('created_at', '>=', $cutoff)
            ->groupBy('device_id')
            ->having('session_count', '>', $threshold)
            ->orderByDesc('session_count')
            ->get();

        // Get device details
        $deviceIds = $excessiveDevices->pluck('device_id');
        $devices = Device::whereIn('id', $deviceIds)
            ->with('subscriber')
            ->get()
            ->keyBy('id');

        // Merge session counts with device data
        $results = $excessiveDevices->map(function ($item) use ($devices, $hours) {
            $device = $devices->get($item->device_id);
            if ($device) {
                $device->session_count = $item->session_count;
                $device->informs_per_hour = round($item->session_count / $hours, 1);
                return $device;
            }
            return null;
        })->filter();

        return view('reports.excessive-informs', [
            'devices' => $results,
            'hours' => $hours,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Device Firmware Report
     */
    public function firmwareReport(Request $request)
    {
        $firmware = Device::select('manufacturer', 'product_class', 'software_version', DB::raw('COUNT(*) as count'))
            ->whereNotNull('software_version')
            ->groupBy('manufacturer', 'product_class', 'software_version')
            ->orderBy('manufacturer')
            ->orderBy('product_class')
            ->orderByDesc('count')
            ->get();

        // Group by manufacturer/product_class for easier display
        $grouped = $firmware->groupBy(function ($item) {
            return $item->manufacturer . ' - ' . $item->product_class;
        });

        return view('reports.firmware', [
            'firmware' => $firmware,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Device Type Report
     */
    public function deviceTypeReport(Request $request)
    {
        $types = Device::select('manufacturer', 'product_class', 'model_name', DB::raw('COUNT(*) as count'))
            ->groupBy('manufacturer', 'product_class', 'model_name')
            ->orderByDesc('count')
            ->get();

        $byManufacturer = Device::select('manufacturer', DB::raw('COUNT(*) as count'))
            ->groupBy('manufacturer')
            ->orderByDesc('count')
            ->get();

        return view('reports.device-types', [
            'types' => $types,
            'byManufacturer' => $byManufacturer,
        ]);
    }

    /**
     * All Devices Export (CSV)
     */
    public function exportAllDevices(Request $request)
    {
        $filename = 'all-devices-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Device ID',
                'Serial Number',
                'Manufacturer',
                'Product Class',
                'Model Name',
                'Hardware Version',
                'Software Version',
                'IP Address',
                'Online',
                'Last Inform',
                'Created At',
                'Subscriber ID',
                'Subscriber Name',
                'Connection Request URL',
                'STUN Enabled',
            ]);

            // Stream devices in chunks
            Device::with('subscriber')
                ->orderBy('manufacturer')
                ->orderBy('serial_number')
                ->chunk(500, function ($devices) use ($file) {
                    foreach ($devices as $device) {
                        fputcsv($file, [
                            $device->id,
                            $device->serial_number,
                            $device->manufacturer,
                            $device->product_class,
                            $device->model_name,
                            $device->hardware_version,
                            $device->software_version,
                            $device->ip_address,
                            $device->online ? 'Yes' : 'No',
                            $device->last_inform ? $device->last_inform->toIso8601String() : '',
                            $device->created_at->toIso8601String(),
                            $device->subscriber_id,
                            $device->subscriber?->name ?? '',
                            $device->connection_request_url,
                            $device->stun_enabled ? 'Yes' : 'No',
                        ]);
                    }
                });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Wake offline devices (send connection request)
     */
    public function wakeDevice(Request $request, $id)
    {
        $device = Device::findOrFail($id);

        if (!$device->connection_request_url) {
            return response()->json([
                'success' => false,
                'message' => 'Device has no connection request URL configured',
            ], 400);
        }

        try {
            // Send HTTP connection request
            $response = Http::withBasicAuth(
                $device->connection_request_username ?? 'admin',
                $device->connection_request_password ?? 'admin'
            )
            ->withOptions([
                'verify' => false,
                'timeout' => 10,
                'connect_timeout' => 5,
            ])
            ->get($device->connection_request_url);

            $success = $response->successful() || $response->status() === 401; // 401 is acceptable for some devices

            Log::info("Wake device {$device->serial_number}: HTTP {$response->status()}");

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? 'Connection request sent successfully'
                    : "Connection request failed: HTTP {$response->status()}",
                'status_code' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error("Wake device {$device->serial_number} failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Connection request failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk wake devices
     */
    public function bulkWake(Request $request)
    {
        $deviceIds = $request->input('device_ids', []);

        if (empty($deviceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No devices selected',
            ], 400);
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $devices = Device::whereIn('id', $deviceIds)->get();

        foreach ($devices as $device) {
            if (!$device->connection_request_url) {
                $results['failed']++;
                $results['errors'][] = "{$device->serial_number}: No CR URL";
                continue;
            }

            try {
                $response = Http::withBasicAuth(
                    $device->connection_request_username ?? 'admin',
                    $device->connection_request_password ?? 'admin'
                )
                ->withOptions([
                    'verify' => false,
                    'timeout' => 5,
                    'connect_timeout' => 3,
                ])
                ->get($device->connection_request_url);

                if ($response->successful() || $response->status() === 401) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "{$device->serial_number}: HTTP {$response->status()}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "{$device->serial_number}: " . substr($e->getMessage(), 0, 50);
            }

            // Small delay between requests
            usleep(100000); // 100ms
        }

        return response()->json([
            'success' => true,
            'message' => "Woke {$results['success']} devices, {$results['failed']} failed",
            'results' => $results,
        ]);
    }

    /**
     * Connection Request Failures Report
     */
    public function connectionRequestFailures(Request $request)
    {
        // Find devices where we've tried to send CR but they're still offline
        // or devices that have issues with their CR configuration
        $devices = Device::where('online', false)
            ->whereNotNull('connection_request_url')
            ->with('subscriber')
            ->orderBy('last_inform', 'asc')
            ->paginate(50);

        // Add diagnosis for each device
        $devices->getCollection()->transform(function ($device) {
            $issues = [];

            if (empty($device->connection_request_url)) {
                $issues[] = 'No CR URL';
            }

            if (empty($device->connection_request_username) || empty($device->connection_request_password)) {
                $issues[] = 'Missing CR credentials';
            }

            // Check if URL looks like NAT/private IP
            if (preg_match('/^https?:\/\/(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $device->connection_request_url)) {
                $issues[] = 'Private IP (likely NAT)';
            }

            $device->issues = $issues;
            $device->issue_count = count($issues);

            return $device;
        });

        return view('reports.connection-request-failures', compact('devices'));
    }

    /**
     * STUN Enabled Devices Report
     */
    public function stunDevices(Request $request)
    {
        $devices = Device::where('stun_enabled', true)
            ->with('subscriber')
            ->orderBy('manufacturer')
            ->orderBy('serial_number')
            ->paginate(50);

        return view('reports.stun-devices', compact('devices'));
    }

    /**
     * NAT-ed Devices Report (devices with private IP in CR URL)
     */
    public function natDevices(Request $request)
    {
        $devices = Device::whereNotNull('connection_request_url')
            ->where(function ($query) {
                $query->where('connection_request_url', 'LIKE', '%://192.168.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://10.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.16.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.17.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.18.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.19.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.2%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.30.%')
                    ->orWhere('connection_request_url', 'LIKE', '%://172.31.%');
            })
            ->with('subscriber')
            ->orderBy('manufacturer')
            ->paginate(50);

        return view('reports.nat-devices', compact('devices'));
    }

    /**
     * Helper: Get duplicate serial count
     */
    private function getDuplicateSerialCount(): int
    {
        return DB::table('devices')
            ->select('serial_number')
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->groupBy('serial_number')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }

    /**
     * Helper: Get duplicate MAC count
     * Uses fulltext search for performance and separate longer cache (30 min)
     * since duplicate MACs don't change frequently
     */
    private function getDuplicateMacCount(): int
    {
        return Cache::remember('reports.duplicate_macs_count', self::EXPENSIVE_QUERY_CACHE_TTL, function () {
            // Use fulltext search (15x faster than LIKE %...%)
            // The parameters_name_fulltext index makes this query ~3s instead of 45s
            return DB::table('parameters')
                ->select('value')
                ->whereRaw("MATCH(name) AGAINST('+MACAddress +WAN*' IN BOOLEAN MODE)")
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->where('value', '!=', '00:00:00:00:00:00')
                ->groupBy('value')
                ->havingRaw('COUNT(DISTINCT device_id) > 1')
                ->count();
        });
    }

    /**
     * Bulk Set Inform Interval for devices
     */
    public function bulkSetInformInterval(Request $request)
    {
        $deviceIds = $request->input('device_ids', []);
        $interval = $request->input('interval');

        if (empty($deviceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No devices selected',
            ], 400);
        }

        if (!$interval || !is_numeric($interval) || $interval < 60) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interval value (minimum 60 seconds)',
            ], 400);
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $devices = Device::whereIn('id', $deviceIds)->get();

        foreach ($devices as $device) {
            try {
                // Determine the correct parameter path based on data model
                $parameterPath = $this->getPeriodicInformIntervalPath($device);

                // Create set_params task with proper XSD type
                // PeriodicInformInterval must be xsd:unsignedInt, not xsd:string
                $task = Task::create([
                    'device_id' => $device->id,
                    'task_type' => 'set_params',
                    'status' => 'pending',
                    'parameters' => [
                        $parameterPath => [
                            'value' => (string) $interval,
                            'type' => 'xsd:unsignedInt',
                        ],
                    ],
                ]);

                Log::info("Created inform interval task {$task->id} for device {$device->serial_number}: {$parameterPath} = {$interval}");
                $results['success']++;

            } catch (\Exception $e) {
                Log::error("Failed to create inform interval task for {$device->serial_number}: " . $e->getMessage());
                $results['failed']++;
                $results['errors'][] = "{$device->serial_number}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$results['success']} tasks, {$results['failed']} failed",
            'results' => $results,
        ]);
    }

    /**
     * Helper: Get the correct PeriodicInformInterval parameter path based on device data model
     */
    private function getPeriodicInformIntervalPath(Device $device): string
    {
        // Check the device's data model root
        // TR-181 uses Device. prefix, TR-098 uses InternetGatewayDevice. prefix
        $dataModelRoot = $device->data_model_root ?? 'InternetGatewayDevice';

        if (str_starts_with($dataModelRoot, 'Device')) {
            // TR-181 data model
            return 'Device.ManagementServer.PeriodicInformInterval';
        } else {
            // TR-098 data model (default)
            return 'InternetGatewayDevice.ManagementServer.PeriodicInformInterval';
        }
    }

    /**
     * Helper: Get excessive informs count (last 24h)
     * Cached for 30 minutes since this is expensive and doesn't need real-time accuracy
     */
    private function getExcessiveInformsCount(): int
    {
        return Cache::remember('reports.excessive_informs_count', self::EXPENSIVE_QUERY_CACHE_TTL, function () {
            $cutoff = now()->subHours(24);

            return DB::table('cwmp_sessions')
                ->select('device_id')
                ->where('created_at', '>=', $cutoff)
                ->groupBy('device_id')
                ->havingRaw('COUNT(*) > 50')
                ->count();
        });
    }

    /**
     * SmartRG on Non-DSL Report - Finds SmartRG devices used by Fibre/Cable customers
     * SmartRGs are DSL routers and shouldn't be used for Fibre/Cable connections
     */
    public function smartrgOnNonDsl(Request $request)
    {
        // Service types that are NOT appropriate for SmartRG devices
        $nonDslServiceTypes = [
            'Internet Fibre',
            'Internet XGS Fibre',
            'Cable Internet',
            'Internet G.hn',
            'Internet Fixed Wireless',
        ];

        // Find SmartRG devices linked to subscribers with non-DSL service types
        $devices = Device::whereHas('subscriber', function ($query) use ($nonDslServiceTypes) {
                $query->whereIn('service_type', $nonDslServiceTypes);
            })
            ->where(function ($query) {
                // Match SmartRG or Sagemcom manufacturers
                $query->where('manufacturer', 'LIKE', '%SmartRG%')
                    ->orWhere('manufacturer', 'LIKE', '%Sagemcom%');
            })
            ->with('subscriber')
            ->orderBy('manufacturer')
            ->orderBy('product_class')
            ->get();

        // Group by service type for summary
        $byServiceType = $devices->groupBy(function ($device) {
            return $device->subscriber->service_type ?? 'Unknown';
        })->map->count();

        // Group by device model
        $byModel = $devices->groupBy('product_class')->map->count();

        return view('reports.smartrg-on-non-dsl', [
            'devices' => $devices,
            'byServiceType' => $byServiceType,
            'byModel' => $byModel,
            'nonDslServiceTypes' => $nonDslServiceTypes,
        ]);
    }

    /**
     * Helper: Get count of SmartRG devices on non-DSL connections
     */
    private function getSmartrgOnNonDslCount(): int
    {
        $nonDslServiceTypes = [
            'Internet Fibre',
            'Internet XGS Fibre',
            'Cable Internet',
            'Internet G.hn',
            'Internet Fixed Wireless',
        ];

        return Device::whereHas('subscriber', function ($query) use ($nonDslServiceTypes) {
                $query->whereIn('service_type', $nonDslServiceTypes);
            })
            ->where(function ($query) {
                $query->where('manufacturer', 'LIKE', '%SmartRG%')
                    ->orWhere('manufacturer', 'LIKE', '%Sagemcom%');
            })
            ->count();
    }
}
