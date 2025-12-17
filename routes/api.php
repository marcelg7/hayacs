<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\AnalyticsController;
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

// All API routes require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // Global Search
    Route::get('/search', [SearchController::class, 'search']);

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
    Route::delete('/devices/{id}/tasks/{taskId}', [DeviceController::class, 'cancelTask']);

    // Task Status (for polling)
    Route::get('/tasks/{taskId}', function ($taskId) {
        $task = \App\Models\Task::find($taskId);
        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }
        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'task_type' => $task->task_type,
            'description' => $task->description,
            'result' => $task->result,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ]);
    });

    // Device Actions
    Route::post('/devices/{id}/query', [DeviceController::class, 'query']);
    Route::post('/devices/{id}/refresh-troubleshooting', [DeviceController::class, 'refreshTroubleshooting']);
    Route::post('/devices/{id}/enable-stun', [DeviceController::class, 'enableStun']);
    Route::post('/devices/{id}/connection-request', [DeviceController::class, 'connectionRequest']);
    Route::post('/devices/{id}/remote-gui', [DeviceController::class, 'remoteGui']);
    Route::post('/devices/{id}/close-remote-access', [DeviceController::class, 'closeRemoteAccess']);
    Route::post('/devices/{id}/reboot', [DeviceController::class, 'reboot']);
    Route::post('/devices/{id}/factory-reset', [DeviceController::class, 'factoryReset']);
    Route::post('/devices/{id}/firmware-upgrade', [DeviceController::class, 'firmwareUpgrade']);
    Route::post('/devices/{id}/upload', [DeviceController::class, 'uploadFile']);
    Route::post('/devices/{id}/request-config-backup', [DeviceController::class, 'requestConfigBackup']);
    Route::post('/devices/{id}/ping-test', [DeviceController::class, 'pingTest']);
    Route::post('/devices/{id}/traceroute-test', [DeviceController::class, 'tracerouteTest']);

    // WiFi Configuration
    Route::get('/devices/{id}/wifi-config', [DeviceController::class, 'getWifiConfig']);
    Route::post('/devices/{id}/wifi-config', [DeviceController::class, 'updateWifi']);
    Route::post('/devices/{id}/wifi-radio', [DeviceController::class, 'updateWifiRadio']);

    // Standard WiFi Setup (simplified customer-friendly configuration)
    Route::get('/devices/{id}/standard-wifi', [DeviceController::class, 'getStandardWifiConfig']);
    Route::post('/devices/{id}/standard-wifi', [DeviceController::class, 'applyStandardWifiConfig']);
    Route::post('/devices/{id}/guest-network', [DeviceController::class, 'toggleGuestNetwork']);
    Route::post('/devices/{id}/guest-password', [DeviceController::class, 'updateGuestPassword']);

    // LAN Configuration
    Route::get('/devices/{id}/lan-config', [DeviceController::class, 'getLanConfig']);
    Route::post('/devices/{id}/lan-config', [DeviceController::class, 'updateLanConfig']);

    // Admin Credentials (customer-facing, not support/superadmin)
    Route::get('/devices/{id}/admin-credentials', [DeviceController::class, 'getAdminCredentials']);
    Route::post('/devices/{id}/admin-credentials/reset', [DeviceController::class, 'resetAdminPassword']);

    // Configuration Backups
    Route::get('/devices/{id}/backups', [DeviceController::class, 'getBackups']);
    Route::post('/devices/{id}/backups', [DeviceController::class, 'createBackup']);
    Route::post('/devices/{id}/backups/{backupId}/restore', [DeviceController::class, 'restoreBackup']);
    Route::patch('/devices/{id}/backups/{backupId}', [DeviceController::class, 'updateBackupMetadata']);
    Route::get('/devices/{id}/backups/{backup1Id}/compare/{backup2Id}', [DeviceController::class, 'compareBackups']);
    Route::get('/devices/{id}/backups/{backupId}/download', [DeviceController::class, 'downloadBackup']);
    Route::post('/devices/{id}/backups/import', [DeviceController::class, 'importBackup']);

    // Native Config File Operations (binary config files from device)
    Route::get('/devices/{id}/native-configs', [DeviceController::class, 'getNativeConfigFiles']);
    Route::post('/devices/{id}/native-configs/restore', [DeviceController::class, 'restoreNativeConfig']);

    // Port Management
    Route::get('/devices/{id}/port-mappings', [DeviceController::class, 'getPortMappings']);
    Route::post('/devices/{id}/port-mappings', [DeviceController::class, 'addPortMapping']);
    Route::delete('/devices/{id}/port-mappings', [DeviceController::class, 'deletePortMapping']);
    Route::post('/devices/{id}/port-mappings/refresh', [DeviceController::class, 'refreshPortMappings']);
    Route::get('/devices/{id}/connected-devices', [DeviceController::class, 'getConnectedDevices']);
    Route::post('/devices/{id}/connected-devices/refresh', [DeviceController::class, 'refreshConnectedDevices']);

    // WiFi Interference Scan
    Route::post('/devices/{id}/wifi-scan', [DeviceController::class, 'startWiFiScan']);
    Route::get('/devices/{id}/wifi-scan-results', [DeviceController::class, 'getWiFiScanResults']);

    // TR-143 SpeedTest
    Route::post('/devices/{id}/speedtest', [DeviceController::class, 'startSpeedTest']);
    Route::get('/devices/{id}/speedtest/status', [DeviceController::class, 'getSpeedTestStatus']);
    Route::get('/devices/{id}/speedtest/history', [DeviceController::class, 'getSpeedTestHistory']);

    // Backup Templates
    Route::get('/templates', [DeviceController::class, 'getTemplates']);
    Route::get('/templates/{templateId}', [DeviceController::class, 'getTemplate']);
    Route::post('/templates', [DeviceController::class, 'createTemplate']);
    Route::patch('/templates/{templateId}', [DeviceController::class, 'updateTemplate']);
    Route::delete('/templates/{templateId}', [DeviceController::class, 'deleteTemplate']);
    Route::post('/templates/{templateId}/apply', [DeviceController::class, 'applyTemplate']);

    // Analytics
    Route::get('/analytics/device-health', [AnalyticsController::class, 'deviceHealth']);
    Route::get('/analytics/task-performance', [AnalyticsController::class, 'taskPerformance']);
    Route::get('/analytics/parameter-trending', [AnalyticsController::class, 'parameterTrending']);
    Route::get('/analytics/fleet', [AnalyticsController::class, 'fleetAnalytics']);
    Route::get('/analytics/speedtest-results', [AnalyticsController::class, 'speedTestResults']);
    Route::get('/analytics/available-parameters', [AnalyticsController::class, 'getAvailableParameters']);

    // TR-181 Migration (Nokia Beacon G6)
    Route::get('/migration/stats', [DeviceController::class, 'getMigrationStats']);
    Route::get('/migration/eligible-devices', [DeviceController::class, 'getEligibleDevices']);
    Route::get('/devices/{id}/migration/check', [DeviceController::class, 'checkMigrationEligibility']);
    Route::post('/devices/{id}/migration/start', [DeviceController::class, 'startMigration']);
    Route::get('/devices/{id}/migration/verify', [DeviceController::class, 'verifyMigration']);
    Route::post('/devices/{id}/migration/wifi-fallback', [DeviceController::class, 'createWifiFallback']);

    // Remote Support Password Management (Nokia Beacon G6)
    Route::get('/devices/{id}/remote-support', [DeviceController::class, 'getRemoteSupportStatus']);
    Route::post('/devices/{id}/remote-support/enable', [DeviceController::class, 'enableRemoteSupport']);
    Route::post('/devices/{id}/remote-support/disable', [DeviceController::class, 'disableRemoteSupport']);
    Route::post('/devices/{id}/set-initial-password', [DeviceController::class, 'setInitialPassword']);

    // SSH-Based WiFi Configuration & Password Extraction (Nokia Beacon G6)
    Route::get('/devices/{id}/wifi-configs', [DeviceController::class, 'wifiConfigs']);
    Route::get('/devices/{id}/wifi-passwords', [DeviceController::class, 'wifiPasswords']);
    Route::post('/devices/{id}/extract-wifi-config', [DeviceController::class, 'extractWifiConfig']);
    Route::post('/devices/{id}/test-ssh', [DeviceController::class, 'testSshConnection']);
    Route::get('/devices/{id}/ssh-credentials', [DeviceController::class, 'sshCredentialStatus']);
});
