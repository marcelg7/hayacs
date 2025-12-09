<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('feedback_id')->constrained('feedbacks')->onDelete('cascade');
            $table->enum('type', [
                'status_changed',      // Feedback status was updated
                'comment_added',       // New comment on feedback
                'reply_added',         // Reply to user's comment
                'assigned',            // Feedback assigned to user (staff)
                'mentioned',           // User mentioned in comment
            ]);
            $table->string('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_notifications');
    }
};
