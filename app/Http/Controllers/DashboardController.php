<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index(): View
    {
        // Calculate online/offline counts dynamically based on last_inform timestamp
        // A device is considered online if it informed within 2x its periodic inform interval (min 15 minutes)
        // Default interval is 10 minutes (600 seconds), so default grace period is 20 minutes
        $defaultGraceMinutes = 20;
        $onlineCutoff = now()->subMinutes($defaultGraceMinutes);

        $totalDevices = Device::count();
        $onlineDevices = Device::whereNotNull('last_inform')
            ->where('last_inform', '>=', $onlineCutoff)
            ->count();
        $offlineDevices = $totalDevices - $onlineDevices;

        $stats = [
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'pending_tasks' => Task::where('status', 'pending')->count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'failed_tasks' => Task::where('status', 'failed')->count(),
        ];

        $recentDevices = Device::with('subscriber')
            ->orderBy('last_inform', 'desc')
            ->limit(10)
            ->get();

        $recentTasks = Task::with('device')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('stats', 'recentDevices', 'recentTasks'));
    }

    /**
     * Show all devices
     */
    public function devices(Request $request): View
    {
        $query = Device::with('subscriber');

        // Apply search filter
        if ($search = $request->get('search')) {
            // Check if search matches any display name mappings (e.g., "844E" -> "ENT")
            $matchingProductClasses = Device::getProductClassesMatchingDisplayName($search);

            $query->where(function ($q) use ($search, $matchingProductClasses) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('product_class', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhereHas('subscriber', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('account', 'like', "%{$search}%");
                    });

                // Also search by mapped product_class values (e.g., search "844E" finds "ENT")
                if (!empty($matchingProductClasses)) {
                    $q->orWhereIn('product_class', $matchingProductClasses);
                }
            });
        }

        // Apply manufacturer filter
        if ($manufacturer = $request->get('manufacturer')) {
            $query->where('manufacturer', $manufacturer);
        }

        // Apply model filter
        if ($model = $request->get('model')) {
            $query->where('model_name', $model);
        }

        // Apply product class filter
        if ($productClass = $request->get('product_class')) {
            $query->where('product_class', $productClass);
        }

        // Apply status filter
        if ($status = $request->get('status')) {
            $graceMinutes = 20;
            $onlineCutoff = now()->subMinutes($graceMinutes);
            if ($status === 'online') {
                $query->whereNotNull('last_inform')
                    ->where('last_inform', '>=', $onlineCutoff);
            } elseif ($status === 'offline') {
                $query->where(function ($q) use ($onlineCutoff) {
                    $q->whereNull('last_inform')
                        ->orWhere('last_inform', '<', $onlineCutoff);
                });
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'last_inform');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['id', 'manufacturer', 'model_name', 'serial_number', 'last_inform', 'software_version'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('last_inform', 'desc');
        }

        $devices = $query->paginate(50)->withQueryString();

        // Get filter options
        $manufacturers = Device::select('manufacturer')
            ->distinct()
            ->whereNotNull('manufacturer')
            ->orderBy('manufacturer')
            ->pluck('manufacturer');

        $models = Device::select('model_name')
            ->distinct()
            ->whereNotNull('model_name')
            ->orderBy('model_name')
            ->pluck('model_name');

        // Get product classes with their best display names
        // For each product_class, we get the model_name if available (for SmartRG devices)
        $productClassData = Device::select('product_class', 'model_name')
            ->distinct()
            ->whereNotNull('product_class')
            ->orderBy('product_class')
            ->get();

        // Build a map of product_class => best_display_name
        $productClassDisplayMap = [];
        foreach ($productClassData as $row) {
            $pc = $row->product_class;
            if (!isset($productClassDisplayMap[$pc])) {
                // Use explicit mapping first, then model_name, then product_class
                if (isset(Device::PRODUCT_CLASS_DISPLAY_NAMES[$pc])) {
                    $productClassDisplayMap[$pc] = Device::PRODUCT_CLASS_DISPLAY_NAMES[$pc];
                } elseif (!empty($row->model_name)) {
                    $productClassDisplayMap[$pc] = $row->model_name;
                } else {
                    $productClassDisplayMap[$pc] = $pc;
                }
            }
        }
        // Sort by display name for better UX
        asort($productClassDisplayMap);

        return view('dashboard.devices', compact('devices', 'manufacturers', 'models', 'productClassDisplayMap'));
    }

    /**
     * Show device details
     */
    public function device(string $id): View
    {
        $device = Device::with('subscriber')->findOrFail($id);

        $parameters = $device->parameters()
            ->orderBy('name')
            ->paginate(50);

        $tasks = $device->tasks()
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $sessions = $device->sessions()
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get();

        $events = $device->events()
            ->orderBy('created_at', 'desc')
            ->paginate(50, ['*'], 'events_page');

        return view('dashboard.device', compact('device', 'parameters', 'tasks', 'sessions', 'events'));
    }

    /**
     * Export devices as CSV
     */
    public function exportDevices(Request $request): StreamedResponse
    {
        $query = Device::with('subscriber');

        // Apply the same filters as the devices list
        if ($search = $request->get('search')) {
            // Check if search matches any display name mappings (e.g., "844E" -> "ENT")
            $matchingProductClasses = Device::getProductClassesMatchingDisplayName($search);

            $query->where(function ($q) use ($search, $matchingProductClasses) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('product_class', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhereHas('subscriber', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('account', 'like', "%{$search}%");
                    });

                // Also search by mapped product_class values (e.g., search "844E" finds "ENT")
                if (!empty($matchingProductClasses)) {
                    $q->orWhereIn('product_class', $matchingProductClasses);
                }
            });
        }

        if ($manufacturer = $request->get('manufacturer')) {
            $query->where('manufacturer', $manufacturer);
        }

        if ($model = $request->get('model')) {
            $query->where('model_name', $model);
        }

        if ($productClass = $request->get('product_class')) {
            $query->where('product_class', $productClass);
        }

        if ($status = $request->get('status')) {
            $graceMinutes = 20;
            $onlineCutoff = now()->subMinutes($graceMinutes);
            if ($status === 'online') {
                $query->whereNotNull('last_inform')
                    ->where('last_inform', '>=', $onlineCutoff);
            } elseif ($status === 'offline') {
                $query->where(function ($q) use ($onlineCutoff) {
                    $q->whereNull('last_inform')
                        ->orWhere('last_inform', '<', $onlineCutoff);
                });
            }
        }

        $sortBy = $request->get('sort', 'last_inform');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['id', 'manufacturer', 'model_name', 'serial_number', 'last_inform', 'software_version'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('last_inform', 'desc');
        }

        $filename = 'devices-' . now()->format('Y-m-d-His') . '.csv';

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV header
            fputcsv($handle, [
                'Device ID',
                'Subscriber',
                'Subscriber Account',
                'Manufacturer',
                'Model',
                'Product Class',
                'Serial Number',
                'Software Version',
                'IP Address',
                'Status',
                'Last Inform',
                'Created At',
            ]);

            // Stream data in chunks
            $query->chunk(500, function ($devices) use ($handle) {
                $graceMinutes = 20;
                $onlineCutoff = now()->subMinutes($graceMinutes);

                foreach ($devices as $device) {
                    $isOnline = $device->last_inform && $device->last_inform >= $onlineCutoff;
                    fputcsv($handle, [
                        $device->id,
                        $device->subscriber?->name ?? '',
                        $device->subscriber?->account ?? '',
                        $device->manufacturer ?? '',
                        $device->model_name ?? '',
                        $device->product_class ?? '',
                        $device->serial_number ?? '',
                        $device->software_version ?? '',
                        $device->ip_address ?? '',
                        $isOnline ? 'Online' : 'Offline',
                        $device->last_inform?->format('Y-m-d H:i:s') ?? 'Never',
                        $device->created_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Quick device search - searches by partial serial number
     * If single match found, redirects directly to device WiFi tab
     * If multiple matches, shows search results
     */
    public function quickSearch(Request $request): View|RedirectResponse
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 3) {
            return redirect()->route('dashboard')
                ->with('error', 'Please enter at least 3 characters to search.');
        }

        // Search by serial number (case-insensitive partial match)
        $devices = Device::with('subscriber')
            ->where('serial_number', 'LIKE', "%{$query}%")
            ->orderByDesc('last_inform')
            ->limit(50)
            ->get();

        // If exactly one match, redirect directly to WiFi tab
        if ($devices->count() === 1) {
            return redirect()->route('device.show', [
                'id' => $devices->first()->id,
                'tab' => 'wifi'
            ]);
        }

        // If no matches by serial, also try device ID
        if ($devices->isEmpty()) {
            $devices = Device::with('subscriber')
                ->where('id', 'LIKE', "%{$query}%")
                ->orderByDesc('last_inform')
                ->limit(50)
                ->get();

            // If exactly one match by ID, redirect to WiFi tab
            if ($devices->count() === 1) {
                return redirect()->route('device.show', [
                    'id' => $devices->first()->id,
                    'tab' => 'wifi'
                ]);
            }
        }

        // Multiple matches or no matches - show results page
        return view('dashboard.quick-search-results', [
            'devices' => $devices,
            'query' => $query,
        ]);
    }
}
