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

// Nightly remote access audit at 10 PM (when My Support team closes)
// Disables any remaining remote access and resets passwords to random values
Schedule::command('devices:audit-remote-access')
    ->dailyAt('22:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/remote-access-audit.log'));

// Sync billing data from NISC Ivue every 15 minutes
// Watches /home/billingsync/uploads for new CSV exports, truncates and reimports
Schedule::command('billing:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing-sync.log'));

// Mark devices offline if they haven't checked in within threshold (default 20 min)
// Runs every 5 minutes to keep online status accurate
Schedule::command('devices:mark-offline --threshold=20')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Cleanup duplicate parameters at 2:30 AM when traffic is lowest
// Uses batched deletes with delays to avoid lock contention
Schedule::command('parameters:cleanup-duplicates --batch-size=500 --delay=100')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/duplicate-cleanup.log'));

// Daily activity report to Slack at 4:20 PM
// Reports on today's ACS activity for monitoring and troubleshooting
Schedule::command('report:daily-activity --date=' . now()->format('Y-m-d'))
    ->dailyAt('16:20')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-report.log'));

// Pre-warm daily activity report cache every 5 minutes
// Ensures the web report loads instantly for anyone viewing it
Schedule::command('cache:warm-daily-activity')
    ->everyFiveMinutes()
    ->withoutOverlapping();
