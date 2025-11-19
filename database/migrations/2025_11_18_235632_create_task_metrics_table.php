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
        Schema::create('task_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->nullable()->index();
            $table->string('task_type')->index();
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->index();
            $table->integer('total_tasks')->default(0);
            $table->integer('successful_tasks')->default(0);
            $table->integer('failed_tasks')->default(0);
            $table->integer('avg_execution_time_ms')->nullable();
            $table->text('most_common_error')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'task_type', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_metrics');
    }
};
