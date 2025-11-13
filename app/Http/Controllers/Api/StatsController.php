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
}
