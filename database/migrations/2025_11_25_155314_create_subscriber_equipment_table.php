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
        Schema::create('subscriber_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('customer')->nullable()->index(); // For matching during import
            $table->string('account')->nullable()->index(); // For matching during import
            $table->string('agreement')->nullable();
            $table->string('equip_item')->nullable(); // Equipment item code (e.g., "844E", "SR505N")
            $table->string('equip_desc')->nullable(); // Equipment description
            $table->date('start_date')->nullable(); // Equipment start date
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable()->index(); // Device serial number
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriber_equipment');
    }
};
