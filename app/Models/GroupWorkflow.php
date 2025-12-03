<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_group_id',
        'name',
        'description',
        'task_type',
        'task_parameters',
        'schedule_type',
        'schedule_config',
        'rate_limit',
        'max_concurrent',
        'retry_count',
        'retry_delay_minutes',
        'stop_on_failure_percent',
        'is_active',
        'priority',
        'run_once_per_device',
        'depends_on_workflow_id',
        'status',
        'started_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'task_parameters' => 'array',
        'schedule_config' => 'array',
        'rate_limit' => 'integer',
        'max_concurrent' => 'integer',
        'retry_count' => 'integer',
        'retry_delay_minutes' => 'integer',
        'stop_on_failure_percent' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'run_once_per_device' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Available task types
     */
    public const TASK_TYPES = [
        'firmware_upgrade' => 'Firmware Upgrade',
        'set_parameter_values' => 'Set Parameters',
        'get_parameter_values' => 'Get Parameters',
        'reboot' => 'Reboot Device',
        'factory_reset' => 'Factory Reset',
        'backup' => 'Create Backup',
        'restore' => 'Restore from Backup',
        'download' => 'Download File',
        'upload' => 'Upload File',
        // TR-181 Migration specific tasks (Nokia Beacon G6)
        'version_check' => 'Version Check (Skip if meets criteria)',
        'datamodel_check' => 'Datamodel Check (Skip if TR-181)',
        'transition_backup' => 'Transition Backup (Pre-migration)',
        'extract_wifi_ssh' => 'Extract WiFi Config via SSH',
        'hayacs_tr181_preconfig' => 'Load HayACS TR-181 Pre-config',
        'wifi_restore_ssh' => 'Restore WiFi from SSH Config',
        'combined_restore' => 'Combined Restore (WiFi SSH + TR-069 Backup)',
        // Legacy Corteca tasks (for reference)
        'corteca_preconfig' => 'Load Corteca Pre-config (Custom URL)',
        'corteca_restore' => 'Restore After Migration',
    ];

    /**
     * Available schedule types
     */
    public const SCHEDULE_TYPES = [
        'immediate' => 'Run Immediately',
        'scheduled' => 'Run at Scheduled Time',
        'recurring' => 'Recurring Schedule',
        'on_connect' => 'Run When Device Connects',
    ];

    /**
     * Workflow statuses
     */
    public const STATUSES = [
        'draft' => 'Draft',
        'active' => 'Active',
        'paused' => 'Paused',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The device group this workflow belongs to
     */
    public function deviceGroup(): BelongsTo
    {
        return $this->belongsTo(DeviceGroup::class);
    }

    /**
     * Executions for this workflow
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Logs for this workflow
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class);
    }

    /**
     * Workflow this depends on
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(GroupWorkflow::class, 'depends_on_workflow_id');
    }

    /**
     * Workflows that depend on this one
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(GroupWorkflow::class, 'depends_on_workflow_id');
    }

    /**
     * User who created this workflow
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this workflow
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if this workflow can run for a given device
     */
    public function canRunForDevice(Device $device): bool
    {
        // Must be active
        if (!$this->is_active || $this->status !== 'active') {
            return false;
        }

        // Check if device matches group
        if (!$this->deviceGroup->matchesDevice($device)) {
            return false;
        }

        // Check run_once_per_device
        if ($this->run_once_per_device) {
            $existingExecution = $this->executions()
                ->where('device_id', $device->id)
                ->whereIn('status', ['completed', 'in_progress', 'queued'])
                ->exists();

            if ($existingExecution) {
                return false;
            }
        }

        // Check dependency
        if ($this->depends_on_workflow_id) {
            $dependencyCompleted = WorkflowExecution::where('group_workflow_id', $this->depends_on_workflow_id)
                ->where('device_id', $device->id)
                ->where('status', 'completed')
                ->exists();

            if (!$dependencyCompleted) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if this workflow should run now based on schedule
     */
    public function shouldRunNow(): bool
    {
        if (!$this->is_active || $this->status !== 'active') {
            return false;
        }

        return match ($this->schedule_type) {
            'immediate' => true,
            'on_connect' => false, // Handled by device connect event
            'scheduled' => $this->isWithinScheduledWindow(),
            'recurring' => $this->isWithinRecurringWindow(),
            default => false,
        };
    }

    /**
     * Check if current time is within scheduled window
     */
    private function isWithinScheduledWindow(): bool
    {
        $config = $this->schedule_config ?? [];

        if (empty($config['start_at'])) {
            return false;
        }

        $startAt = new \DateTime($config['start_at']);
        $now = now();

        // Must be after start time
        if ($now < $startAt) {
            return false;
        }

        // Check end time if specified
        if (!empty($config['end_at'])) {
            $endAt = new \DateTime($config['end_at']);
            if ($now > $endAt) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if current time is within recurring window
     */
    private function isWithinRecurringWindow(): bool
    {
        $config = $this->schedule_config ?? [];

        if (empty($config['days']) || empty($config['start_time']) || empty($config['end_time'])) {
            return false;
        }

        $timezone = $config['timezone'] ?? 'UTC';
        $now = now($timezone);
        $currentDay = strtolower($now->format('D')); // mon, tue, etc.
        $currentTime = $now->format('H:i');

        // Check if today is an allowed day
        $allowedDays = array_map('strtolower', $config['days']);
        if (!in_array($currentDay, $allowedDays)) {
            return false;
        }

        // Check if within time window
        return $currentTime >= $config['start_time'] && $currentTime <= $config['end_time'];
    }

    /**
     * Get execution statistics
     */
    public function getStats(): array
    {
        $stats = $this->executions()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $stats['pending'] ?? 0,
            'queued' => $stats['queued'] ?? 0,
            'in_progress' => $stats['in_progress'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'skipped' => $stats['skipped'] ?? 0,
            'cancelled' => $stats['cancelled'] ?? 0,
            'total' => array_sum($stats),
        ];
    }

    /**
     * Calculate progress percentage
     */
    public function getProgressPercent(): float
    {
        $stats = $this->getStats();
        $total = $stats['total'];

        if ($total === 0) {
            return 0;
        }

        $finished = $stats['completed'] + $stats['failed'] + $stats['skipped'] + $stats['cancelled'];
        return round(($finished / $total) * 100, 1);
    }

    /**
     * Check if workflow is currently running
     */
    public function isRunning(): bool
    {
        return $this->status === 'active' && $this->executions()
            ->whereIn('status', ['pending', 'queued', 'in_progress'])
            ->exists();
    }

    /**
     * Scope to get active workflows
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope to get workflows by schedule type
     */
    public function scopeBySchedule($query, string $type)
    {
        return $query->where('schedule_type', $type);
    }
}
