<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Parameter;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Search by MAC Address (in parameters)
        $macResults = $this->searchByMacAddress($query, $limit);
        if ($macResults->isNotEmpty()) {
            $results[] = [
                'category' => 'By MAC Address',
                'icon' => 'mac',
                'items' => $macResults->map(function ($result) {
                    return [
                        'id' => $result->device_id,
                        'title' => $result->value,
                        'subtitle' => "Device: {$result->device_id}",
                        'meta' => $result->name,
                        'meta_class' => 'text-gray-500',
                        'url' => "/devices/{$result->device_id}",
                        'type' => 'device',
                    ];
                })->values(),
            ];
        }

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

        return Device::with('subscriber')
            ->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('id', 'LIKE', "%{$term}%")
                      ->orWhere('manufacturer', 'LIKE', "%{$term}%")
                      ->orWhere('model_name', 'LIKE', "%{$term}%")
                      ->orWhere('product_class', 'LIKE', "%{$term}%")
                      ->orWhere('oui', 'LIKE', "%{$term}%");
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
     */
    private function searchByMacAddress(string $query, int $limit)
    {
        // Normalize MAC address search (remove colons/dashes)
        $normalizedQuery = strtoupper(preg_replace('/[:\-\.]/', '', $query));

        // Only search if it looks like a MAC address (hex characters)
        if (!preg_match('/^[0-9A-F]{4,}$/', $normalizedQuery)) {
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
     * Falls back to LIKE for value searches (less common, but still needed)
     */
    private function searchParameters(string $query, int $limit)
    {
        // Sanitize query for fulltext search - remove special characters
        $fulltextQuery = preg_replace('/[^\w\s\.]/', '', $query);

        // Use fulltext search for parameter names (much faster than LIKE)
        // Also search by value using indexed prefix search
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
}
