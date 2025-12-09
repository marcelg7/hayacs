<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index on (status, created_at) for fast task status searches
     * with ORDER BY created_at DESC LIMIT N.
     *
     * Also adds composite index on (task_type, created_at) for task type searches.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Composite index for status + created_at sorting
            // This allows "WHERE status = 'completed' ORDER BY created_at DESC LIMIT 10"
            // to be satisfied entirely from the index (no filesort)
            $table->index(['status', 'created_at'], 'tasks_status_created_at_index');

            // Composite index for task_type + created_at sorting
            // This allows "WHERE task_type = 'reboot' ORDER BY created_at DESC LIMIT 10"
            // to be satisfied entirely from the index
            $table->index(['task_type', 'created_at'], 'tasks_task_type_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_status_created_at_index');
            $table->dropIndex('tasks_task_type_created_at_index');
        });
    }
};
