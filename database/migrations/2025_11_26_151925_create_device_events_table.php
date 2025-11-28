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
        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 255);
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->string('event_code', 50);  // e.g., "0 BOOTSTRAP", "1 BOOT"
            $table->string('event_type', 50)->nullable();  // Normalized: bootstrap, boot, periodic, etc.
            $table->string('command_key')->nullable();  // For TRANSFER COMPLETE correlation
            $table->text('details')->nullable();  // JSON for additional event data
            $table->string('source_ip', 45)->nullable();  // Device IP at time of event
            $table->string('session_id')->nullable();  // CWMP session ID
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
            $table->index(['device_id', 'event_type']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_events');
    }
};
