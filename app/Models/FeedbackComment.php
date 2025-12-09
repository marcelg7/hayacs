<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_id',
        'user_id',
        'parent_id',
        'content',
        'is_staff_response',
    ];

    protected $casts = [
        'is_staff_response' => 'boolean',
    ];

    /**
     * Get the feedback this comment belongs to
     */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    /**
     * Get the user who wrote this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (if this is a reply)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FeedbackComment::class, 'parent_id');
    }

    /**
     * Get all replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(FeedbackComment::class, 'parent_id');
    }

    /**
     * Get all nested replies recursively
     */
    public function allReplies(): HasMany
    {
        return $this->replies()->with('allReplies');
    }

    /**
     * Check if this comment is a reply
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if this is a staff response
     */
    public function isStaffResponse(): bool
    {
        return $this->is_staff_response;
    }

    /**
     * Get the depth level of this comment (0 for root, 1+ for replies)
     */
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $comment = $this;

        while ($comment->parent_id !== null) {
            $depth++;
            $comment = $comment->parent;

            // Prevent infinite loops - max depth of 10
            if ($depth > 10) {
                break;
            }
        }

        return $depth;
    }
}
