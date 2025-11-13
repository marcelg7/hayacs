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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('task_type'); // get_params, set_params, reboot, factory_reset, download, upload
            $table->json('parameters')->nullable(); // Task-specific parameters
            $table->string('status')->default('pending'); // pending, sent, completed, failed
            $table->json('result')->nullable(); // Store task results
            $table->text('error')->nullable(); // Error message if failed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index('device_id');
            $table->index('status');
            $table->index('task_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
