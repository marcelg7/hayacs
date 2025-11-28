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
