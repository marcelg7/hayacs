<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of all tasks with filtering.
     */
    public function index(Request $request)
    {
        $query = Task::with(['device:id,serial_number,manufacturer,product_class', 'initiator:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by task type
        if ($request->filled('type')) {
            $query->where('task_type', $request->type);
        }

        // Filter by initiator type (user or ACS)
        if ($request->filled('initiated_by')) {
            if ($request->initiated_by === 'user') {
                $query->whereNotNull('initiated_by_user_id');
            } elseif ($request->initiated_by === 'acs') {
                $query->whereNull('initiated_by_user_id');
            } elseif (is_numeric($request->initiated_by)) {
                $query->where('initiated_by_user_id', $request->initiated_by);
            }
        }

        // Filter by device serial
        if ($request->filled('serial')) {
            $query->whereHas('device', function ($q) use ($request) {
                $q->where('serial_number', 'like', '%' . $request->serial . '%');
            });
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $tasks = $query->paginate(50)->withQueryString();

        // Get statistics for the header
        $stats = [
            'total' => Task::count(),
            'pending' => Task::where('status', 'pending')->count(),
            'sent' => Task::where('status', 'sent')->count(),
            'completed' => Task::where('status', 'completed')->count(),
            'failed' => Task::where('status', 'failed')->count(),
            'cancelled' => Task::where('status', 'cancelled')->count(),
            'user_initiated' => Task::whereNotNull('initiated_by_user_id')->count(),
            'acs_initiated' => Task::whereNull('initiated_by_user_id')->count(),
        ];

        // Get task types for filter dropdown
        $taskTypes = Task::distinct()->pluck('task_type')->sort()->values();

        // Get users who have initiated tasks for filter dropdown
        $initiators = User::whereHas('initiatedTasks')->orderBy('name')->get(['id', 'name']);

        return view('admin.tasks.index', compact('tasks', 'stats', 'taskTypes', 'initiators'));
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task)
    {
        $task->load(['device', 'initiator']);

        return view('admin.tasks.show', compact('task'));
    }

    /**
     * Cancel a pending task.
     */
    public function cancel(Task $task)
    {
        if ($task->status !== 'pending') {
            return back()->with('error', 'Only pending tasks can be cancelled.');
        }

        $task->update(['status' => 'cancelled']);

        return back()->with('success', "Task #{$task->id} has been cancelled.");
    }

    /**
     * Bulk cancel pending tasks.
     */
    public function bulkCancel(Request $request)
    {
        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        $count = Task::whereIn('id', $request->task_ids)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return back()->with('success', "{$count} task(s) have been cancelled.");
    }
}
