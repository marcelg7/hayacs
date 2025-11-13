<?php

use App\Http\Controllers\CwmpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Password Change Routes (must be before other auth routes)
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

// Dashboard Routes (protected by auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/devices', [DashboardController::class, 'devices'])->name('devices');
    Route::get('/devices/{id}', [DashboardController::class, 'device'])->name('device.show');
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// CWMP Endpoint for TR-069 Device Communication (Protected with HTTP Basic Auth)
Route::post('/cwmp', [CwmpController::class, 'handle'])->middleware('cwmp.auth');

require __DIR__.'/auth.php';
