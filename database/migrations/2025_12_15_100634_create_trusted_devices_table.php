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
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique(); // Secure random token stored in cookie
            $table->string('fingerprint_hash', 64); // SHA-256 hash of device fingerprint
            $table->string('device_name')->nullable(); // User-friendly name (from User-Agent parsing)
            $table->string('ip_address', 45)->nullable(); // Last known IP (for logging)
            $table->timestamp('trusted_at'); // When device was trusted
            $table->timestamp('expires_at'); // Auto-expiry date (90 days by default)
            $table->timestamp('last_used_at')->nullable(); // Last successful use
            $table->boolean('revoked')->default(false); // Admin/user can revoke
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by')->nullable(); // admin email or 'user'
            $table->timestamps();

            // Indexes for efficient lookups
            $table->index(['user_id', 'revoked', 'expires_at']);
            $table->index(['token', 'revoked', 'expires_at']);
        });

        // Track trusted device access logs for security auditing
        Schema::create('trusted_device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trusted_device_id')->constrained()->onDelete('cascade');
            $table->string('action'); // 'login_bypass', 'two_fa_skip', 'created', 'revoked'
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('fingerprint_matched')->default(true);
            $table->timestamp('created_at');

            $table->index(['trusted_device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_device_logs');
        Schema::dropIfExists('trusted_devices');
    }
};
