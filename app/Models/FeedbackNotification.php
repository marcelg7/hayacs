<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feedback_id',
        'type',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Notification type constants
     */
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_COMMENT_ADDED = 'comment_added';
    public const TYPE_REPLY_ADDED = 'reply_added';
    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_MENTIONED = 'mentioned';

    /**
     * Get the user this notification is for
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the feedback this notification is about
     */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    /**
     * Mark this notification as read
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Mark this notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }

    /**
     * Get the icon for this notification type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_STATUS_CHANGED => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            self::TYPE_COMMENT_ADDED => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            self::TYPE_REPLY_ADDED => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
            self::TYPE_ASSIGNED => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            self::TYPE_MENTIONED => 'M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207',
            default => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        };
    }

    /**
     * Get the color for this notification type
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_STATUS_CHANGED => 'text-green-500',
            self::TYPE_COMMENT_ADDED => 'text-blue-500',
            self::TYPE_REPLY_ADDED => 'text-purple-500',
            self::TYPE_ASSIGNED => 'text-yellow-500',
            self::TYPE_MENTIONED => 'text-orange-500',
            default => 'text-gray-500',
        };
    }

    /**
     * Create a status changed notification
     */
    public static function createStatusChanged(Feedback $feedback, string $oldStatus, string $newStatus): self
    {
        $message = "Status changed from {$oldStatus} to {$newStatus}";

        return self::create([
            'user_id' => $feedback->user_id,
            'feedback_id' => $feedback->id,
            'type' => self::TYPE_STATUS_CHANGED,
            'message' => $message,
        ]);
    }

    /**
     * Create a comment added notification
     */
    public static function createCommentAdded(Feedback $feedback, FeedbackComment $comment): self
    {
        $commenterName = $comment->user->name;
        $message = "{$commenterName} commented on your feedback";

        return self::create([
            'user_id' => $feedback->user_id,
            'feedback_id' => $feedback->id,
            'type' => self::TYPE_COMMENT_ADDED,
            'message' => $message,
        ]);
    }

    /**
     * Create a reply notification
     */
    public static function createReplyAdded(FeedbackComment $parentComment, FeedbackComment $reply): self
    {
        $replierName = $reply->user->name;
        $message = "{$replierName} replied to your comment";

        return self::create([
            'user_id' => $parentComment->user_id,
            'feedback_id' => $parentComment->feedback_id,
            'type' => self::TYPE_REPLY_ADDED,
            'message' => $message,
        ]);
    }

    /**
     * Create an assignment notification
     */
    public static function createAssigned(Feedback $feedback, User $assignee): self
    {
        $message = "You have been assigned to feedback: {$feedback->title}";

        return self::create([
            'user_id' => $assignee->id,
            'feedback_id' => $feedback->id,
            'type' => self::TYPE_ASSIGNED,
            'message' => $message,
        ]);
    }
}
