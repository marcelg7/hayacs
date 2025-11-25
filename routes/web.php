<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DeviceTypeController;
use App\Http\Controllers\FirmwareController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Protected dashboard routes (requires authentication)
Route::middleware(['auth'])->group(function () {
    // Main dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Devices
    Route::get('/devices', [DashboardController::class, 'devices'])->name('devices.index');
    Route::get('/devices/{id}', [DashboardController::class, 'device'])->name('device.show');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin-only routes
    Route::middleware(['admin'])->group(function () {
        // Device Types Management
        Route::resource('device-types', DeviceTypeController::class);

        // Firmware Management
        Route::resource('firmware', FirmwareController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
