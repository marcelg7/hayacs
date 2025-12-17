<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDailyActivityReport extends Command
{
    protected $signature = 'report:daily-activity
                            {--date= : Specific date to report on (YYYY-MM-DD), defaults to yesterday}
                            {--no-slack : Skip sending to Slack, just output to console}';

    protected $description = 'Send daily ACS activity report to Slack';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $reportDate = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();
        $startOfDay = $reportDate->copy()->startOfDay();
        $endOfDay = $reportDate->copy()->endOfDay();

        $this->info("Generating activity report for {$reportDate->format('l, F j, Y')}");

        // Gather all statistics
        $stats = $this->gatherStats($startOfDay, $endOfDay);

        // Output to console
        $this->displayConsoleReport($stats, $reportDate);

        // Send to Slack unless --no-slack
        if (!$this->option('no-slack')) {
            $this->sendSlackReport($stats, $reportDate);
        }

        return Command::SUCCESS;
    }

    protected function gatherStats(Carbon $startOfDay, Carbon $endOfDay): array
    {
        // Use aggregate queries instead of loading all records
        $taskQuery = Task::whereBetween('created_at', [$startOfDay, $endOfDay]);

        // Task counts by status
        $taskCounts = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        // Average duration for completed tasks
        $avgDuration = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('status', 'completed')
            ->whereNotNull('sent_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, completed_at)) as avg_duration')
            ->value('avg_duration');

        // Tasks by type
        $tasksByType = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('task_type', DB::raw('COUNT(*) as count'))
            ->groupBy('task_type')
            ->orderByDesc('count')
            ->pluck('count', 'task_type')
            ->toArray();

        // Tasks by user
        $userTasksRaw = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select(
                'initiated_by_user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->groupBy('initiated_by_user_id')
            ->orderByDesc('total')
            ->get();

        $tasksByUser = $userTasksRaw->mapWithKeys(function ($row) {
            $user = $row->initiated_by_user_id ? User::find($row->initiated_by_user_id) : null;
            $key = $row->initiated_by_user_id ?? 'system';
            return [$key => [
                'user_name' => $user?->name ?? 'System (ACS)',
                'total' => $row->total,
                'completed' => $row->completed,
                'failed' => $row->failed,
            ]];
        });

        // Unique devices
        $uniqueDeviceCount = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->distinct('device_id')
            ->count('device_id');

        // Device events count (use count, don't load all)
        $eventCounts = DB::table('device_events')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT device_id) as unique_devices')
            ->first();

        $eventsByType = DB::table('device_events')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type')
            ->toArray();

        // Devices online count
        $devicesOnline = Device::where('online', true)
            ->whereBetween('last_inform', [$startOfDay, $endOfDay])
            ->count();

        // Failed tasks with details (limit to 10)
        $failedTaskDetails = Task::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('status', 'failed')
            ->with(['device', 'initiator'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'type' => $task->task_type,
                    'device' => $task->device?->serial_number ?? 'Unknown',
                    'subscriber' => $task->device?->subscriber?->name ?? 'N/A',
                    'user' => $task->getInitiatorDisplayName(),
                    'error' => $this->extractErrorMessage($task->result),
                    'created_at' => $task->created_at->format('g:i A'),
                ];
            });

        // Active users (non-system)
        $activeUsers = $tasksByUser->filter(fn($u) => $u['user_name'] !== 'System (ACS)');

        // Top devices by task count (limit to 10)
        $topDevicesRaw = DB::table('tasks')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select(
                'device_id',
                DB::raw('COUNT(*) as task_count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->groupBy('device_id')
            ->orderByDesc('task_count')
            ->take(10)
            ->get();

        $topDevices = $topDevicesRaw->map(function ($row) {
            $device = Device::with('subscriber')->find($row->device_id);
            return [
                'serial' => $device?->serial_number ?? 'Unknown',
                'subscriber' => $device?->subscriber?->name ?? 'N/A',
                'product_class' => $device?->product_class ?? 'Unknown',
                'task_count' => $row->task_count,
                'completed' => $row->completed,
                'failed' => $row->failed,
            ];
        });

        // Slowest tasks (limit to 5)
        $slowestTasks = Task::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('status', 'completed')
            ->whereNotNull('sent_at')
            ->whereNotNull('completed_at')
            ->with(['device', 'initiator'])
            ->orderByRaw('TIMESTAMPDIFF(SECOND, sent_at, completed_at) DESC')
            ->take(5)
            ->get()
            ->map(function ($task) {
                $duration = $task->completed_at->diffInSeconds($task->sent_at);
                return [
                    'id' => $task->id,
                    'type' => $task->task_type,
                    'device' => $task->device?->serial_number ?? 'Unknown',
                    'duration_seconds' => $duration,
                    'duration_formatted' => $this->formatDuration($duration),
                    'user' => $task->getInitiatorDisplayName(),
                ];
            });

        return [
            'date' => $startOfDay,
            'tasks' => [
                'total' => $taskCounts->total ?? 0,
                'completed' => $taskCounts->completed ?? 0,
                'failed' => $taskCounts->failed ?? 0,
                'pending' => $taskCounts->pending ?? 0,
                'avg_duration_seconds' => $avgDuration ? round($avgDuration, 1) : null,
                'by_type' => $tasksByType,
                'by_user' => $tasksByUser->toArray(),
            ],
            'users' => [
                'active_count' => $activeUsers->count(),
                'details' => $activeUsers->toArray(),
            ],
            'devices' => [
                'unique_acted_on' => $uniqueDeviceCount,
                'reporting' => $eventCounts->unique_devices ?? 0,
                'came_online' => $devicesOnline,
                'top_by_tasks' => $topDevices->toArray(),
            ],
            'events' => [
                'total' => $eventCounts->total ?? 0,
                'by_type' => $eventsByType,
            ],
            'issues' => [
                'failed_tasks' => $failedTaskDetails->toArray(),
                'slowest_tasks' => $slowestTasks->toArray(),
            ],
        ];
    }

    protected function displayConsoleReport(array $stats, Carbon $reportDate): void
    {
        $this->newLine();
        $this->line("╔══════════════════════════════════════════════════════════════╗");
        $this->line("║           Today's Hay ACS Activity - {$reportDate->format('M j, Y')}            ║");
        $this->line("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // Task summary
        $this->info("📋 Tasks Summary");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tasks', $stats['tasks']['total']],
                ['Completed', $stats['tasks']['completed']],
                ['Failed', $stats['tasks']['failed']],
                ['Pending', $stats['tasks']['pending']],
                ['Avg Duration', $stats['tasks']['avg_duration_seconds'] ? $this->formatDuration($stats['tasks']['avg_duration_seconds']) : 'N/A'],
            ]
        );

        // Active users
        if (!empty($stats['users']['details'])) {
            $this->newLine();
            $this->info("👥 Active Users ({$stats['users']['active_count']})");
            $userRows = [];
            foreach ($stats['users']['details'] as $user) {
                $userRows[] = [
                    $user['user_name'],
                    $user['total'],
                    $user['completed'],
                    $user['failed'],
                ];
            }
            $this->table(['User', 'Tasks', 'Completed', 'Failed'], $userRows);
        }

        // Failed tasks
        if (!empty($stats['issues']['failed_tasks'])) {
            $this->newLine();
            $this->warn("⚠️ Failed Tasks");
            $failedRows = [];
            foreach ($stats['issues']['failed_tasks'] as $task) {
                $failedRows[] = [
                    $task['id'],
                    $task['type'],
                    substr($task['device'], 0, 15),
                    $task['user'],
                    substr($task['error'] ?? 'Unknown error', 0, 30),
                ];
            }
            $this->table(['ID', 'Type', 'Device', 'User', 'Error'], $failedRows);
        }

        $this->newLine();
        $this->info("View full report: " . route('reports.daily-activity', ['date' => $reportDate->format('Y-m-d')]));
    }

    protected function sendSlackReport(array $stats, Carbon $reportDate): void
    {
        $webhookUrl = config('services.slack.daily_report.webhook_url');
        $enabled = config('services.slack.daily_report.enabled', false);

        if (!$enabled || empty($webhookUrl)) {
            $this->warn('Slack daily report is not enabled or webhook URL not configured.');
            $this->line('Set SLACK_DAILY_REPORT_ENABLED=true and SLACK_DAILY_REPORT_WEBHOOK_URL in .env');
            return;
        }

        $reportUrl = route('reports.daily-activity', ['date' => $reportDate->format('Y-m-d')]);

        // Determine overall health emoji
        $failRate = $stats['tasks']['total'] > 0
            ? ($stats['tasks']['failed'] / $stats['tasks']['total']) * 100
            : 0;
        $healthEmoji = $failRate > 10 ? '🔴' : ($failRate > 5 ? '🟡' : '🟢');

        // Build user activity summary
        $userSummary = '';
        foreach (array_slice($stats['users']['details'], 0, 5) as $user) {
            $userSummary .= "• *{$user['user_name']}*: {$user['total']} tasks";
            if ($user['failed'] > 0) {
                $userSummary .= " ({$user['failed']} failed)";
            }
            $userSummary .= "\n";
        }
        if (empty($userSummary)) {
            $userSummary = "_No user-initiated tasks_";
        }

        // Build task type breakdown
        $taskTypeSummary = '';
        foreach (array_slice($stats['tasks']['by_type'], 0, 5, true) as $type => $count) {
            $taskTypeSummary .= "• `{$type}`: {$count}\n";
        }

        // Build failed tasks summary
        $failedSummary = '';
        if (!empty($stats['issues']['failed_tasks'])) {
            foreach (array_slice($stats['issues']['failed_tasks'], 0, 3) as $task) {
                $errorMsg = substr($task['error'] ?? 'Unknown', 0, 50);
                $failedSummary .= "• #{$task['id']} `{$task['type']}` - {$task['device']}\n  _{$errorMsg}_\n";
            }
        } else {
            $failedSummary = "_No failed tasks_ :tada:";
        }

        $avgDuration = $stats['tasks']['avg_duration_seconds']
            ? $this->formatDuration($stats['tasks']['avg_duration_seconds'])
            : 'N/A';

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "📊 Today's Hay ACS Activity - {$reportDate->format('l, M j')}",
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*{$healthEmoji} Tasks*\n{$stats['tasks']['total']} total",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*✅ Completed*\n{$stats['tasks']['completed']}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*❌ Failed*\n{$stats['tasks']['failed']}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*⏱️ Avg Duration*\n{$avgDuration}",
                    ],
                ],
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*👥 User Activity*\n{$userSummary}",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*📋 Task Types*\n{$taskTypeSummary}",
                ],
            ],
        ];

        // Add failed tasks section if there are failures
        if ($stats['tasks']['failed'] > 0) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*⚠️ Failed Tasks*\n{$failedSummary}",
                ],
            ];
        }

        // Add device stats
        $blocks[] = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*📡 Devices Active*\n{$stats['devices']['unique_acted_on']} managed",
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*📶 Devices Reporting*\n{$stats['devices']['reporting']} checked in",
                ],
            ],
        ];

        // Add link to full report
        $blocks[] = [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '📄 View Full Report',
                        'emoji' => true,
                    ],
                    'url' => $reportUrl,
                    'style' => 'primary',
                ],
            ],
        ];

        // Add context footer
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "Generated " . now()->format('M j, Y g:i A') . " | <{$reportUrl}|View detailed report>",
                ],
            ],
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'blocks' => $blocks,
                'text' => "Hay ACS Daily Activity Report - {$reportDate->format('M j, Y')}: {$stats['tasks']['total']} tasks, {$stats['tasks']['completed']} completed, {$stats['tasks']['failed']} failed",
            ]);

            if ($response->successful()) {
                $this->info('✓ Slack notification sent successfully');
                Log::info('Daily activity report sent to Slack', [
                    'date' => $reportDate->toDateString(),
                    'tasks_total' => $stats['tasks']['total'],
                ]);
            } else {
                $this->error('Failed to send Slack notification: ' . $response->body());
                Log::error('Failed to send daily activity report to Slack', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $this->error('Exception sending Slack notification: ' . $e->getMessage());
            Log::error('Exception sending daily activity report to Slack', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function extractErrorMessage($result): ?string
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if ($decoded) {
                $result = $decoded;
            }
        }

        if (is_array($result)) {
            return $result['error'] ?? $result['message'] ?? $result['faultstring'] ?? json_encode($result);
        }

        return $result ? (string) $result : null;
    }

    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }
}
