<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ML training table for the SAKAN price estimation service.
 *
 * This table is also written by the Python scraper pipeline
 * (scraper/normalize.py, scraper/ta_normalize.py, scraper/seed_to_clean.py).
 * We use raw SQL here to mirror the exact column types the scraper expects,
 * avoiding any Blueprint/Python type mismatch.
 *
 * Pipeline: mubawab_scraper → normalize.py
 *           tunisie_annonce_scraper → geocode_properties.py → ta_normalize.py
 *           properties (website DB) → seed_to_clean.py
 *           All three write to this table.
 *
 * Deduplication:
 *   - Mubawab rows: raw_id (FK to properties_raw) is unique, source_ref NULL
 *   - Tunisie-Annonce rows: source_ref = 'TA-XXXXXXX', raw_id NULL
 *   - Seeded rows: source_ref = 'seeded-{id}', raw_id NULL
 *   MySQL UNIQUE KEY ignores NULLs, so only non-NULL source_ref values are deduplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create table if not already created by the Python scraper
        if (! Schema::hasTable('properties_clean')) {
            DB::statement("
                CREATE TABLE properties_clean (
                    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    raw_id           BIGINT UNSIGNED NULL,
                    price            INT UNSIGNED NOT NULL,
                    surface          SMALLINT UNSIGNED NOT NULL,
                    price_per_m2     DECIMAL(10,2) NOT NULL,
                    city             VARCHAR(64) NOT NULL,
                    governorate      VARCHAR(64) NOT NULL,
                    neighborhood     VARCHAR(128) NULL,
                    zone_score       TINYINT UNSIGNED NOT NULL DEFAULT 3,
                    property_type    VARCHAR(32) NOT NULL,
                    transaction_type VARCHAR(16) NOT NULL,
                    bedrooms         TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    bathrooms        TINYINT UNSIGNED NULL,
                    floor            TINYINT UNSIGNED NULL,
                    condition_state  VARCHAR(16) NOT NULL DEFAULT 'bon_etat',
                    amenities        JSON NULL,
                    amenities_labels JSON NULL,
                    garden_surface   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    parking_spaces   TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    terrace_surface  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    building_age     TINYINT UNSIGNED NULL,
                    latitude         DECIMAL(10,7) NULL,
                    longitude        DECIMAL(10,7) NULL,
                    source           VARCHAR(32) NOT NULL DEFAULT 'mubawab',
                    source_ref       VARCHAR(64) NULL,
                    created_at       DATETIME NOT NULL,
                    UNIQUE KEY uq_raw_id    (raw_id),
                    UNIQUE KEY uq_source_ref (source_ref),
                    INDEX idx_city_type (city, property_type, transaction_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return;
        }

        // Table already exists (created by Python scraper on a running instance).
        // Apply any missing columns idempotently so this migration is safe to run
        // on both fresh installs and existing databases.
        $missing = $this->missingColumns();

        $alterations = [
            'raw_id'           => 'MODIFY COLUMN raw_id BIGINT UNSIGNED NULL',
            'bathrooms'        => 'ADD COLUMN bathrooms TINYINT UNSIGNED NULL AFTER bedrooms',
            'floor'            => 'ADD COLUMN floor TINYINT UNSIGNED NULL AFTER bathrooms',
            'amenities_labels' => 'ADD COLUMN amenities_labels JSON NULL AFTER amenities',
            'garden_surface'   => 'ADD COLUMN garden_surface SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER amenities_labels',
            'parking_spaces'   => 'ADD COLUMN parking_spaces TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER garden_surface',
            'terrace_surface'  => 'ADD COLUMN terrace_surface SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER parking_spaces',
            'building_age'     => 'ADD COLUMN building_age TINYINT UNSIGNED NULL AFTER terrace_surface',
            'latitude'         => 'ADD COLUMN latitude DECIMAL(10,7) NULL AFTER building_age',
            'longitude'        => 'ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
            'source'           => "ADD COLUMN source VARCHAR(32) NOT NULL DEFAULT 'mubawab' AFTER longitude",
            'source_ref'       => 'ADD COLUMN source_ref VARCHAR(64) NULL AFTER source',
        ];

        foreach ($alterations as $col => $clause) {
            // raw_id is a MODIFY (always run to make it nullable); others are ADD (only if absent)
            if ($col === 'raw_id' || in_array($col, $missing)) {
                DB::statement("ALTER TABLE properties_clean {$clause}");
            }
        }

        // Add unique index on source_ref if missing
        $hasSourceRefIndex = DB::selectOne("
            SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'properties_clean'
              AND INDEX_NAME   = 'uq_source_ref'
        ")->n;

        if (! $hasSourceRefIndex) {
            DB::statement('ALTER TABLE properties_clean ADD UNIQUE INDEX uq_source_ref (source_ref)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('properties_clean');
    }

    /** Returns column names that exist in the ALTER list but are absent from the live table. */
    private function missingColumns(): array
    {
        $rows = DB::select("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties_clean'
        ");
        $existing = array_map(fn($r) => $r->COLUMN_NAME, $rows);

        $wanted = [
            'bathrooms', 'floor', 'amenities_labels',
            'garden_surface', 'parking_spaces', 'terrace_surface',
            'building_age', 'latitude', 'longitude', 'source', 'source_ref',
        ];

        return array_values(array_diff($wanted, $existing));
    }
};
