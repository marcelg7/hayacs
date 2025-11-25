<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index(): View
    {
        $stats = [
            'total_devices' => Device::count(),
            'online_devices' => Device::where('online', true)->count(),
            'offline_devices' => Device::where('online', false)->count(),
            'pending_tasks' => Task::where('status', 'pending')->count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'failed_tasks' => Task::where('status', 'failed')->count(),
        ];

        $recentDevices = Device::orderBy('last_inform', 'desc')
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
    public function devices(): View
    {
        $devices = Device::orderBy('last_inform', 'desc')->paginate(20);

        return view('dashboard.devices', compact('devices'));
    }

    /**
     * Show device details
     */
    public function device(string $id): View
    {
        $device = Device::findOrFail($id);

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

        return view('dashboard.device', compact('device', 'parameters', 'tasks', 'sessions'));
    }
}
