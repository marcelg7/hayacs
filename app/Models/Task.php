<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'device_id',
        'initiated_by_user_id',
        'task_type',
        'description',
        'parameters',
        'progress_info',
        'status',
        'resend_count',
        'sent_at',
        'result',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'progress_info' => 'array',
        'result' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate description when creating tasks
     */
    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (empty($task->description)) {
                $task->description = $task->generateDefaultDescription();
            }
        });
    }

    /**
     * Generate a default description based on task_type and parameters
     */
    public function generateDefaultDescription(): string
    {
        $params = $this->parameters ?? [];

        switch ($this->task_type) {
            case 'get_params':
                // Check if it's a partial path query
                if (isset($params['path'])) {
                    $path = $params['path'];
                    // Shorten long paths
                    if (strlen($path) > 50) {
                        $path = '...' . substr($path, -47);
                    }
                    return "Get parameters: {$path}";
                }
                // Check if it's named parameters
                if (isset($params['names']) && is_array($params['names'])) {
                    $count = count($params['names']);
                    return "Get {$count} parameter" . ($count !== 1 ? 's' : '');
                }
                return 'Get parameters';

            case 'set_params':
            case 'set_parameter_values':
                $paramCount = 0;
                foreach ($params as $key => $value) {
                    if (!str_starts_with($key, '_')) {
                        $paramCount++;
                    }
                }
                return "Set {$paramCount} parameter" . ($paramCount !== 1 ? 's' : '');

            case 'verify_set_params':
                return 'Verify parameter changes';

            case 'wifi_scan':
                return 'WiFi interference scan';

            case 'reboot':
                return 'Reboot device';

            case 'factory_reset':
                return 'Factory reset';

            case 'get_param_names':
                if (isset($params['path'])) {
                    return "Discover parameters: {$params['path']}";
                }
                return 'Discover parameter names';

            case 'download':
                if (isset($params['url'])) {
                    $filename = basename(parse_url($params['url'], PHP_URL_PATH));
                    if (strlen($filename) > 40) {
                        $filename = substr($filename, 0, 37) . '...';
                    }
                    return "Download: {$filename}";
                }
                return 'Firmware download';

            case 'upload':
                return 'Upload configuration';

            case 'discover_troubleshooting':
                return 'Refresh: Discover device parameters';

            case 'download_diagnostics':
                return 'Speed test: Download';

            case 'upload_diagnostics':
                return 'Speed test: Upload';

            case 'ping_diagnostics':
                return 'Ping test';

            case 'traceroute_diagnostics':
                return 'Traceroute test';

            case 'add_object':
                if (isset($params['object_name'])) {
                    $obj = str_replace(['InternetGatewayDevice.', 'Device.'], '', $params['object_name']);
                    return "Add: {$obj}";
                }
                return 'Add object';

            case 'delete_object':
                if (isset($params['object_name'])) {
                    $obj = str_replace(['InternetGatewayDevice.', 'Device.'], '', $params['object_name']);
                    return "Delete: {$obj}";
                }
                return 'Delete object';

            default:
                return ucfirst(str_replace('_', ' ', $this->task_type));
        }
    }

    /**
     * Get the device that owns this task
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Get the user who initiated this task
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Get the initiator display name
     * Returns user name if user-initiated, "ACS" if system-initiated
     */
    public function getInitiatorDisplayName(): string
    {
        if ($this->initiated_by_user_id === null) {
            return 'ACS';
        }

        return $this->initiator?->name ?? 'Unknown User';
    }

    /**
     * Mark task as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(?array $result = null): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);

        // Check if this is a download test that should trigger an upload test
        if ($this->task_type === 'download_diagnostics' &&
            is_array($this->progress_info) &&
            ($this->progress_info['queue_upload_after'] ?? false)) {

            $this->queueUploadTest();
        }
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $error, ?array $result = null): void
    {
        $updateData = [
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ];

        if ($result !== null) {
            $updateData['result'] = $result;
        }

        $this->update($updateData);
    }

    /**
     * Queue upload test after download completes
     */
    private function queueUploadTest(): void
    {
        $uploadUrl = $this->progress_info['upload_url'] ?? 'http://tr143.hay.net/handler.php';
        $isDevice2 = $this->progress_info['is_device2'] ?? false;
        $isSmartRG = $this->progress_info['is_smartrg'] ?? false;

        $uploadPrefix = $isDevice2
            ? 'Device.IP.Diagnostics.UploadDiagnostics'
            : 'InternetGatewayDevice.UploadDiagnostics';

        \Log::info('Queueing upload test after download completion', [
            'device_id' => $this->device_id,
            'download_task_id' => $this->id,
        ]);

        if ($isSmartRG) {
            // SmartRG: 3 separate tasks
            // Task 1: Set NumberOfConnections
            $configTask1 = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'set_parameter_values',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.NumberOfConnections" => [
                        'value' => '2',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);

            // Task 2: Set TimeBasedTestDuration
            $configTask2 = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'set_parameter_values',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.TimeBasedTestDuration" => [
                        'value' => '12',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);

            // Task 3: Set DiagnosticsState + UploadURL + TestFileLength
            $uploadTask = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'upload_diagnostics',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.DiagnosticsState" => [
                        'value' => 'Requested',
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.UploadURL" => [
                        'value' => $uploadUrl,
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.TestFileLength" => [
                        'value' => '1858291200',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);
        } else {
            // Standard devices: 3 separate tasks
            $configTask1 = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'set_parameter_values',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.NumberOfConnections" => [
                        'value' => '2',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);

            $configTask2 = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'set_parameter_values',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.TimeBasedTestDuration" => [
                        'value' => '12',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);

            $uploadTask = self::create([
                'device_id' => $this->device_id,
                'task_type' => 'upload_diagnostics',
                'status' => 'pending',
                'parameters' => [
                    "{$uploadPrefix}.DiagnosticsState" => [
                        'value' => 'Requested',
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.UploadURL" => [
                        'value' => $uploadUrl,
                        'type' => 'xsd:string',
                    ],
                    "{$uploadPrefix}.TestFileLength" => [
                        'value' => '1858291200',
                        'type' => 'xsd:unsignedInt',
                    ],
                ],
            ]);
        }

        \Log::info('Upload test queued', [
            'device_id' => $this->device_id,
            'tasks_created' => 3,
        ]);
    }

    /**
     * Check if task is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if task is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get elapsed time in seconds
     */
    public function getElapsedSeconds(): int
    {
        $startTime = $this->sent_at ?? $this->created_at;
        $endTime = $this->completed_at ?? now();

        return $startTime->diffInSeconds($endTime);
    }

    /**
     * Get friendly task description
     */
    public function getFriendlyDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $descriptions = [
            'get_params' => 'Get Parameters',
            'set_params' => 'Set Parameters',
            'verify_set_params' => 'Verify Changes',
            'wifi_scan' => 'WiFi Interference Scan',
            'reboot' => 'Reboot Device',
            'factory_reset' => 'Factory Reset',
            'get_param_names' => 'Get Parameter Names',
            'download' => 'Download Firmware',
            'upload' => 'Upload Configuration',
            'backup' => 'Backup Configuration',
            'restore' => 'Restore Configuration',
            'download_diagnostics' => 'Speed test (download)',
            'upload_diagnostics' => 'Speed test (upload)',
            'ping_diagnostics' => 'Ping test',
            'traceroute_diagnostics' => 'Traceroute test',
        ];

        return $descriptions[$this->task_type] ?? ucfirst(str_replace('_', ' ', $this->task_type));
    }

    /**
     * Get progress details
     */
    public function getProgressDetails(): ?string
    {
        if (!$this->progress_info) {
            return null;
        }

        $info = $this->progress_info;

        // Handle chunked tasks
        if (isset($info['chunk']) && isset($info['total_chunks'])) {
            return "Chunk {$info['chunk']}/{$info['total_chunks']}";
        }

        // Handle discovered parameters
        if (isset($info['discovered'])) {
            return "Discovered {$info['discovered']} parameters";
        }

        // Handle parameter count
        if (isset($info['count'])) {
            return "{$info['count']} parameters";
        }

        return null;
    }

    /**
     * Calculate average duration for this task type (in seconds)
     */
    public static function getAverageDuration(string $taskType): ?int
    {
        $completed = self::where('task_type', $taskType)
            ->where('status', 'completed')
            ->whereNotNull('sent_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completed->isEmpty()) {
            return null;
        }

        $totalSeconds = $completed->sum(function ($task) {
            return $task->sent_at->diffInSeconds($task->completed_at);
        });

        return (int) ($totalSeconds / $completed->count());
    }

    /**
     * Get estimated time remaining (in seconds)
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if ($this->status !== 'sent') {
            return null;
        }

        $avgDuration = self::getAverageDuration($this->task_type);
        if (!$avgDuration) {
            return null;
        }

        $elapsed = $this->getElapsedSeconds();
        $remaining = $avgDuration - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Check if task can be cancelled
     */
    public function isCancellable(): bool
    {
        return $this->status === 'pending';
    }
}
