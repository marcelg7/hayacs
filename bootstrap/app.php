<?php

use App\Http\Middleware\CwmpAuth;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsAdminOrSupport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Device routes - no middleware for TR-069 device communication
            Route::middleware([])
                ->group(base_path('routes/device.php'));
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Run task timeout check every minute
        $schedule->command('tasks:timeout')->everyMinute();

        // Collect analytics metrics every hour
        $schedule->command('analytics:collect-metrics')->hourly();

        // Create daily backup for all devices at 11 PM
        $schedule->command('backups:create-daily')
            ->dailyAt('23:00')
            ->onOneServer()
            ->withoutOverlapping();

        // Cleanup old backups (7 day retention, preserves initial backups)
        // Run at 11:30 PM after daily backups complete
        $schedule->command('backups:cleanup')
            ->dailyAt('23:30')
            ->onOneServer()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsurePasswordChanged::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'cwmp.auth' => CwmpAuth::class,
            'admin' => EnsureUserIsAdmin::class,
            'admin.support' => EnsureUserIsAdminOrSupport::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'cwmp',
            'device-upload/*', // TR-069 Upload RPC file reception
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
