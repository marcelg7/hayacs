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
        Schema::create('group_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Task configuration
            $table->string('task_type', 50); // firmware_upgrade, set_parameter_values, reboot, etc.
            $table->json('task_parameters')->nullable(); // Task-specific parameters

            // Scheduling
            $table->enum('schedule_type', ['immediate', 'scheduled', 'recurring', 'on_connect'])->default('immediate');
            $table->json('schedule_config')->nullable(); // Schedule details

            // Rate limiting
            $table->integer('rate_limit')->default(0); // Max devices per hour (0 = unlimited)
            $table->integer('max_concurrent')->default(0); // Max concurrent executions (0 = unlimited)

            // Retry configuration
            $table->integer('retry_count')->default(0);
            $table->integer('retry_delay_minutes')->default(5);

            // Safety
            $table->integer('stop_on_failure_percent')->default(0); // Pause if X% fail (0 = never)

            // Control
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Execution priority
            $table->boolean('run_once_per_device')->default(true);

            // Workflow dependencies
            $table->foreignId('depends_on_workflow_id')->nullable()->constrained('group_workflows')->nullOnDelete();

            // Status
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_group_id', 'is_active']);
            $table->index(['status', 'schedule_type']);
            $table->index('depends_on_workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_workflows');
    }
};
