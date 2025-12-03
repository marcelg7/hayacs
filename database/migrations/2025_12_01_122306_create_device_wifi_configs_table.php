<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores WiFi configuration extracted via SSH from Nokia devices.
     * This includes the plaintext passwords that TR-069 masks.
     */
    public function up(): void
    {
        Schema::create('device_wifi_configs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();

            // WiFi interface identification
            $table->string('interface_name'); // e.g., 'ath0', 'ath1', 'ath01'
            $table->string('radio'); // 'wifi0' (5GHz) or 'wifi1' (2.4GHz)
            $table->enum('band', ['2.4GHz', '5GHz', '6GHz'])->default('5GHz');

            // SSID configuration
            $table->string('ssid');
            $table->text('password_encrypted')->nullable(); // WiFi password (encrypted)
            $table->string('encryption')->nullable(); // e.g., 'mixed-psk+tkip+ccmp', 'psk2+ccmp'
            $table->boolean('hidden')->default(false);
            $table->boolean('enabled')->default(true);

            // Network type classification
            $table->enum('network_type', ['primary', 'secondary', 'guest', 'backhaul', 'other'])->default('primary');
            $table->boolean('is_mesh_backhaul')->default(false);

            // Additional settings
            $table->integer('max_clients')->nullable();
            $table->boolean('client_isolation')->default(false);
            $table->boolean('wps_enabled')->default(false);
            $table->string('mac_address')->nullable(); // VAP MAC

            // Raw UCI config for full restoration
            $table->text('raw_uci_config')->nullable();

            // Extraction metadata
            $table->timestamp('extracted_at')->nullable();
            $table->string('extraction_method')->default('ssh'); // 'ssh', 'tr069', 'manual'
            $table->string('data_model')->nullable(); // 'TR-098' or 'TR-181' at time of extraction

            // For migration tracking
            $table->boolean('migrated_to_tr181')->default(false);
            $table->timestamp('migrated_at')->nullable();

            $table->timestamps();

            $table->foreign('device_id')
                ->references('id')
                ->on('devices')
                ->onDelete('cascade');

            // Unique constraint per interface per device
            $table->unique(['device_id', 'interface_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_wifi_configs');
    }
};
