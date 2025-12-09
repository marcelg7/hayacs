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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['bug', 'feedback', 'feature_request'])->default('feedback');
            $table->string('title');
            $table->text('description'); // TinyMCE rich text content
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedInteger('upvotes')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
