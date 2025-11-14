<?php

namespace App\Http\Controllers;

use App\Models\DeviceType;
use App\Models\Firmware;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class FirmwareController extends Controller
{
    /**
     * Display firmware for a device type
     */
    public function index(DeviceType $deviceType): View
    {
        $firmware = $deviceType->firmware()->orderBy('created_at', 'desc')->get();

        return view('dashboard.firmware.index', compact('deviceType', 'firmware'));
    }

    /**
     * Show the form for uploading new firmware
     */
    public function create(DeviceType $deviceType): View
    {
        return view('dashboard.firmware.create', compact('deviceType'));
    }

    /**
     * Store newly uploaded firmware
     */
    public function store(Request $request, DeviceType $deviceType): RedirectResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|max:255',
            'firmware_file' => 'required|file|max:102400', // Max 100MB
            'release_notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'download_url' => 'nullable|url',
        ]);

        // Handle file upload
        if ($request->hasFile('firmware_file')) {
            $file = $request->file('firmware_file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('firmware', 'public');
            $fileSize = $file->getSize();
            $fileHash = hash_file('sha256', $file->getRealPath());

            // If this firmware is set as active, deactivate others
            if ($request->boolean('is_active')) {
                $deviceType->firmware()->update(['is_active' => false]);
            }

            Firmware::create([
                'device_type_id' => $deviceType->id,
                'version' => $validated['version'],
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_hash' => $fileHash,
                'release_notes' => $validated['release_notes'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'download_url' => $validated['download_url'] ?? null,
            ]);
        }

        return redirect()->route('firmware.index', $deviceType)
            ->with('success', 'Firmware uploaded successfully.');
    }

    /**
     * Toggle the active status of firmware
     */
    public function toggleActive(DeviceType $deviceType, Firmware $firmware): RedirectResponse
    {
        // Deactivate all firmware for this device type
        $deviceType->firmware()->update(['is_active' => false]);

        // Activate this firmware
        $firmware->update(['is_active' => true]);

        return redirect()->route('firmware.index', $deviceType)
            ->with('success', 'Active firmware updated successfully.');
    }

    /**
     * Remove the specified firmware
     */
    public function destroy(DeviceType $deviceType, Firmware $firmware): RedirectResponse
    {
        // Delete the file from storage
        if (Storage::disk('public')->exists($firmware->file_path)) {
            Storage::disk('public')->delete($firmware->file_path);
        }

        $firmware->delete();

        return redirect()->route('firmware.index', $deviceType)
            ->with('success', 'Firmware deleted successfully.');
    }
}
