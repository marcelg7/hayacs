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
        Schema::create('speedtest_results', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->bigInteger('download_speed_kbps')->nullable();
            $table->bigInteger('upload_speed_kbps')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->integer('jitter_ms')->nullable();
            $table->decimal('packet_loss_percent', 5, 2)->nullable();
            $table->integer('test_duration_seconds')->nullable();
            $table->string('test_server_url')->nullable();
            $table->string('diagnostics_state')->default('Requested');
            $table->timestamp('rom_time')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->index(['device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speedtest_results');
    }
};
