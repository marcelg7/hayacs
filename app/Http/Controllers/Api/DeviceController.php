<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Display a listing of all devices
     */
    public function index(): JsonResponse
    {
        $devices = Device::orderBy('last_inform', 'desc')->get();

        return response()->json($devices);
    }

    /**
     * Display the specified device
     */
    public function show(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        return response()->json($device);
    }

    /**
     * Get all parameters for a device
     */
    public function parameters(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $parameters = $device->parameters()
            ->orderBy('name')
            ->get();

        return response()->json($parameters);
    }

    /**
     * Get all tasks for a device
     */
    public function tasks(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $tasks = $device->tasks()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Create a new task for a device
     */
    public function createTask(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|in:get_params,set_params,reboot,factory_reset',
            'parameters' => 'nullable|array',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => $validated['type'],
            'parameters' => $validated['parameters'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($task, 201);
    }

    /**
     * Reboot a device
     */
    public function reboot(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'reboot',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reboot task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Factory reset a device
     */
    public function factoryReset(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'factory_reset',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Factory reset task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Get specific parameters from a device
     */
    public function getParameters(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'names' => 'required|array',
            'names.*' => 'required|string',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'get_params',
            'parameters' => [
                'names' => $validated['names'],
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Get parameters task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Set parameters on a device
     */
    public function setParameters(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'values' => 'required|array',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'set_params',
            'parameters' => [
                'values' => $validated['values'],
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Set parameters task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Update device tags/metadata
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'tags' => 'nullable|array',
        ]);

        $device->update($validated);

        return response()->json($device);
    }

    /**
     * Firmware upgrade (Download RPC)
     */
    public function firmwareUpgrade(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'url' => 'required|url',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'download',
            'parameters' => [
                'url' => $validated['url'],
                'file_type' => '1 Firmware Upgrade Image',
                'username' => $validated['username'] ?? '',
                'password' => $validated['password'] ?? '',
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Firmware upgrade task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Upload logs/config from device
     */
    public function uploadFile(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'url' => 'required|url',
            'file_type' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $task = Task::create([
            'device_id' => $device->id,
            'task_type' => 'upload',
            'parameters' => [
                'url' => $validated['url'],
                'file_type' => $validated['file_type'] ?? '3 Vendor Log File',
                'username' => $validated['username'] ?? '',
                'password' => $validated['password'] ?? '',
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Upload task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Delete a device
     */
    public function destroy(string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $device->delete();

        return response()->json([
            'message' => 'Device deleted successfully',
        ]);
    }
}
