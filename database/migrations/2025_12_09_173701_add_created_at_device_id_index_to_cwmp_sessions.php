<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a composite index on (created_at, device_id) to improve the
     * excessive informs report query performance. This query scans all
     * sessions in the last 24 hours and groups by device_id.
     *
     * Before: ~150 seconds on 920k rows
     * After: Expected <1 second
     */
    public function up(): void
    {
        Schema::table('cwmp_sessions', function (Blueprint $table) {
            // Composite index for: WHERE created_at >= ? GROUP BY device_id
            $table->index(['created_at', 'device_id'], 'cwmp_sessions_created_at_device_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cwmp_sessions', function (Blueprint $table) {
            $table->dropIndex('cwmp_sessions_created_at_device_id_index');
        });
    }
};
