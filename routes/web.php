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
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\DeviceUploadController;
use App\Http\Controllers\DeviceGroupController;
use App\Http\Controllers\GroupWorkflowController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SlackWebhookController;
use Illuminate\Support\Facades\Route;

// Slack Webhook for Interactive Components (no auth, verified via signing secret)
Route::post('/webhooks/slack/interaction', [SlackWebhookController::class, 'handleInteraction'])
    ->name('slack.interaction')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

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

    // Quick device search (for mobile field techs - redirects to WiFi tab on single match)
    Route::get('/quick-search', [DashboardController::class, 'quickSearch'])->name('quick-search');

    // Devices
    Route::get('/devices', [DashboardController::class, 'devices'])->name('devices.index');
    Route::get('/devices/export', [DashboardController::class, 'exportDevices'])->name('devices.export');
    Route::get('/devices/{id}', [DashboardController::class, 'device'])->name('device.show');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/offline-devices', [ReportController::class, 'offlineDevices'])->name('offline-devices');
        Route::get('/inactive-devices', [ReportController::class, 'inactiveDevices'])->name('inactive-devices');
        Route::get('/devices-without-subscriber', [ReportController::class, 'devicesWithoutSubscriber'])->name('devices-without-subscriber');
        Route::get('/duplicate-serials', [ReportController::class, 'duplicateSerials'])->name('duplicate-serials');
        Route::get('/duplicate-macs', [ReportController::class, 'duplicateMacs'])->name('duplicate-macs');
        Route::get('/excessive-informs', [ReportController::class, 'excessiveInforms'])->name('excessive-informs');
        Route::get('/firmware', [ReportController::class, 'firmwareReport'])->name('firmware');
        Route::get('/device-types', [ReportController::class, 'deviceTypeReport'])->name('device-types');
        Route::get('/connection-request-failures', [ReportController::class, 'connectionRequestFailures'])->name('connection-request-failures');
        Route::get('/stun-devices', [ReportController::class, 'stunDevices'])->name('stun-devices');
        Route::get('/nat-devices', [ReportController::class, 'natDevices'])->name('nat-devices');
        Route::get('/smartrg-on-non-dsl', [ReportController::class, 'smartrgOnNonDsl'])->name('smartrg-on-non-dsl');
        Route::get('/export/all-devices', [ReportController::class, 'exportAllDevices'])->name('export-all-devices');

        // Actions
        Route::post('/wake-device/{id}', [ReportController::class, 'wakeDevice'])->name('wake-device');
        Route::post('/bulk-wake', [ReportController::class, 'bulkWake'])->name('bulk-wake');
        Route::post('/bulk-set-inform-interval', [ReportController::class, 'bulkSetInformInterval'])->name('bulk-set-inform-interval');
    });

    // Analytics API (using web session auth instead of sanctum for seamless browser access)
    Route::get('/analytics/device-health', [AnalyticsController::class, 'deviceHealth'])->name('analytics.device-health');
    Route::get('/analytics/task-performance', [AnalyticsController::class, 'taskPerformance'])->name('analytics.task-performance');
    Route::get('/analytics/speedtest-results', [AnalyticsController::class, 'speedTestResults'])->name('analytics.speedtest-results');
    Route::get('/analytics/fleet', [AnalyticsController::class, 'fleetAnalytics'])->name('analytics.fleet');
    Route::get('/analytics/parameter-trending', [AnalyticsController::class, 'parameterTrending'])->name('analytics.parameter-trending');
    Route::get('/analytics/available-parameters', [AnalyticsController::class, 'getAvailableParameters'])->name('analytics.available-parameters');

    // Subscribers (specific routes MUST come before wildcard routes)
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');

    // Subscriber Data Import (Admin only - BEFORE {subscriber} wildcard)
    Route::middleware(['admin'])->group(function () {
        Route::get('/subscribers/import', [SubscriberController::class, 'import'])->name('subscribers.import');
        Route::post('/subscribers/import', [SubscriberController::class, 'processImport'])->name('subscribers.import.process');
        Route::get('/subscribers/import/status/{importStatus}', [SubscriberController::class, 'importStatus'])->name('subscribers.import.status');
    });

    Route::get('/subscribers/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');

    // Feedback System (accessible to all authenticated users)
    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [FeedbackController::class, 'index'])->name('index');
        Route::get('/create', [FeedbackController::class, 'create'])->name('create');
        Route::post('/', [FeedbackController::class, 'store'])->name('store');
        Route::get('/notifications', [FeedbackController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/mark-all-read', [FeedbackController::class, 'markAllNotificationsRead'])->name('notifications.mark-all-read');
        Route::post('/notifications/{notification}/mark-read', [FeedbackController::class, 'markNotificationRead'])->name('notifications.mark-read');
        Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('show');
        Route::patch('/{feedback}', [FeedbackController::class, 'update'])->name('update');
        Route::delete('/{feedback}', [FeedbackController::class, 'destroy'])->name('destroy');
        Route::post('/{feedback}/upvote', [FeedbackController::class, 'toggleUpvote'])->name('upvote');
        Route::post('/{feedback}/comment', [FeedbackController::class, 'addComment'])->name('comment');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin-only routes
    Route::middleware(['admin'])->group(function () {
        // Server Status API for admin status bar
        Route::get('/server-status', [StatsController::class, 'serverStatus'])->name('server.status');

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

        // Device Upload Management (view/download uploaded config files)
        Route::get('/device-upload/{taskId}/view', [DeviceUploadController::class, 'view'])->name('device.upload.view');
        Route::get('/device-upload/{taskId}/download', [DeviceUploadController::class, 'download'])->name('device.upload.download');

        // Device Groups Management
        Route::resource('device-groups', DeviceGroupController::class);
        Route::post('/device-groups/preview-rules', [DeviceGroupController::class, 'previewRules'])->name('device-groups.preview-rules');
        Route::patch('/device-groups/{deviceGroup}/toggle-active', [DeviceGroupController::class, 'toggleActive'])->name('device-groups.toggle-active');

        // Workflows Management
        Route::resource('workflows', GroupWorkflowController::class);
        Route::patch('/workflows/{workflow}/activate', [GroupWorkflowController::class, 'activate'])->name('workflows.activate');
        Route::patch('/workflows/{workflow}/pause', [GroupWorkflowController::class, 'pause'])->name('workflows.pause');
        Route::patch('/workflows/{workflow}/resume', [GroupWorkflowController::class, 'resume'])->name('workflows.resume');
        Route::patch('/workflows/{workflow}/cancel', [GroupWorkflowController::class, 'cancel'])->name('workflows.cancel');
        Route::patch('/workflows/{workflow}/retry-failed', [GroupWorkflowController::class, 'retryFailed'])->name('workflows.retry-failed');
        Route::get('/workflows/{workflow}/stats', [GroupWorkflowController::class, 'stats'])->name('workflows.stats');
    });
});

require __DIR__.'/auth.php';
