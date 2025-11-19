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
Route::get('/devices/{id}/parameters/export', [DeviceController::class, 'exportParameters']);
Route::post('/devices/{id}/get-parameters', [DeviceController::class, 'getParameters']);
Route::post('/devices/{id}/set-parameters', [DeviceController::class, 'setParameters']);
Route::post('/devices/{id}/get-all-parameters', [DeviceController::class, 'getAllParameters']);

// Device Tasks
Route::get('/devices/{id}/tasks', [DeviceController::class, 'tasks']);
Route::post('/devices/{id}/tasks', [DeviceController::class, 'createTask']);

// Device Actions
Route::post('/devices/{id}/query', [DeviceController::class, 'query']);
Route::post('/devices/{id}/refresh-troubleshooting', [DeviceController::class, 'refreshTroubleshooting']);
Route::post('/devices/{id}/enable-stun', [DeviceController::class, 'enableStun']);
Route::post('/devices/{id}/connection-request', [DeviceController::class, 'connectionRequest']);
Route::post('/devices/{id}/remote-gui', [DeviceController::class, 'remoteGui']);
Route::post('/devices/{id}/reboot', [DeviceController::class, 'reboot']);
Route::post('/devices/{id}/factory-reset', [DeviceController::class, 'factoryReset']);
Route::post('/devices/{id}/firmware-upgrade', [DeviceController::class, 'firmwareUpgrade']);
Route::post('/devices/{id}/upload', [DeviceController::class, 'uploadFile']);
Route::post('/devices/{id}/ping-test', [DeviceController::class, 'pingTest']);
Route::post('/devices/{id}/traceroute-test', [DeviceController::class, 'tracerouteTest']);

// WiFi Configuration
Route::get('/devices/{id}/wifi-config', [DeviceController::class, 'getWifiConfig']);
Route::post('/devices/{id}/wifi-config', [DeviceController::class, 'updateWifi']);
Route::post('/devices/{id}/wifi-radio', [DeviceController::class, 'updateWifiRadio']);

// Configuration Backups
Route::get('/devices/{id}/backups', [DeviceController::class, 'getBackups']);
Route::post('/devices/{id}/backups', [DeviceController::class, 'createBackup']);
Route::post('/devices/{id}/backups/{backupId}/restore', [DeviceController::class, 'restoreBackup']);

// Port Management
Route::get('/devices/{id}/port-mappings', [DeviceController::class, 'getPortMappings']);
Route::post('/devices/{id}/port-mappings', [DeviceController::class, 'addPortMapping']);
Route::delete('/devices/{id}/port-mappings', [DeviceController::class, 'deletePortMapping']);

// WiFi Interference Scan
Route::post('/devices/{id}/wifi-scan', [DeviceController::class, 'startWiFiScan']);
Route::get('/devices/{id}/wifi-scan-results', [DeviceController::class, 'getWiFiScanResults']);

// TR-143 SpeedTest
Route::post('/devices/{id}/speedtest', [DeviceController::class, 'startSpeedTest']);
Route::get('/devices/{id}/speedtest/status', [DeviceController::class, 'getSpeedTestStatus']);
Route::get('/devices/{id}/speedtest/history', [DeviceController::class, 'getSpeedTestHistory']);

// Analytics
use App\Http\Controllers\AnalyticsController;

Route::get('/analytics/device-health', [AnalyticsController::class, 'deviceHealth']);
Route::get('/analytics/task-performance', [AnalyticsController::class, 'taskPerformance']);
Route::get('/analytics/parameter-trending', [AnalyticsController::class, 'parameterTrending']);
Route::get('/analytics/fleet', [AnalyticsController::class, 'fleetAnalytics']);
Route::get('/analytics/speedtest-results', [AnalyticsController::class, 'speedTestResults']);
Route::get('/analytics/available-parameters', [AnalyticsController::class, 'getAvailableParameters']);
