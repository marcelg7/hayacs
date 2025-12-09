<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'status',
        'priority',
        'upvotes',
        'assigned_to',
        'resolved_at',
        'slack_message_ts',
    ];

    /**
     * Status labels for external use
     */
    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'upvotes' => 'integer',
    ];

    /**
     * Type constants
     */
    public const TYPE_BUG = 'bug';
    public const TYPE_FEEDBACK = 'feedback';
    public const TYPE_FEATURE_REQUEST = 'feature_request';

    /**
     * Status constants
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    /**
     * Priority constants
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    /**
     * Get the user who created this feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user this feedback is assigned to
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get all comments for this feedback
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class);
    }

    /**
     * Get only root-level comments (not replies)
     */
    public function rootComments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class)->whereNull('parent_id');
    }

    /**
     * Get all notifications for this feedback
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(FeedbackNotification::class);
    }

    /**
     * Get all upvotes for this feedback
     */
    public function upvoteRecords(): HasMany
    {
        return $this->hasMany(FeedbackUpvote::class);
    }

    /**
     * Check if a user has upvoted this feedback
     */
    public function hasUpvotedBy(User $user): bool
    {
        return $this->upvoteRecords()->where('user_id', $user->id)->exists();
    }

    /**
     * Toggle upvote for a user
     */
    public function toggleUpvote(User $user): bool
    {
        $existing = $this->upvoteRecords()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $this->decrement('upvotes');
            return false; // Removed upvote
        }

        $this->upvoteRecords()->create(['user_id' => $user->id]);
        $this->increment('upvotes');
        return true; // Added upvote
    }

    /**
     * Get type label for display
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BUG => 'Bug Report',
            self::TYPE_FEEDBACK => 'General Feedback',
            self::TYPE_FEATURE_REQUEST => 'Feature Request',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get type badge color class
     */
    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BUG => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            self::TYPE_FEEDBACK => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::TYPE_FEATURE_REQUEST => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    /**
     * Get status label for display
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status badge color class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::STATUS_IN_PROGRESS => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::STATUS_RESOLVED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    /**
     * Get priority label for display
     */
    public function getPriorityLabelAttribute(): string
    {
        return ucfirst($this->priority);
    }

    /**
     * Get priority badge color class
     */
    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::PRIORITY_MEDIUM => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::PRIORITY_HIGH => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            self::PRIORITY_CRITICAL => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_BUG => 'Bug Report',
            self::TYPE_FEEDBACK => 'General Feedback',
            self::TYPE_FEATURE_REQUEST => 'Feature Request',
        ];
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
        ];
    }

    /**
     * Get all available priorities
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical',
        ];
    }
}
