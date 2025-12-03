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
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_workflow_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 150); // Device ID (not foreign key since devices table uses string PK)

            // Link to actual task
            $table->unsignedBigInteger('task_id')->nullable();

            // Status tracking
            $table->enum('status', [
                'pending',      // Waiting to be scheduled
                'queued',       // Task created, waiting to be sent
                'in_progress',  // Task sent to device
                'completed',    // Successfully completed
                'failed',       // Failed after all retries
                'skipped',      // Skipped (dependency not met, device offline, etc.)
                'cancelled'     // Manually cancelled
            ])->default('pending');

            // Timing
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Retry tracking
            $table->integer('attempt')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            // Result details
            $table->json('result')->nullable(); // Success/error details

            $table->timestamps();

            // Unique constraint - one execution per device per workflow
            $table->unique(['group_workflow_id', 'device_id']);

            // Indexes for queries
            $table->index(['group_workflow_id', 'status']);
            $table->index(['device_id', 'status']);
            $table->index('scheduled_at');
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
