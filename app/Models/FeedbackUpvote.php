<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackUpvote extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_id',
        'user_id',
    ];

    /**
     * Get the feedback this upvote is for
     */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    /**
     * Get the user who upvoted
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
