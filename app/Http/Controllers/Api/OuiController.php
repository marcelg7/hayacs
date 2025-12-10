<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MacOuiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OuiController extends Controller
{
    /**
     * Look up OUI information for a MAC address
     */
    public function lookup(Request $request): JsonResponse
    {
        $mac = $request->get('mac', '');

        if (empty($mac)) {
            return response()->json([
                'error' => 'MAC address required',
            ], 400);
        }

        $service = new MacOuiService();
        $result = $service->lookup($mac);

        if ($result === null) {
            return response()->json([
                'found' => false,
                'mac' => $service->formatMac($mac),
                'vendor' => null,
                'prefix' => null,
            ]);
        }

        return response()->json([
            'found' => true,
            'mac' => $service->formatMac($mac),
            'vendor' => $result['vendor'],
            'prefix' => $result['prefix'],
            'address' => $result['address'] ?? null,
        ]);
    }

    /**
     * Bulk lookup for multiple MAC addresses
     */
    public function bulkLookup(Request $request): JsonResponse
    {
        $macs = $request->get('macs', []);

        if (empty($macs) || !is_array($macs)) {
            return response()->json([
                'error' => 'Array of MAC addresses required',
            ], 400);
        }

        // Limit to 100 lookups at once
        $macs = array_slice($macs, 0, 100);

        $service = new MacOuiService();
        $results = [];

        foreach ($macs as $mac) {
            $result = $service->lookup($mac);
            $results[$mac] = [
                'found' => $result !== null,
                'mac' => $service->formatMac($mac),
                'vendor' => $result['vendor'] ?? null,
                'prefix' => $result['prefix'] ?? null,
            ];
        }

        return response()->json([
            'count' => count($results),
            'results' => $results,
        ]);
    }
}
