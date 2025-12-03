<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_workflow_id',
        'workflow_execution_id',
        'device_id',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Log levels
     */
    public const LEVELS = [
        'info' => 'Info',
        'warning' => 'Warning',
        'error' => 'Error',
    ];

    /**
     * The workflow this log belongs to
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(GroupWorkflow::class, 'group_workflow_id');
    }

    /**
     * The execution this log belongs to
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /**
     * The device this log is about
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    /**
     * Create an info log
     */
    public static function info(
        ?int $workflowId,
        string $message,
        ?int $executionId = null,
        ?string $deviceId = null,
        array $context = []
    ): self {
        return static::create([
            'group_workflow_id' => $workflowId,
            'workflow_execution_id' => $executionId,
            'device_id' => $deviceId,
            'level' => 'info',
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Create a warning log
     */
    public static function warning(
        ?int $workflowId,
        string $message,
        ?int $executionId = null,
        ?string $deviceId = null,
        array $context = []
    ): self {
        return static::create([
            'group_workflow_id' => $workflowId,
            'workflow_execution_id' => $executionId,
            'device_id' => $deviceId,
            'level' => 'warning',
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Create an error log
     */
    public static function error(
        ?int $workflowId,
        string $message,
        ?int $executionId = null,
        ?string $deviceId = null,
        array $context = []
    ): self {
        return static::create([
            'group_workflow_id' => $workflowId,
            'workflow_execution_id' => $executionId,
            'device_id' => $deviceId,
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Scope to get logs by level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get recent logs
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
