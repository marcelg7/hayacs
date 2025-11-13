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
        Schema::create('devices', function (Blueprint $table) {
            $table->string('id')->primary(); // Device ID from TR-069 (OUI-ProductClass-SerialNumber)
            $table->string('manufacturer')->nullable();
            $table->string('oui')->nullable(); // Organizationally Unique Identifier
            $table->string('product_class')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('hardware_version')->nullable();
            $table->string('software_version')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('connection_request_url')->nullable();
            $table->string('connection_request_username')->nullable();
            $table->string('connection_request_password')->nullable();
            $table->boolean('online')->default(false);
            $table->timestamp('last_inform')->nullable();
            $table->json('tags')->nullable(); // For device grouping/categorization
            $table->timestamps();

            $table->index('manufacturer');
            $table->index('product_class');
            $table->index('online');
            $table->index('last_inform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
