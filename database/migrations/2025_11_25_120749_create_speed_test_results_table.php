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
        Schema::create('speed_test_results', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->decimal('download_speed_mbps', 10, 2)->nullable();
            $table->decimal('upload_speed_mbps', 10, 2)->nullable();
            $table->bigInteger('download_bytes')->nullable();
            $table->bigInteger('upload_bytes')->nullable();
            $table->integer('download_duration_ms')->nullable();
            $table->integer('upload_duration_ms')->nullable();
            $table->string('download_state')->nullable();
            $table->string('upload_state')->nullable();
            $table->timestamp('download_start_time')->nullable();
            $table->timestamp('download_end_time')->nullable();
            $table->timestamp('upload_start_time')->nullable();
            $table->timestamp('upload_end_time')->nullable();
            $table->string('test_type')->default('both'); // download, upload, both
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speed_test_results');
    }
};
