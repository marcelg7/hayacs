<?php

use App\Http\Controllers\CwmpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DeviceTypeController;
use App\Http\Controllers\FirmwareController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

// CWMP Endpoint for TR-069 Device Communication (Protected with HTTP Basic Auth)
// This must be outside the auth middleware group - devices use HTTP Basic Auth
Route::match(['get', 'post'], '/cwmp', [CwmpController::class, 'handle'])->middleware('cwmp.auth');

// Password Setup (for new users via email link - no auth required, uses signed URL)
Route::get('/password/setup/{user}', [PasswordSetupController::class, 'show'])->name('password.setup');
Route::post('/password/setup/{user}', [PasswordSetupController::class, 'store'])->name('password.setup.store');

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Protected dashboard routes (requires authentication)
Route::middleware(['auth'])->group(function () {
    // Password Change (accessible even when must_change_password is true)
    Route::get('/change-password', [PasswordChangeController::class, 'show'])->name('password.change')->withoutMiddleware('App\Http\Middleware\EnsurePasswordChanged');
    Route::post('/change-password', [PasswordChangeController::class, 'update'])->name('password.change.update')->withoutMiddleware('App\Http\Middleware\EnsurePasswordChanged');

    // Theme switcher
    Route::get('/theme/{theme}', [ThemeController::class, 'set'])->name('theme.set');

    // Global Search (web session auth) - excluded from password change check for AJAX
    Route::get('/search', [SearchController::class, 'search'])->name('search')->withoutMiddleware(\App\Http\Middleware\EnsurePasswordChanged::class);

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
        // User Management
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('/users/{user}/resend-welcome', [UserController::class, 'resendWelcome'])->name('users.resend-welcome');

        // Device Types Management
        Route::resource('device-types', DeviceTypeController::class);

        // Firmware Management (nested under device-types)
        Route::get('/device-types/{deviceType}/firmware', [FirmwareController::class, 'index'])->name('firmware.index');
        Route::get('/device-types/{deviceType}/firmware/create', [FirmwareController::class, 'create'])->name('firmware.create');
        Route::post('/device-types/{deviceType}/firmware', [FirmwareController::class, 'store'])->name('firmware.store');
        Route::post('/device-types/{deviceType}/firmware/{firmware}/toggle', [FirmwareController::class, 'toggleActive'])->name('firmware.toggle');
        Route::delete('/device-types/{deviceType}/firmware/{firmware}', [FirmwareController::class, 'destroy'])->name('firmware.destroy');
    });
});

require __DIR__.'/auth.php';
