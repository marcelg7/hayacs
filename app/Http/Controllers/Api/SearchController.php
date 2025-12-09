<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Parameter;
use App\Models\Subscriber;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Global search across devices, parameters, and more
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return response()->json([
                'results' => [],
                'total' => 0,
            ]);
        }

        $results = [];
        $parameterResults = null; // Store parameters to add last

        // Search Devices
        $devices = $this->searchDevices($query, $limit);
        if ($devices->isNotEmpty()) {
            $results[] = [
                'category' => 'Devices',
                'icon' => 'device',
                'items' => $devices->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'title' => $device->id,
                        'subtitle' => $this->getDeviceSubtitle($device),
                        'meta' => $device->online ? 'Online' : 'Offline',
                        'meta_class' => $device->online ? 'text-green-600' : 'text-red-600',
                        'url' => "/devices/{$device->id}",
                        'type' => 'device',
                    ];
                })->values(),
            ];
        }

        // Search by Serial Number (exact or partial)
        $serialDevices = $this->searchBySerialNumber($query, $limit);
        if ($serialDevices->isNotEmpty()) {
            // Filter out duplicates from device search
            $existingIds = $devices->pluck('id')->toArray();
            $newSerialDevices = $serialDevices->filter(fn($d) => !in_array($d->id, $existingIds));

            if ($newSerialDevices->isNotEmpty()) {
                $results[] = [
                    'category' => 'By Serial Number',
                    'icon' => 'serial',
                    'items' => $newSerialDevices->map(function ($device) {
                        $subtitle = $device->subscriber
                            ? "{$device->subscriber->name} | {$device->manufacturer} {$device->model_name}"
                            : "{$device->manufacturer} {$device->model_name}";
                        return [
                            'id' => $device->id,
                            'title' => $device->serial_number,
                            'subtitle' => $subtitle,
                            'meta' => $device->online ? 'Online' : 'Offline',
                            'meta_class' => $device->online ? 'text-green-600' : 'text-red-600',
                            'url' => "/devices/{$device->id}",
                            'type' => 'device',
                        ];
                    })->values(),
                ];
            }
        }

        // Search by IP Address
        $ipDevices = $this->searchByIpAddress($query, $limit);
        if ($ipDevices->isNotEmpty()) {
            $results[] = [
                'category' => 'By IP Address',
                'icon' => 'network',
                'items' => $ipDevices->map(function ($device) {
                    $subtitle = $device->subscriber
                        ? "{$device->subscriber->name} | {$device->manufacturer} {$device->model_name}"
                        : "{$device->manufacturer} {$device->model_name} ({$device->id})";
                    return [
                        'id' => $device->id,
                        'title' => $device->ip_address,
                        'subtitle' => $subtitle,
                        'meta' => $device->online ? 'Online' : 'Offline',
                        'meta_class' => $device->online ? 'text-green-600' : 'text-red-600',
                        'url' => "/devices/{$device->id}",
                        'type' => 'device',
                    ];
                })->values(),
            ];
        }

        // MAC address search disabled due to performance (requires scanning 800k+ parameters)
        // Users can search MAC addresses via the device's parameters tab instead
        // $macResults = $this->searchByMacAddress($query, $limit);

        // Search Subscribers
        $subscribers = $this->searchSubscribers($query, $limit);
        if ($subscribers->isNotEmpty()) {
            $results[] = [
                'category' => 'Subscribers',
                'icon' => 'subscriber',
                'items' => $subscribers->map(function ($subscriber) {
                    $deviceCount = $subscriber->devices_count ?? 0;
                    return [
                        'id' => $subscriber->id,
                        'title' => $subscriber->name,
                        'subtitle' => "Account: {$subscriber->account}" . ($subscriber->service_type ? " | {$subscriber->service_type}" : ''),
                        'meta' => $deviceCount > 0 ? "{$deviceCount} device" . ($deviceCount > 1 ? 's' : '') : 'No devices',
                        'meta_class' => $deviceCount > 0 ? 'text-green-600' : 'text-gray-500',
                        'url' => "/subscribers/{$subscriber->id}",
                        'type' => 'subscriber',
                    ];
                })->values(),
            ];
        }

        // Search Tasks (by ID, status, type, device)
        $tasks = $this->searchTasks($query, $limit);
        if ($tasks->isNotEmpty()) {
            $results[] = [
                'category' => 'Tasks',
                'icon' => 'task',
                'items' => $tasks->map(function ($task) {
                    $statusColors = [
                        'pending' => 'text-yellow-600',
                        'sent' => 'text-blue-600',
                        'completed' => 'text-green-600',
                        'failed' => 'text-red-600',
                        'cancelled' => 'text-gray-500',
                        'verifying' => 'text-purple-600',
                    ];
                    return [
                        'id' => $task->id,
                        'title' => "Task #{$task->id}",
                        'subtitle' => $task->getFriendlyDescription() . " | Device: {$task->device_id}",
                        'meta' => ucfirst($task->status),
                        'meta_class' => $statusColors[$task->status] ?? 'text-gray-500',
                        'url' => "/devices/{$task->device_id}?tab=tasks&task={$task->id}",
                        'type' => 'task',
                    ];
                })->values(),
            ];
        }

        // Search by Firmware Version
        $firmwareDevices = $this->searchByFirmware($query, $limit);
        if ($firmwareDevices->isNotEmpty()) {
            $results[] = [
                'category' => 'By Firmware',
                'icon' => 'firmware',
                'items' => $firmwareDevices->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'title' => $device->software_version ?? 'Unknown',
                        'subtitle' => "{$device->manufacturer} {$device->model_name} ({$device->serial_number})",
                        'meta' => $device->online ? 'Online' : 'Offline',
                        'meta_class' => $device->online ? 'text-green-600' : 'text-red-600',
                        'url' => "/devices/{$device->id}",
                        'type' => 'device',
                    ];
                })->values(),
            ];
        }

        // Search Parameters (name or value) - stored to add LAST
        $parameters = $this->searchParameters($query, $limit);
        if ($parameters->isNotEmpty()) {
            $parameterResults = [
                'category' => 'Parameters',
                'icon' => 'parameter',
                'items' => $parameters->map(function ($param) {
                    $shortName = $this->shortenParameterName($param->name);
                    return [
                        'id' => $param->id,
                        'title' => $shortName,
                        'subtitle' => $this->truncateValue($param->value),
                        'meta' => "Device: {$param->device_id}",
                        'meta_class' => 'text-gray-500',
                        'url' => "/devices/{$param->device_id}?tab=parameters&search=" . urlencode($param->name),
                        'type' => 'parameter',
                        'full_name' => $param->name,
                    ];
                })->values(),
            ];
        }

        // Add parameter results LAST (always at bottom)
        if ($parameterResults) {
            $results[] = $parameterResults;
        }

        // Count total results
        $total = collect($results)->sum(fn($cat) => count($cat['items']));

        return response()->json([
            'results' => $results,
            'total' => $total,
            'query' => $query,
        ]);
    }

    /**
     * Search devices by ID, manufacturer, model, etc.
     */
    private function searchDevices(string $query, int $limit)
    {
        // Also check for model aliases (e.g., GS4220E = GigaSpire)
        $modelAliases = [
            'GS4220E' => 'GigaSpire',
            'GS4220' => 'GigaSpire',
            'GIGASPIRE' => 'GigaSpire',
            'U6' => 'GigaSpire',
        ];

        $searchTerms = [$query];
        $upperQuery = strtoupper($query);
        if (isset($modelAliases[$upperQuery])) {
            $searchTerms[] = $modelAliases[$upperQuery];
        }

        // Check for product class display name mappings (e.g., "844E" -> "ENT", "854G" -> "ONT")
        $matchingProductClasses = Device::getProductClassesMatchingDisplayName($query);

        return Device::with('subscriber')
            ->where(function ($q) use ($searchTerms, $matchingProductClasses) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('id', 'LIKE', "%{$term}%")
                      ->orWhere('manufacturer', 'LIKE', "%{$term}%")
                      ->orWhere('model_name', 'LIKE', "%{$term}%")
                      ->orWhere('product_class', 'LIKE', "%{$term}%")
                      ->orWhere('oui', 'LIKE', "%{$term}%");
                }
                // Also search by mapped product_class values (e.g., search "844E" finds "ENT")
                if (!empty($matchingProductClasses)) {
                    $q->orWhereIn('product_class', $matchingProductClasses);
                }
            })
            ->orWhereHas('subscriber', function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('account', 'LIKE', "%{$query}%")
                  ->orWhere('customer', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('online')
            ->orderByDesc('last_inform')
            ->limit($limit)
            ->get();
    }

    /**
     * Search devices by serial number
     */
    private function searchBySerialNumber(string $query, int $limit)
    {
        return Device::with('subscriber')
            ->where('serial_number', 'LIKE', "%{$query}%")
            ->orderByDesc('online')
            ->orderByDesc('last_inform')
            ->limit($limit)
            ->get();
    }

    /**
     * Search devices by IP address
     */
    private function searchByIpAddress(string $query, int $limit)
    {
        // Only search if query looks like an IP
        if (!preg_match('/^[\d\.]+$/', $query)) {
            return collect();
        }

        return Device::with('subscriber')
            ->where('ip_address', 'LIKE', "%{$query}%")
            ->orderByDesc('online')
            ->orderByDesc('last_inform')
            ->limit($limit)
            ->get();
    }

    /**
     * Search for MAC addresses in parameters
     *
     * Note: MAC address search is inherently slow on large parameter tables.
     * We require at least 8 hex characters (4 octets) to ensure specificity.
     */
    private function searchByMacAddress(string $query, int $limit)
    {
        // Skip if this looks like an IP address (digits and dots only)
        if (preg_match('/^[\d\.]+$/', $query)) {
            return collect();
        }

        // Normalize MAC address search (remove colons/dashes)
        $normalizedQuery = strtoupper(preg_replace('/[:\-\.]/', '', $query));

        // Only search if it looks like a MAC address:
        // - Must be hex characters (0-9, A-F)
        // - Must contain at least one letter (A-F) to distinguish from IP addresses
        // - Must be at least 8 characters (4 octets) to ensure specificity and fast search
        //   (searching for short patterns like "AA:BB" is too slow on 800k+ parameters)
        if (!preg_match('/^[0-9A-F]{8,}$/', $normalizedQuery)) {
            return collect();
        }

        // Must contain at least one hex letter (A-F) to be considered a MAC address
        // This filters out IP-like queries that are pure digits
        if (!preg_match('/[A-F]/', $normalizedQuery)) {
            return collect();
        }

        return Parameter::where('name', 'LIKE', '%MACAddress%')
            ->where(function ($q) use ($query, $normalizedQuery) {
                $q->where('value', 'LIKE', "%{$query}%")
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(value), ':', ''), '-', ''), '.', '') LIKE ?", ["%{$normalizedQuery}%"]);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Search parameters by name or value
     * Uses fulltext search on name for fast parameter name lookup
     * Only searches for queries that look like TR-069 parameter names or specific values
     */
    private function searchParameters(string $query, int $limit)
    {
        // Only search parameters if the query looks like a TR-069 parameter
        // This avoids expensive searches for general terms like "Calix" or "completed"
        $looksLikeParameter = $this->looksLikeParameterSearch($query);

        if (!$looksLikeParameter) {
            return collect();
        }

        // Sanitize query for fulltext search - remove special characters except dots
        $fulltextQuery = preg_replace('/[^\w\s\.]/', '', $query);

        // Use fulltext search for parameter names (much faster than LIKE)
        return Parameter::select(['id', 'device_id', 'name', 'value'])
            ->where(function ($q) use ($fulltextQuery, $query) {
                // Fulltext search on name - handles partial word matches with *
                if (strlen($fulltextQuery) >= 3) {
                    $q->whereRaw(
                        "MATCH(name) AGAINST(? IN BOOLEAN MODE)",
                        ["*{$fulltextQuery}*"]
                    );
                } else {
                    // For very short queries, use LIKE with prefix on name (uses index)
                    $q->where('name', 'LIKE', "%{$query}%");
                }
            })
            // Also search exact value matches (uses prefix index)
            ->orWhere('value', $query)
            ->orderBy('device_id')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if a search query looks like a TR-069 parameter search
     * Returns true for queries that should search the parameters table
     */
    private function looksLikeParameterSearch(string $query): bool
    {
        $lowerQuery = strtolower($query);

        // Check for IP addresses FIRST before checking for dots
        // IP addresses should NOT trigger parameter search (searchByIpAddress handles them)
        if (preg_match('/^\d{1,3}\.\d{1,3}/', $query)) {
            return false;
        }

        // Check for MAC addresses EARLY - searchByMacAddress handles them more efficiently
        // MAC formats: AA:BB:CC, AA-BB-CC (hex pairs with separators)
        if (preg_match('/^[0-9a-f]{2}[:\-][0-9a-f]{2}/i', $query)) {
            return false;
        }

        // Contains a dot - likely a parameter path (after ruling out IPs)
        if (str_contains($query, '.')) {
            return true;
        }

        // Common TR-069 parameter keywords that users search for
        $parameterKeywords = [
            'ssid', 'wifi', 'wlan', 'lan', 'wan', 'dhcp', 'dns', 'ntp',
            'password', 'passphrase', 'key', 'psk',
            'gateway', 'subnet', 'mask',
            'enable', 'disable', 'status', 'state',
            'channel', 'frequency', 'bandwidth', 'radio',
            'port', 'forward', 'nat', 'mapping',
            'version', 'firmware', 'software', 'hardware',
            'uptime', 'memory', 'cpu', 'temp',
            'interval', 'timeout', 'url', 'server',
            'tr069', 'cwmp', 'acs', 'cpe',
            'internet', 'connection',
        ];

        // Check if query matches any parameter keyword
        foreach ($parameterKeywords as $keyword) {
            if (str_contains($lowerQuery, $keyword)) {
                return true;
            }
        }

        // All uppercase with underscores - vendor params like X_000631_
        // But exclude device serial number prefixes like CXNK, SAGEMCOM, etc.
        $serialPrefixes = ['CXNK', 'CP', 'S5', 'SR', '80AB', '0C7C'];
        $upperQuery = strtoupper($query);
        foreach ($serialPrefixes as $prefix) {
            if (str_starts_with($upperQuery, $prefix)) {
                return false;  // This is a serial number search, not parameter
            }
        }

        // Check for vendor parameter patterns (X_XXXXXX_ style)
        if (preg_match('/^X_[A-Z0-9]+_?/i', $query)) {
            return true;
        }

        // Skip general terms that aren't parameter-related
        return false;
    }

    /**
     * Search subscribers by name, account, or customer ID
     */
    private function searchSubscribers(string $query, int $limit)
    {
        return Subscriber::withCount('devices')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('account', 'LIKE', "%{$query}%")
                  ->orWhere('customer', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('devices_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a descriptive subtitle for a device
     */
    private function getDeviceSubtitle(Device $device): string
    {
        $parts = [];

        if ($device->subscriber) {
            $parts[] = $device->subscriber->name;
        }

        if ($device->manufacturer) {
            $parts[] = $device->manufacturer;
        }

        if ($device->model_name) {
            $parts[] = $device->model_name;
        }

        if ($device->serial_number && !$device->subscriber) {
            $parts[] = "S/N: {$device->serial_number}";
        }

        return implode(' | ', $parts) ?: 'Unknown Device';
    }

    /**
     * Shorten a long parameter name for display
     */
    private function shortenParameterName(string $name): string
    {
        // Remove common prefixes
        $name = preg_replace('/^(InternetGatewayDevice\.|Device\.)/', '', $name);

        // If still too long, show last 2-3 parts
        if (strlen($name) > 60) {
            $parts = explode('.', $name);
            if (count($parts) > 3) {
                $name = '...' . implode('.', array_slice($parts, -3));
            }
        }

        return $name;
    }

    /**
     * Truncate a value for display
     */
    private function truncateValue(?string $value): string
    {
        if (empty($value)) {
            return '(empty)';
        }

        if (strlen($value) > 50) {
            return substr($value, 0, 47) . '...';
        }

        return $value;
    }

    /**
     * Search tasks by ID, status, type, or device
     * Optimized to use indexes and avoid slow LIKE queries where possible
     */
    private function searchTasks(string $query, int $limit)
    {
        $lowerQuery = strtolower($query);

        // Map common status searches
        $statusAliases = [
            'pending' => 'pending',
            'sent' => 'sent',
            'running' => 'sent',
            'active' => 'sent',
            'completed' => 'completed',
            'done' => 'completed',
            'success' => 'completed',
            'failed' => 'failed',
            'error' => 'failed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'verifying' => 'verifying',
        ];

        // Map task type searches
        $taskTypeAliases = [
            'reboot' => 'reboot',
            'reset' => 'factory_reset',
            'factory' => 'factory_reset',
            'get' => 'get_params',
            'set' => 'set_parameter_values',
            'download' => 'download',
            'firmware' => 'download',
            'upload' => 'upload',
            'backup' => 'backup',
            'restore' => 'restore',
            'wifi' => 'wifi_scan',
            'scan' => 'wifi_scan',
            'speed' => ['download_diagnostics', 'upload_diagnostics'],
        ];

        // Check if this is a specific indexed search we can handle fast
        // 1. Numeric = task ID search (uses primary key - instant)
        if (is_numeric($query)) {
            return Task::with('device')
                ->where('id', $query)
                ->limit($limit)
                ->get();
        }

        // 2. Status alias search (uses status index - fast)
        if (isset($statusAliases[$lowerQuery])) {
            return Task::with('device')
                ->where('status', $statusAliases[$lowerQuery])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        // 3. Task type alias search (uses task_type index - fast)
        if (isset($taskTypeAliases[$lowerQuery])) {
            $types = is_array($taskTypeAliases[$lowerQuery])
                ? $taskTypeAliases[$lowerQuery]
                : [$taskTypeAliases[$lowerQuery]];
            return Task::with('device')
                ->whereIn('task_type', $types)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        // 4. Exact task type match (uses task_type index)
        $exactTaskTypes = ['reboot', 'factory_reset', 'get_params', 'set_parameter_values',
            'download', 'upload', 'backup', 'restore', 'wifi_scan', 'add_object', 'delete_object'];
        if (in_array($lowerQuery, $exactTaskTypes)) {
            return Task::with('device')
                ->where('task_type', $lowerQuery)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        // 5. For general text search, use fulltext on description (uses fulltext index)
        // Skip LIKE searches on device_id as they are too slow
        if (strlen($query) >= 3) {
            return Task::with('device')
                ->whereRaw("MATCH(description) AGAINST(? IN BOOLEAN MODE)", ["*{$query}*"])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        // For very short queries that don't match known patterns, return empty
        // This prevents slow table scans
        return collect();
    }

    /**
     * Search devices by firmware/software version
     */
    private function searchByFirmware(string $query, int $limit)
    {
        // Only search if query looks like a version number (contains numbers and dots)
        if (!preg_match('/[\d\.]/', $query)) {
            return collect();
        }

        return Device::where('software_version', 'LIKE', "%{$query}%")
            ->orderByDesc('online')
            ->orderByDesc('last_inform')
            ->limit($limit)
            ->get();
    }
}
