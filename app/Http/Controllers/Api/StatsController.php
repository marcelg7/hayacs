<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'load1' => round($loadAvg[0], 2),
            'load5' => round($loadAvg[1], 2),
            'load15' => round($loadAvg[2], 2),
            'uptime' => $uptime,
            'tasks_pending' => $pendingTasks,
            'tasks_sent' => $sentTasks,
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
        ]);
    }
}
