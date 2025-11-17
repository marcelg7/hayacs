<?php

use App\Http\Middleware\CwmpBasicAuth;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Run task timeout check every minute
        $schedule->command('tasks:timeout')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'cwmp.auth' => CwmpBasicAuth::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'cwmp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
