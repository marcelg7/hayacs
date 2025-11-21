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
        Schema::create('backup_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // WiFi, Port Forwarding, General, etc.
            $table->json('template_data'); // Parameter values to apply
            $table->json('parameter_patterns')->nullable(); // Which parameters to include (supports wildcards)
            $table->string('device_model_filter')->nullable(); // Filter for specific device models
            $table->json('tags')->nullable();
            $table->string('created_by_device_id')->nullable(); // Source device (matches devices.id type)
            $table->boolean('is_public')->default(false); // For future multi-tenancy
            $table->timestamps();

            // Foreign key to devices table
            $table->foreign('created_by_device_id')
                  ->references('id')
                  ->on('devices')
                  ->onDelete('set null');

            // Indexes
            $table->index('category');
            $table->index('created_by_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_templates');
    }
};
