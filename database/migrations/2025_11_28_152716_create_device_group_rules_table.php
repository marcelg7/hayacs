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
        Schema::create('device_group_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_group_id')->constrained()->cascadeOnDelete();
            $table->string('field', 100); // Device field to match
            $table->enum('operator', [
                'equals',
                'not_equals',
                'contains',
                'not_contains',
                'starts_with',
                'ends_with',
                'less_than',
                'greater_than',
                'less_than_or_equals',
                'greater_than_or_equals',
                'regex',
                'in',
                'not_in',
                'is_null',
                'is_not_null'
            ]);
            $table->text('value')->nullable(); // Value to compare (JSON for in/not_in)
            $table->integer('order')->default(0); // Rule evaluation order
            $table->timestamps();

            $table->index(['device_group_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_group_rules');
    }
};
