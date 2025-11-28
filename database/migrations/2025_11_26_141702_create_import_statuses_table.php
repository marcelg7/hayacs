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
        Schema::create('import_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('subscriber'); // Type of import
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('filename')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('subscribers_created')->default(0);
            $table->unsignedInteger('subscribers_updated')->default(0);
            $table->unsignedInteger('equipment_created')->default(0);
            $table->unsignedInteger('devices_linked')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_statuses');
    }
};
