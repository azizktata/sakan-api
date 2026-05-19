<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Superseded by 2026_05_19_000001_create_properties_clean_table.php
 * which creates the full properties_clean schema including amenities_labels.
 * Kept as a no-op so existing deployments where this already ran are unaffected.
 */
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
