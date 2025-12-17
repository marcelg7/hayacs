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
            // For mesh APs: store the port-forwarded connection request URL
            // This is the gateway's external IP + forwarded port
            $table->string('mesh_forwarded_url')->nullable()->after('udp_connection_request_address');

            // Store the external port used on the gateway for this mesh AP
            $table->unsignedInteger('mesh_forward_port')->nullable()->after('mesh_forwarded_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['mesh_forwarded_url', 'mesh_forward_port']);
        });
    }
};
