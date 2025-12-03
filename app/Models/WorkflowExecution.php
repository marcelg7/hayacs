<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_workflow_id',
        'device_id',
        'task_id',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'attempt',
        'next_retry_at',
        'result',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempt' => 'integer',
        'result' => 'array',
    ];

    /**
     * Execution statuses
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'queued' => 'Queued',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The workflow this execution belongs to
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(GroupWorkflow::class, 'group_workflow_id');
    }

    /**
     * The device this execution is for
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    /**
     * The task created for this execution
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Logs for this execution
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class);
    }

    /**
     * Mark as queued with a task
     */
    public function markQueued(Task $task): void
    {
        $this->update([
            'task_id' => $task->id,
            'status' => 'queued',
            'started_at' => now(),
            'attempt' => $this->attempt + 1,
        ]);
    }

    /**
     * Mark as in progress
     */
    public function markInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(array $result = []): void
    {
        $workflow = $this->workflow;

        // Check if we should retry
        if ($workflow && $this->attempt < $workflow->retry_count) {
            $this->update([
                'status' => 'pending',
                'next_retry_at' => now()->addMinutes($workflow->retry_delay_minutes),
                'result' => $result,
            ]);
        } else {
            $this->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => $result,
            ]);
        }
    }

    /**
     * Mark as skipped
     */
    public function markSkipped(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'completed_at' => now(),
            'result' => ['reason' => $reason],
        ]);
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if execution can retry
     */
    public function canRetry(): bool
    {
        if ($this->status !== 'pending' || !$this->next_retry_at) {
            return false;
        }

        return now()->gte($this->next_retry_at);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationSeconds(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get human-readable duration
     */
    public function getDuration(): string
    {
        $seconds = $this->getDurationSeconds();

        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Scope to get pending executions ready to run
     */
    public function scopeReadyToRun($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope to get executions by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get executions for a device
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
}
