<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceHealthSnapshot;
use App\Models\ParameterHistory;
use App\Models\SpeedTestResult;
use App\Models\Task;
use App\Models\TaskMetric;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display the analytics dashboard
     */
    public function index(): View
    {
        return view('analytics.index');
    }

    /**
     * Get device health trends
     */
    public function deviceHealth(Request $request): JsonResponse
    {
        $range = $request->input('range', '24h');
        $deviceId = $request->input('device_id');

        $period = $this->getPeriodFromRange($range);

        $query = DeviceHealthSnapshot::query()
            ->where('snapshot_at', '>=', $period['start'])
            ->orderBy('snapshot_at', 'asc');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $snapshots = $query->get();

        // Calculate uptime percentage over the period
        $totalSnapshots = $snapshots->count();
        $onlineSnapshots = $snapshots->where('is_online', true)->count();
        $uptimePercent = $totalSnapshots > 0 ? round(($onlineSnapshots / $totalSnapshots) * 100, 2) : 0;

        // Group by time intervals for charting
        $interval = $this->getIntervalFromRange($range);
        $chartData = $snapshots->groupBy(function ($snapshot) use ($interval) {
            return $snapshot->snapshot_at->format($interval);
        })->map(function ($group) {
            return [
                'timestamp' => $group->first()->snapshot_at->toIso8601String(),
                'online_count' => $group->where('is_online', true)->count(),
                'offline_count' => $group->where('is_online', false)->count(),
                'avg_uptime_seconds' => round($group->avg('connection_uptime_seconds')),
                'avg_inform_interval' => round($group->avg('inform_interval')),
            ];
        })->values();

        return response()->json([
            'range' => $range,
            'period' => $period,
            'uptime_percent' => $uptimePercent,
            'total_snapshots' => $totalSnapshots,
            'online_snapshots' => $onlineSnapshots,
            'offline_snapshots' => $totalSnapshots - $onlineSnapshots,
            'chart_data' => $chartData,
        ]);
    }

    /**
     * Get task performance analytics
     */
    public function taskPerformance(Request $request): JsonResponse
    {
        $range = $request->input('range', '24h');
        $deviceId = $request->input('device_id');
        $taskType = $request->input('task_type');

        $period = $this->getPeriodFromRange($range);

        // Base query builder
        $baseQuery = Task::query()
            ->where('created_at', '>=', $period['start']);

        if ($deviceId) {
            $baseQuery->where('device_id', $deviceId);
        }

        if ($taskType) {
            $baseQuery->where('task_type', $taskType);
        }

        // Overall statistics using database aggregates (memory efficient)
        $stats = (clone $baseQuery)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            ])
            ->first();

        $totalTasks = $stats->total ?? 0;
        $successfulTasks = $stats->successful ?? 0;
        $failedTasks = $stats->failed ?? 0;
        $successRate = $totalTasks > 0 ? round(($successfulTasks / $totalTasks) * 100, 2) : 0;

        // Task type breakdown using database aggregates
        $taskTypeBreakdown = (clone $baseQuery)
            ->select([
                'task_type',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            ])
            ->groupBy('task_type')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                $total = $item->total;
                $successful = $item->successful;
                return [
                    'task_type' => $item->task_type,
                    'total' => $total,
                    'successful' => $successful,
                    'failed' => $item->failed,
                    'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                ];
            });

        // Most common errors (limited query to reduce memory)
        $commonErrors = (clone $baseQuery)
            ->where('status', 'failed')
            ->whereNotNull('result')
            ->select('result')
            ->limit(500)
            ->get()
            ->filter(function ($task) {
                $result = is_string($task->result) ? json_decode($task->result, true) : $task->result;
                return isset($result['error']);
            })
            ->groupBy(function ($task) {
                $result = is_string($task->result) ? json_decode($task->result, true) : $task->result;
                return $result['error'] ?? 'Unknown error';
            })
            ->map(function ($group, $error) {
                return [
                    'error' => $error,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(10);

        // Chart data - tasks over time using database aggregates
        $interval = $this->getIntervalFromRange($range);
        $dateFormat = match ($range) {
            '24h', '7d' => '%Y-%m-%d %H:00:00',
            '30d', '90d' => '%Y-%m-%d',
            '1y' => '%Y-%m-01',
            default => '%Y-%m-%d %H:00:00',
        };

        $chartData = (clone $baseQuery)
            ->select([
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as time_bucket"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            ])
            ->groupBy('time_bucket')
            ->orderBy('time_bucket')
            ->get()
            ->map(function ($item) {
                return [
                    'timestamp' => Carbon::parse($item->time_bucket)->toIso8601String(),
                    'total' => $item->total,
                    'successful' => $item->successful,
                    'failed' => $item->failed,
                ];
            });

        return response()->json([
            'range' => $range,
            'period' => $period,
            'total_tasks' => $totalTasks,
            'successful_tasks' => $successfulTasks,
            'failed_tasks' => $failedTasks,
            'success_rate' => $successRate,
            'task_type_breakdown' => $taskTypeBreakdown,
            'common_errors' => $commonErrors,
            'chart_data' => $chartData,
        ]);
    }

    /**
     * Get parameter trending data
     */
    public function parameterTrending(Request $request): JsonResponse
    {
        $range = $request->input('range', '7d');
        $deviceId = $request->input('device_id');
        $parameterName = $request->input('parameter_name');

        if (!$parameterName) {
            return response()->json(['error' => 'parameter_name is required'], 400);
        }

        $period = $this->getPeriodFromRange($range);

        $query = ParameterHistory::query()
            ->where('parameter_name', $parameterName)
            ->where('recorded_at', '>=', $period['start'])
            ->orderBy('recorded_at', 'asc');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $history = $query->get();

        // Chart data
        $chartData = $history->map(function ($record) {
            return [
                'timestamp' => $record->recorded_at->toIso8601String(),
                'value' => $record->parameter_value,
                'device_id' => $record->device_id,
            ];
        });

        // Value distribution (for discrete values)
        $valueDistribution = $history->groupBy('parameter_value')
            ->map(function ($group, $value) {
                return [
                    'value' => $value,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(20);

        return response()->json([
            'range' => $range,
            'period' => $period,
            'parameter_name' => $parameterName,
            'total_records' => $history->count(),
            'chart_data' => $chartData,
            'value_distribution' => $valueDistribution,
        ]);
    }

    /**
     * Get fleet-wide analytics
     */
    public function fleetAnalytics(Request $request): JsonResponse
    {
        $range = $request->input('range', '24h');
        $period = $this->getPeriodFromRange($range);

        // Device statistics
        $totalDevices = Device::count();
        $onlineDevices = Device::where('online', true)->count();
        $offlineDevices = $totalDevices - $onlineDevices;

        // Firmware distribution
        $firmwareDistribution = Device::select('software_version', DB::raw('count(*) as count'))
            ->groupBy('software_version')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'version' => $item->software_version,
                    'count' => $item->count,
                ];
            });

        // Device type distribution
        $deviceTypeDistribution = Device::select('product_class', DB::raw('count(*) as count'))
            ->groupBy('product_class')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'product_class' => $item->product_class,
                    'display_name' => Device::getDisplayNameForProductClass($item->product_class),
                    'count' => $item->count,
                ];
            });

        // Manufacturer distribution
        $manufacturerDistribution = Device::select('manufacturer', DB::raw('count(*) as count'))
            ->groupBy('manufacturer')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'manufacturer' => $item->manufacturer,
                    'count' => $item->count,
                ];
            });

        // Recent task activity (fleet-wide)
        $recentTasks = Task::where('created_at', '>=', $period['start'])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            });

        return response()->json([
            'range' => $range,
            'period' => $period,
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'online_percentage' => $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100, 2) : 0,
            'firmware_distribution' => $firmwareDistribution,
            'device_type_distribution' => $deviceTypeDistribution,
            'manufacturer_distribution' => $manufacturerDistribution,
            'recent_task_activity' => $recentTasks,
        ]);
    }

    /**
     * Get SpeedTest results
     */
    public function speedTestResults(Request $request): JsonResponse
    {
        $range = $request->input('range', '30d');
        $deviceId = $request->input('device_id');

        $period = $this->getPeriodFromRange($range);

        $query = SpeedTestResult::query()
            ->where('download_state', 'Completed')
            ->where('created_at', '>=', $period['start'])
            ->orderBy('created_at', 'asc');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $results = $query->get();

        // Chart data
        $chartData = $results->map(function ($result) {
            return [
                'timestamp' => $result->created_at->toIso8601String(),
                'device_id' => $result->device_id,
                'download_mbps' => round($result->download_speed_mbps ?? 0, 2),
                'upload_mbps' => round($result->upload_speed_mbps ?? 0, 2),
                'latency_ms' => 0, // Not stored in current schema
            ];
        });

        // Statistics
        $avgDownload = round($results->avg('download_speed_mbps') ?? 0, 2);
        $avgUpload = round($results->avg('upload_speed_mbps') ?? 0, 2);

        return response()->json([
            'range' => $range,
            'period' => $period,
            'total_tests' => $results->count(),
            'avg_download_mbps' => $avgDownload,
            'avg_upload_mbps' => $avgUpload,
            'avg_latency_ms' => 0,
            'avg_jitter_ms' => 0,
            'chart_data' => $chartData,
        ]);
    }

    /**
     * Get list of parameters available for trending
     */
    public function getAvailableParameters(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id');

        $query = ParameterHistory::select('parameter_name', DB::raw('count(*) as record_count'))
            ->groupBy('parameter_name')
            ->orderBy('record_count', 'desc');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $parameters = $query->get()->map(function ($item) {
            return [
                'name' => $item->parameter_name,
                'record_count' => $item->record_count,
            ];
        });

        return response()->json([
            'parameters' => $parameters,
        ]);
    }

    /**
     * Convert range string to Carbon period
     */
    private function getPeriodFromRange(string $range): array
    {
        $end = now();
        $start = match ($range) {
            '24h' => $end->copy()->subHours(24),
            '7d' => $end->copy()->subDays(7),
            '30d' => $end->copy()->subDays(30),
            '90d' => $end->copy()->subDays(90),
            '1y' => $end->copy()->subYear(),
            default => $end->copy()->subHours(24),
        };

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Get time interval format string based on range
     */
    private function getIntervalFromRange(string $range): string
    {
        return match ($range) {
            '24h' => 'Y-m-d H:00', // Hourly
            '7d' => 'Y-m-d H:00', // Hourly
            '30d' => 'Y-m-d', // Daily
            '90d' => 'Y-m-d', // Daily
            '1y' => 'Y-m', // Monthly
            default => 'Y-m-d H:00',
        };
    }
}
