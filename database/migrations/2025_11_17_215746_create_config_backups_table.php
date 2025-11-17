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
        Schema::create('config_backups', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('backup_data'); // JSON of all parameters
            $table->boolean('is_auto')->default(false); // Auto-created vs manual
            $table->integer('parameter_count')->default(0); // Number of params backed up
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
        Schema::dropIfExists('config_backups');
    }
};
