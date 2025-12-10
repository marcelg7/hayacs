<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    /**
     * Get ACS statistics
     */
    public function index(): JsonResponse
    {
        $totalDevices = Device::count();
        $onlineDevices = Device::where('online', true)->count();
        $offlineDevices = $totalDevices - $onlineDevices;
        $pendingTasks = Task::where('status', 'pending')->count();

        return response()->json([
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'pending_tasks' => $pendingTasks,
        ]);
    }

    /**
     * Get server status for admin status bar
     */
    public function serverStatus(): JsonResponse
    {
        // Get load averages
        $loadAvg = sys_getloadavg();

        // Get uptime
        $uptimeOutput = shell_exec('uptime -p') ?? 'unknown';
        $uptime = trim(str_replace('up ', '', $uptimeOutput));

        // Get pending tasks count
        $pendingTasks = Task::where('status', 'pending')->count();
        $sentTasks = Task::where('status', 'sent')->count();

        // Get total devices and online count
        $totalDevices = Device::count();
        $onlineDevices = Device::where('last_inform', '>', now()->subMinutes(15))->count();

        // Get disk usage for the main partition
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // Get MySQL status
        $mysqlStats = $this->getMySqlStats();

        // Get memory usage
        $memoryStats = $this->getMemoryStats();

        // Get queue jobs stats
        $queueStats = $this->getQueueStats();

        // Get cache stats
        $cacheStats = $this->getCacheStats();

        return response()->json([
            'load1' => round($loadAvg[0], 2),
            'load5' => round($loadAvg[1], 2),
            'load15' => round($loadAvg[2], 2),
            'uptime' => $uptime,
            'tasks_pending' => $pendingTasks,
            'tasks_sent' => $sentTasks,
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'disk_used_gb' => round($diskUsed / 1073741824, 1),
            'disk_total_gb' => round($diskTotal / 1073741824, 1),
            'disk_percent' => $diskPercent,
            'mysql_connections' => $mysqlStats['connections'],
            'mysql_threads' => $mysqlStats['threads'],
            'mysql_qps' => $mysqlStats['qps'],
            'memory_used_mb' => $memoryStats['used_mb'],
            'memory_total_mb' => $memoryStats['total_mb'],
            'memory_percent' => $memoryStats['percent'],
            'queue_pending' => $queueStats['pending'],
            'queue_failed' => $queueStats['failed'],
            'cache_entries' => $cacheStats['hits'],
            'cache_size_kb' => $cacheStats['misses'],
            'cache_driver' => $cacheStats['driver'] ?? 'unknown',
        ]);
    }

    /**
     * Get MySQL server statistics
     */
    private function getMySqlStats(): array
    {
        try {
            // Get current connections and thread count
            $processlist = \DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $threads = $processlist[0]->Value ?? 0;

            // Get max connections for context
            $maxConn = \DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $maxConnections = $maxConn[0]->Value ?? 151;

            // Get queries per second (approximate from uptime and questions)
            $questions = \DB::select("SHOW STATUS LIKE 'Questions'");
            $uptimeResult = \DB::select("SHOW STATUS LIKE 'Uptime'");

            $totalQuestions = $questions[0]->Value ?? 0;
            $mysqlUptime = $uptimeResult[0]->Value ?? 1;
            $qps = $mysqlUptime > 0 ? round($totalQuestions / $mysqlUptime, 1) : 0;

            return [
                'connections' => (int) $maxConnections,
                'threads' => (int) $threads,
                'qps' => $qps,
            ];
        } catch (\Exception $e) {
            return [
                'connections' => 0,
                'threads' => 0,
                'qps' => 0,
            ];
        }
    }

    /**
     * Get system memory statistics
     */
    private function getMemoryStats(): array
    {
        try {
            // Read from /proc/meminfo on Linux
            $meminfo = file_get_contents('/proc/meminfo');
            if ($meminfo === false) {
                return ['used_mb' => 0, 'total_mb' => 0, 'percent' => 0];
            }

            $lines = explode("\n", $meminfo);
            $mem = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                    $mem[$matches[1]] = (int) $matches[2];
                }
            }

            $totalKb = $mem['MemTotal'] ?? 0;
            $availableKb = $mem['MemAvailable'] ?? ($mem['MemFree'] ?? 0);
            $usedKb = $totalKb - $availableKb;

            $totalMb = round($totalKb / 1024);
            $usedMb = round($usedKb / 1024);
            $percent = $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0;

            return [
                'used_mb' => $usedMb,
                'total_mb' => $totalMb,
                'percent' => $percent,
            ];
        } catch (\Exception $e) {
            return ['used_mb' => 0, 'total_mb' => 0, 'percent' => 0];
        }
    }

    /**
     * Get queue job statistics
     */
    private function getQueueStats(): array
    {
        try {
            // Count pending jobs in the jobs table
            $pending = DB::table('jobs')->count();

            // Count failed jobs
            $failed = DB::table('failed_jobs')->count();

            return [
                'pending' => $pending,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            return ['pending' => 0, 'failed' => 0];
        }
    }

    /**
     * Get cache statistics
     * For database cache, shows entry count and size
     */
    private function getCacheStats(): array
    {
        try {
            $driver = config('cache.default');

            if ($driver === 'database') {
                // For database cache, count entries and estimate size
                $cacheTable = config('cache.stores.database.table', 'cache');
                $entries = DB::table($cacheTable)->count();

                // Get approximate size in KB
                $sizeResult = DB::select("SELECT SUM(LENGTH(value)) as size FROM {$cacheTable}");
                $sizeKb = isset($sizeResult[0]->size) ? round($sizeResult[0]->size / 1024, 1) : 0;

                return [
                    'hits' => $entries,
                    'misses' => $sizeKb,
                    'hit_rate' => $entries, // Repurpose as entry count for DB cache
                    'driver' => 'db',
                ];
            }

            // For other drivers, return basic info
            return [
                'hits' => 0,
                'misses' => 0,
                'hit_rate' => 0,
                'driver' => $driver,
            ];
        } catch (\Exception $e) {
            return ['hits' => 0, 'misses' => 0, 'hit_rate' => 0, 'driver' => 'unknown'];
        }
    }
}
