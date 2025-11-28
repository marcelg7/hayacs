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
        Schema::create('device_wifi_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->string('ssid');
            $table->string('main_password');
            $table->string('guest_ssid')->nullable();
            $table->string('guest_password')->nullable();
            $table->boolean('guest_enabled')->default(false);
            $table->string('set_by')->nullable(); // User who configured it
            $table->timestamps();

            // Each device has one set of WiFi credentials (upsert on update)
            $table->unique('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_wifi_credentials');
    }
};
