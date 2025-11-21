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
        Schema::table('config_backups', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('description'); // Array of tag strings
            $table->text('notes')->nullable()->after('tags'); // Custom notes/comments
            $table->boolean('is_starred')->default(false)->after('notes'); // Favorite/important flag
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_backups', function (Blueprint $table) {
            $table->dropColumn(['tags', 'notes', 'is_starred']);
        });
    }
};
