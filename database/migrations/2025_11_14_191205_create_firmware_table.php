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
        Schema::create('firmware', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_type_id')->constrained('device_types')->onDelete('cascade');
            $table->string('version'); // e.g., "1.2.3", "R2.11.8"
            $table->string('file_name'); // Original uploaded filename
            $table->string('file_path'); // Path in storage (relative)
            $table->bigInteger('file_size')->nullable(); // Size in bytes
            $table->string('file_hash')->nullable(); // SHA256 hash for integrity
            $table->text('release_notes')->nullable();
            $table->boolean('is_active')->default(false); // Recommended version for upgrades
            $table->string('download_url')->nullable(); // Full URL for TR-069 Download RPC
            $table->timestamps();

            // Index for lookups
            $table->index(['device_type_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firmware');
    }
};
