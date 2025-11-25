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
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('customer')->nullable()->index(); // Customer ID from Ivue
            $table->string('account')->nullable()->index(); // Account ID from Ivue
            $table->string('agreement')->nullable(); // Agreement ID from Ivue
            $table->string('name'); // Customer name
            $table->string('service_type')->nullable(); // e.g., "Internet Fibre", "Internet DSL"
            $table->date('connection_date')->nullable(); // Connection date
            $table->timestamps();

            // Unique constraint on customer + account
            $table->unique(['customer', 'account']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
