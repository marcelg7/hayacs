<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add fulltext index on parameters.name for faster search.
     * MySQL fulltext search is much faster than LIKE '%...%' for large tables.
     */
    public function up(): void
    {
        // Add fulltext index on name column
        // Note: We only index 'name' as 'value' can contain arbitrary data that doesn't benefit from fulltext
        DB::statement('ALTER TABLE parameters ADD FULLTEXT INDEX parameters_name_fulltext (name)');

        // Add index on value for prefix searches (LIKE 'term%')
        // This helps with exact value matches and prefix searches
        Schema::table('parameters', function (Blueprint $table) {
            // Index first 100 chars of value for efficient prefix searches
            $table->index([DB::raw('value(100)')], 'parameters_value_prefix_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parameters', function (Blueprint $table) {
            $table->dropIndex('parameters_name_fulltext');
            $table->dropIndex('parameters_value_prefix_index');
        });
    }
};
