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
        Schema::create('device_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_inform_at')->nullable();
            $table->integer('connection_uptime_seconds')->nullable();
            $table->integer('inform_interval')->nullable();
            $table->timestamp('snapshot_at')->index();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'snapshot_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_health_snapshots');
    }
};
