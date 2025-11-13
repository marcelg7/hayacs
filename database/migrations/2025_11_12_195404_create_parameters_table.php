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
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->text('name'); // Full parameter path (e.g., InternetGatewayDevice.DeviceInfo.SoftwareVersion)
            $table->text('value')->nullable();
            $table->string('type')->nullable(); // xsd:string, xsd:boolean, xsd:int, etc.
            $table->boolean('writable')->default(false);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index('device_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};
