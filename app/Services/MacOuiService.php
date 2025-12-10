<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MacOuiService
{
    /**
     * OUI data file path
     */
    protected string $ouiDataPath;

    /**
     * Cache duration in seconds (24 hours)
     */
    protected int $cacheDuration = 86400;

    public function __construct()
    {
        $this->ouiDataPath = storage_path('app/oui-data.json');
    }

    /**
     * Look up manufacturer info from MAC address
     *
     * @param string $mac MAC address in any format (with or without colons/dashes)
     * @return array|null Returns array with 'vendor', 'address', 'prefix' or null if not found
     */
    public function lookup(string $mac): ?array
    {
        // Normalize MAC address - extract first 6 hex characters (OUI prefix)
        $normalized = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if (strlen($normalized) < 6) {
            return null;
        }

        $oui = substr($normalized, 0, 6);

        // Try cache first
        $cacheKey = "oui:{$oui}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 'not_found' ? null : $cached;
        }

        // Look up in data file
        $result = $this->lookupInDataFile($oui);

        // Cache the result (including null results to avoid repeated lookups)
        Cache::put($cacheKey, $result ?? 'not_found', $this->cacheDuration);

        return $result;
    }

    /**
     * Look up OUI in the data file
     */
    protected function lookupInDataFile(string $oui): ?array
    {
        if (!file_exists($this->ouiDataPath)) {
            Log::warning('OUI data file not found', ['path' => $this->ouiDataPath]);
            return null;
        }

        // Load the JSON data
        $data = Cache::remember('oui:full_data', $this->cacheDuration, function () {
            $content = file_get_contents($this->ouiDataPath);
            return json_decode($content, true) ?? [];
        });

        if (isset($data[$oui])) {
            return [
                'vendor' => $data[$oui]['vendor'] ?? 'Unknown',
                'address' => $data[$oui]['address'] ?? null,
                'prefix' => $this->formatOuiPrefix($oui),
            ];
        }

        return null;
    }

    /**
     * Format OUI prefix for display (e.g., "00:11:22")
     */
    protected function formatOuiPrefix(string $oui): string
    {
        return implode(':', str_split($oui, 2));
    }

    /**
     * Format MAC address for display (e.g., "00:11:22:33:44:55")
     */
    public function formatMac(string $mac): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if (strlen($normalized) < 12) {
            // Pad with zeros if needed
            $normalized = str_pad($normalized, 12, '0', STR_PAD_RIGHT);
        }

        return implode(':', str_split(substr($normalized, 0, 12), 2));
    }

    /**
     * Check if OUI data file exists and has data
     */
    public function isDataAvailable(): bool
    {
        return file_exists($this->ouiDataPath) && filesize($this->ouiDataPath) > 100;
    }

    /**
     * Get the count of OUI entries in the data file
     */
    public function getEntryCount(): int
    {
        if (!$this->isDataAvailable()) {
            return 0;
        }

        $data = Cache::remember('oui:full_data', $this->cacheDuration, function () {
            $content = file_get_contents($this->ouiDataPath);
            return json_decode($content, true) ?? [];
        });

        return count($data);
    }

    /**
     * Update OUI data from IEEE (fetches from public IEEE OUI list)
     * This can be run as a scheduled task or manually via artisan command
     */
    public function updateFromIeee(): bool
    {
        try {
            // IEEE OUI list URL (CSV format)
            $url = 'https://standards-oui.ieee.org/oui/oui.csv';

            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'user_agent' => 'HayACS/1.0'
                ]
            ]);

            $csvContent = @file_get_contents($url, false, $context);

            if ($csvContent === false) {
                Log::error('Failed to download IEEE OUI data');
                return false;
            }

            // Parse CSV
            $lines = explode("\n", $csvContent);
            $data = [];
            $header = true;

            foreach ($lines as $line) {
                if ($header) {
                    $header = false;
                    continue;
                }

                // CSV format: Registry,Assignment,Organization Name,Organization Address
                $parts = str_getcsv($line);

                if (count($parts) >= 3 && !empty($parts[1])) {
                    $oui = strtoupper(trim($parts[1]));

                    // Only process MA-L (24-bit OUI) entries
                    if (strlen($oui) === 6 && ctype_xdigit($oui)) {
                        $data[$oui] = [
                            'vendor' => trim($parts[2] ?? 'Unknown'),
                            'address' => trim($parts[3] ?? ''),
                        ];
                    }
                }
            }

            if (count($data) < 1000) {
                Log::error('OUI data parsing returned too few entries', ['count' => count($data)]);
                return false;
            }

            // Write to data file
            $json = json_encode($data, JSON_PRETTY_PRINT);
            file_put_contents($this->ouiDataPath, $json);

            // Clear cache
            Cache::forget('oui:full_data');

            Log::info('OUI data updated successfully', ['entries' => count($data)]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update OUI data', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
