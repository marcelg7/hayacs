<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores per-device SSH credentials for Nokia Beacon G6 devices.
     * These are factory credentials provided by Nokia for each device.
     */
    public function up(): void
    {
        Schema::create('device_ssh_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->string('serial_number')->index();

            // Layer 1: SSH/VTY access credentials
            $table->string('ssh_username')->default('superadmin');
            $table->text('ssh_password_encrypted'); // Factory superadmin password (encrypted)

            // Layer 2: Shell access password (for 'shell' command in VTY)
            $table->text('shell_password_encrypted')->nullable(); // Password2 (encrypted)

            // SSH connection details (may differ from TR-069 connection request)
            $table->integer('ssh_port')->default(22);

            // Status tracking
            $table->boolean('verified')->default(false); // Has SSH been tested?
            $table->timestamp('last_ssh_success')->nullable();
            $table->timestamp('last_ssh_failure')->nullable();
            $table->text('last_error')->nullable();

            // Source tracking
            $table->string('credential_source')->nullable(); // 'nokia_spreadsheet', 'manual', etc.
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            $table->foreign('device_id')
                ->references('id')
                ->on('devices')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_ssh_credentials');
    }
};
