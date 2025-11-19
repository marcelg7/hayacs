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
        Schema::create('parameter_history', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->string('parameter_name')->index();
            $table->text('parameter_value');
            $table->string('parameter_type')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'parameter_name', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameter_history');
    }
};
