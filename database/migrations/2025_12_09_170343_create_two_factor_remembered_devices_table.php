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
        Schema::create('two_factor_remembered_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique(); // Secure random token stored in cookie
            $table->string('device_name')->nullable(); // Browser/OS info for display
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_remembered_devices');
    }
};
