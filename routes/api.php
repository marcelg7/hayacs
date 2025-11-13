<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Statistics
Route::get('/stats', [StatsController::class, 'index']);

// Device Management
Route::get('/devices', [DeviceController::class, 'index']);
Route::get('/devices/{id}', [DeviceController::class, 'show']);
Route::patch('/devices/{id}', [DeviceController::class, 'update']);
Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);

// Device Parameters
Route::get('/devices/{id}/parameters', [DeviceController::class, 'parameters']);
Route::post('/devices/{id}/get-parameters', [DeviceController::class, 'getParameters']);
Route::post('/devices/{id}/set-parameters', [DeviceController::class, 'setParameters']);

// Device Tasks
Route::get('/devices/{id}/tasks', [DeviceController::class, 'tasks']);
Route::post('/devices/{id}/tasks', [DeviceController::class, 'createTask']);

// Device Actions
Route::post('/devices/{id}/reboot', [DeviceController::class, 'reboot']);
Route::post('/devices/{id}/factory-reset', [DeviceController::class, 'factoryReset']);
Route::post('/devices/{id}/firmware-upgrade', [DeviceController::class, 'firmwareUpgrade']);
Route::post('/devices/{id}/upload', [DeviceController::class, 'uploadFile']);
