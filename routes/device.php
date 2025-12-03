<?php

use App\Http\Controllers\DeviceUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device Routes
|--------------------------------------------------------------------------
|
| These routes are for direct device communication without any web middleware.
| TR-069 devices use these endpoints for file uploads via the Upload RPC.
| Security is handled via token authentication in the URL.
|
*/

// Device Upload Endpoint (for TR-069 Upload RPC)
// Devices PUT config/log files to this endpoint. Token in URL provides security.
Route::match(['put', 'post'], '/device-upload/{deviceId}/{taskId}', [DeviceUploadController::class, 'receive'])
    ->name('device.upload.receive');

// Device Config Download Endpoint (for TR-069 Download RPC - config restore)
// Devices GET config files from this endpoint. Token in URL provides security.
Route::get('/device-config/{taskId}', [DeviceUploadController::class, 'serveConfigFile'])
    ->name('device.config.serve');

// Static migration files endpoint (for TR-181 pre-config files)
// Serves files from storage/app/public/migration/ directory
Route::get('/device-config/migration/{filename}', [DeviceUploadController::class, 'serveMigrationFile'])
    ->name('device.config.migration')
    ->where('filename', '.*');
