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
        Schema::table('devices', function (Blueprint $table) {
            $table->string('xmpp_jid')->nullable()->after('stun_enabled');
            $table->boolean('xmpp_enabled')->default(false)->after('xmpp_jid');
            $table->timestamp('xmpp_last_seen')->nullable()->after('xmpp_enabled');
            $table->string('xmpp_status', 50)->nullable()->after('xmpp_last_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['xmpp_jid', 'xmpp_enabled', 'xmpp_last_seen', 'xmpp_status']);
        });
    }
};
