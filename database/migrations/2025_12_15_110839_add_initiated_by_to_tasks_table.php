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
        Schema::table('tasks', function (Blueprint $table) {
            // User who initiated the task (null = ACS/system initiated)
            $table->foreignId('initiated_by_user_id')
                ->nullable()
                ->after('device_id')
                ->constrained('users')
                ->nullOnDelete();

            // Index for filtering by user
            $table->index('initiated_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['initiated_by_user_id']);
            $table->dropColumn('initiated_by_user_id');
        });
    }
};
