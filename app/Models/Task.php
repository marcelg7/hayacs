<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'device_id',
        'task_type',
        'description',
        'parameters',
        'progress_info',
        'status',
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
     * Get the device that owns this task
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
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
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
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
            'reboot' => 'Reboot Device',
            'factory_reset' => 'Factory Reset',
            'get_param_names' => 'Get Parameter Names',
            'download' => 'Download Firmware',
            'upload' => 'Upload Configuration',
            'backup' => 'Backup Configuration',
            'restore' => 'Restore Configuration',
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
