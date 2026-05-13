<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add amenities_labels JSON column to properties_clean.
     *
     * properties_clean is managed by the Python scraper (not Laravel),
     * so we use raw SQL rather than Schema::table to avoid Blueprint
     * type confusion with scraper-managed tables.
     *
     * Backward-compatible: column is nullable, existing rows get NULL.
     */
    public function up(): void
    {
        // Only run if the table exists (created by the Python scraper)
        if (!Schema::hasTable('properties_clean')) {
            return;
        }

        if (!Schema::hasColumn('properties_clean', 'amenities_labels')) {
            DB::statement('
                ALTER TABLE properties_clean
                ADD COLUMN amenities_labels JSON NULL
                AFTER amenities
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('properties_clean') && Schema::hasColumn('properties_clean', 'amenities_labels')) {
            DB::statement('
                ALTER TABLE properties_clean
                DROP COLUMN amenities_labels
            ');
        }
    }
};
