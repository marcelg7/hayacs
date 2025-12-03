<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queued jobs every minute (emails, notifications, etc.)
Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue.log'));

// Schedule task timeout cleanup to run hourly
// tasks:timeout handles 'sent' tasks with type-specific timeouts (runs every minute)
// tasks:timeout-pending handles 'pending' tasks older than 24 hours (runs hourly)
Schedule::command('tasks:timeout-pending')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/task-timeout.log'));

// Process group workflows every minute
// Handles immediate, scheduled, and recurring workflows with rate limiting
Schedule::command('workflows:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/workflows.log'));

// Reset expired remote support sessions every 5 minutes
// Resets Beacon G6 passwords back to device-specific when support session expires
Schedule::command('devices:reset-expired-remote-support')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/remote-support.log'));

// Log management: rotate large logs, compress, and cleanup old logs daily at 3am
// Rotates logs > 100MB, compresses with xz, deletes logs > 30 days old
Schedule::command('logs:manage --all --max-size=100 --retention=30')
    ->dailyAt('03:00')
    ->withoutOverlapping();
