<?php

namespace App\Http\Controllers;

use App\Models\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeviceTypeController extends Controller
{
    /**
     * Display a listing of device types
     */
    public function index(): View
    {
        $deviceTypes = DeviceType::withCount('firmware')->orderBy('manufacturer')->get();

        return view('dashboard.device-types.index', compact('deviceTypes'));
    }

    /**
     * Show the form for creating a new device type
     */
    public function create(): View
    {
        return view('dashboard.device-types.create');
    }

    /**
     * Store a newly created device type
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'product_class' => 'nullable|string|max:255',
            'oui' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        DeviceType::create($validated);

        return redirect()->route('device-types.index')
            ->with('success', 'Device type created successfully.');
    }

    /**
     * Show the form for editing the specified device type
     */
    public function edit(DeviceType $deviceType): View
    {
        return view('dashboard.device-types.edit', compact('deviceType'));
    }

    /**
     * Update the specified device type
     */
    public function update(Request $request, DeviceType $deviceType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'product_class' => 'nullable|string|max:255',
            'oui' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $deviceType->update($validated);

        return redirect()->route('device-types.index')
            ->with('success', 'Device type updated successfully.');
    }

    /**
     * Remove the specified device type
     */
    public function destroy(DeviceType $deviceType): RedirectResponse
    {
        $deviceType->delete();

        return redirect()->route('device-types.index')
            ->with('success', 'Device type deleted successfully.');
    }
}
