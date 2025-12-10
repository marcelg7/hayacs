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
        Schema::table('users', function (Blueprint $table) {
            // TOTP secret key (encrypted in model)
            $table->text('two_factor_secret')->nullable()->after('password');

            // When 2FA was enabled (null = not enabled)
            $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_secret');

            // When the 14-day grace period started
            $table->timestamp('two_factor_grace_started_at')->nullable()->after('two_factor_enabled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_enabled_at',
                'two_factor_grace_started_at',
            ]);
        });
    }
};
