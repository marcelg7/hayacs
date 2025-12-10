<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds index on serial_number column to speed up device-subscriber linking.
     * Without this index, the JOIN in linkDevicesToSubscribers() takes 280+ seconds.
     * With this index, it takes less than 1 second.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->index('serial_number', 'devices_serial_number_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_serial_number_index');
        });
    }
};
