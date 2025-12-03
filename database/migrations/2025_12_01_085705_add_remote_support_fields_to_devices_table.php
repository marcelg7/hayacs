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
        Schema::table('devices', function (Blueprint $table) {
            // Rename remote_gui_enabled_at to remote_support_expires_at for clarity
            // and add who enabled it for audit trail
            if (Schema::hasColumn('devices', 'remote_gui_enabled_at')) {
                $table->renameColumn('remote_gui_enabled_at', 'remote_support_expires_at');
            }

            // Who enabled remote support (for audit trail)
            if (!Schema::hasColumn('devices', 'remote_support_enabled_by')) {
                $table->unsignedBigInteger('remote_support_enabled_by')->nullable()->after('auto_provisioned');
                $table->foreign('remote_support_enabled_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'remote_support_enabled_by')) {
                $table->dropForeign(['remote_support_enabled_by']);
                $table->dropColumn('remote_support_enabled_by');
            }

            if (Schema::hasColumn('devices', 'remote_support_expires_at')) {
                $table->renameColumn('remote_support_expires_at', 'remote_gui_enabled_at');
            }
        });
    }
};
