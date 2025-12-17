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
            // Parent device ID for mesh topology (e.g., Beacon 2/3.1 APs connected to Beacon G6 gateway)
            // Using string to match devices.id which is varchar(255)
            $table->string('parent_device_id')->nullable()->after('subscriber_id');

            // MAC address of parent device (for matching when parent not yet in database)
            $table->string('parent_mac_address', 17)->nullable()->after('parent_device_id');

            // Timestamp when mesh relationship was last updated
            $table->timestamp('mesh_updated_at')->nullable()->after('parent_mac_address');

            // Add indexes for faster lookups
            $table->index('parent_device_id');
            $table->index('parent_mac_address');
        });

        // Add foreign key constraint
        Schema::table('devices', function (Blueprint $table) {
            $table->foreign('parent_device_id')
                ->references('id')
                ->on('devices')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['parent_device_id']);
            $table->dropIndex(['parent_device_id']);
            $table->dropIndex(['parent_mac_address']);
            $table->dropColumn(['parent_device_id', 'parent_mac_address', 'mesh_updated_at']);
        });
    }
};
