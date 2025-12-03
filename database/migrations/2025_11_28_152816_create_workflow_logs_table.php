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
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_workflow_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_execution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('device_id', 150)->nullable();
            $table->enum('level', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->json('context')->nullable(); // Additional context data
            $table->timestamps();

            $table->index('group_workflow_id');
            $table->index('workflow_execution_id');
            $table->index('device_id');
            $table->index(['level', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};
