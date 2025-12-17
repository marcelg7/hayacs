<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmDailyActivityCache extends Command
{
    protected $signature = 'cache:warm-daily-activity {--date= : Specific date to warm (YYYY-MM-DD)}';
    protected $description = 'Pre-warm the daily activity report cache for faster page loads';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $reportDate = $dateStr ? Carbon::parse($dateStr) : Carbon::today();

        $this->info("Warming cache for {$reportDate->format('Y-m-d')}...");

        $cacheKey = 'daily_activity_report_' . $reportDate->format('Y-m-d');
        $startOfDay = $reportDate->copy()->startOfDay();
        $endOfDay = $reportDate->copy()->endOfDay();

        // Cache duration: 1 hour for past days, 5 minutes for today
        $isToday = $reportDate->isToday();
        $cacheDuration = $isToday ? now()->addMinutes(5) : now()->addHour();

        $startTime = microtime(true);

        // Task stats
        $taskStats = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                AVG(CASE WHEN status = "completed" AND sent_at IS NOT NULL AND completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, sent_at, completed_at) END) as avg_duration
            ')
            ->first();

        // Tasks by type
        $tasksByType = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('task_type', DB::raw('COUNT(*) as count'))
            ->groupBy('task_type')
            ->orderByDesc('count')
            ->pluck('count', 'task_type');

        // Tasks by user
        $userTaskStats = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select(
                'initiated_by_user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('COUNT(DISTINCT device_id) as devices')
            )
            ->groupBy('initiated_by_user_id')
            ->orderByDesc('total')
            ->get();

        $userIds = $userTaskStats->pluck('initiated_by_user_id')->filter()->unique();
        $users = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        $tasksByUser = $userTaskStats->map(function ($stat) use ($users) {
            return [
                'user_id' => $stat->initiated_by_user_id,
                'user_name' => $stat->initiated_by_user_id ? ($users[$stat->initiated_by_user_id] ?? 'Unknown') : 'System (ACS)',
                'total' => $stat->total,
                'completed' => $stat->completed,
                'failed' => $stat->failed,
                'pending' => $stat->pending,
                'devices' => $stat->devices,
            ];
        });

        // Tasks by device (top 20)
        $deviceTaskStats = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select(
                'device_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('device_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $deviceIds = $deviceTaskStats->pluck('device_id')->filter()->unique();
        $devices = Device::with('subscriber')->whereIn('id', $deviceIds)->get()->keyBy('id');

        $tasksByDevice = $deviceTaskStats->map(function ($stat) use ($devices) {
            $device = $devices[$stat->device_id] ?? null;
            return [
                'device_id' => $stat->device_id,
                'serial_number' => $device?->serial_number ?? 'Unknown',
                'subscriber' => $device?->subscriber?->name ?? 'N/A',
                'product_class' => $device?->product_class ?? 'Unknown',
                'total' => $stat->total,
                'completed' => $stat->completed,
                'failed' => $stat->failed,
            ];
        });

        // Failed tasks (limited)
        $failedTaskDetails = Task::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('status', 'failed')
            ->with(['device.subscriber', 'initiator'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'type' => $task->task_type,
                    'description' => $task->description,
                    'device_id' => $task->device_id,
                    'device_serial' => $task->device?->serial_number ?? 'Unknown',
                    'subscriber' => $task->device?->subscriber?->name ?? 'N/A',
                    'user' => $task->getInitiatorDisplayName(),
                    'error' => $this->extractErrorMessage($task->result),
                    'created_at' => $task->created_at,
                    'duration' => $task->sent_at && $task->completed_at
                        ? $task->completed_at->diffInSeconds($task->sent_at)
                        : null,
                ];
            });

        // Slowest tasks (limited)
        $slowestTasks = Task::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('status', 'completed')
            ->whereNotNull('sent_at')
            ->whereNotNull('completed_at')
            ->with(['device.subscriber', 'initiator'])
            ->orderByRaw('TIMESTAMPDIFF(SECOND, sent_at, completed_at) DESC')
            ->limit(10)
            ->get()
            ->map(function ($task) {
                $duration = $task->completed_at->diffInSeconds($task->sent_at);
                return [
                    'id' => $task->id,
                    'type' => $task->task_type,
                    'description' => $task->description,
                    'device_serial' => $task->device?->serial_number ?? 'Unknown',
                    'subscriber' => $task->device?->subscriber?->name ?? 'N/A',
                    'user' => $task->getInitiatorDisplayName(),
                    'duration_seconds' => $duration,
                    'created_at' => $task->created_at,
                ];
            });

        // Device events
        $eventStats = DB::table('device_events')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('
                COUNT(*) as total,
                COUNT(DISTINCT device_id) as unique_devices
            ')
            ->first();

        $eventsByType = DB::table('device_events')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        // Hourly breakdown
        $hourlyBreakdown = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('
                DATE_FORMAT(created_at, "%H:00") as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn($h) => [
                'total' => $h->total,
                'completed' => $h->completed,
                'failed' => $h->failed,
            ]);

        // Recent tasks
        $recentTasks = Task::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->with(['device', 'initiator'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        // Build cache data
        $data = [
            'tasks' => $recentTasks,
            'taskStats' => [
                'total' => $taskStats->total ?? 0,
                'completed' => $taskStats->completed ?? 0,
                'failed' => $taskStats->failed ?? 0,
                'pending' => $taskStats->pending ?? 0,
                'avg_duration' => $taskStats->avg_duration ? round($taskStats->avg_duration, 1) : null,
            ],
            'tasksByType' => $tasksByType,
            'tasksByUser' => $tasksByUser,
            'tasksByDevice' => $tasksByDevice,
            'failedTasks' => $failedTaskDetails,
            'slowestTasks' => $slowestTasks,
            'deviceEvents' => [
                'total' => $eventStats->total ?? 0,
                'by_type' => $eventsByType,
                'unique_devices' => $eventStats->unique_devices ?? 0,
            ],
            'hourlyBreakdown' => $hourlyBreakdown,
        ];

        // Store in cache
        Cache::put($cacheKey, $data, $cacheDuration);
        Cache::put($cacheKey . '_time', now(), $cacheDuration);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Cache warmed in {$elapsed}s - {$taskStats->total} tasks");

        return self::SUCCESS;
    }

    private function extractErrorMessage($result): ?string
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if ($decoded) {
                $result = $decoded;
            }
        }

        if (is_array($result)) {
            return $result['error'] ?? $result['message'] ?? $result['faultstring'] ?? json_encode($result);
        }

        return is_string($result) ? substr($result, 0, 100) : null;
    }
}
