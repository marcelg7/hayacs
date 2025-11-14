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
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Calix 844E-1"
            $table->string('manufacturer')->nullable(); // e.g., "Calix"
            $table->string('product_class')->nullable(); // Used to match devices
            $table->string('oui')->nullable(); // Organizationally Unique Identifier
            $table->text('description')->nullable();
            $table->timestamps();

            // Index for faster lookups
            $table->index(['manufacturer', 'product_class']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_types');
    }
};
