<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PropertySeeder extends Seeder
{
    // Demo users that will own the seeded listings
    private const DEMO_USERS = [
        ['name' => 'Agence Tunisie Annonce', 'email' => 'contact@tunisie-annonce-agent.sakan.tn', 'role' => 'agent'],
        ['name' => 'Ahmed Ben Ali',          'email' => 'user1@demo.sakan.tn',                    'role' => 'particulier'],
        ['name' => 'Fatma Chaabane',         'email' => 'user2@demo.sakan.tn',                    'role' => 'particulier'],
        ['name' => 'Mohamed Trabelsi',       'email' => 'user3@demo.sakan.tn',                    'role' => 'agent'],
    ];

    // Human-readable labels for generated titles
    private const TYPE_LABELS = [
        'apartment' => 'Appartement',
        'villa'     => 'Villa',
        'house'     => 'Maison',
        'land'      => 'Terrain',
        'commercial'=> 'Local commercial',
        'office'    => 'Bureau',
    ];

    private const TX_LABELS = [
        'sale' => 'vente',
        'rent' => 'location',
    ];

    public function run(): void
    {
        $dataPath = database_path('seeders/data/properties_seed.json');

        if (! file_exists($dataPath)) {
            $this->command->warn("properties_seed.json not found at {$dataPath}");
            $this->command->warn('Run: cd scraper && python tunisie_annonce_scraper.py');
            return;
        }

        // Idempotency guard — skip if already seeded
        if (Property::where('status', 'published')->count() >= 10) {
            $this->command->info('PropertySeeder: published properties already exist — skipping.');
            return;
        }

        $listings = json_decode(file_get_contents($dataPath), true);
        if (! $listings || ! is_array($listings)) {
            $this->command->error('properties_seed.json is empty or invalid JSON.');
            return;
        }

        $this->command->info("Loading " . count($listings) . " listings from JSON...");

        // ── Pre-load amenity slug → id map ───────────────────────────────────
        $amenityMap = DB::table('amenities')
            ->pluck('id', 'slug')
            ->all();

        // ── Pre-load location slug → id map ──────────────────────────────────
        $locationMap = DB::table('locations')
            ->pluck('id', 'slug')
            ->all();

        // ── Create or find demo users ─────────────────────────────────────────
        $demoUsers = [];
        foreach (self::DEMO_USERS as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'              => $userData['name'],
                    'password'          => Hash::make('demo1234'),
                    'role'              => $userData['role'],
                    'email_verified_at' => now(),
                ]
            );
            $demoUsers[] = $user;
        }

        // ── Seed properties ───────────────────────────────────────────────────
        $created = 0;
        foreach ($listings as $index => $item) {
            $owner  = $demoUsers[$index % count($demoUsers)];
            $locId  = $this->resolveLocationId($item['city_slug'] ?? '', $item['neighborhood'] ?? '', $locationMap);

            $title = $this->resolveTitle($item);

            $property = Property::create([
                'title'            => $title,
                'description'      => $item['description'] ?? null,
                'price'            => (int) ($item['price'] ?? 0),
                'transaction_type' => $item['transaction_type'] ?? 'sale',
                'property_type'    => $item['property_type'] ?? 'apartment',
                'status'           => 'published',
                'location_id'      => $locId,
                'address'          => $item['neighborhood'] ?? $item['location_raw'] ?? null,
                'surface'          => isset($item['surface']) ? (int) $item['surface'] : null,
                'bedrooms'         => isset($item['bedrooms']) ? (int) $item['bedrooms'] : null,
                'bathrooms'        => isset($item['bathrooms']) ? (int) $item['bathrooms'] : null,
                'floor'            => isset($item['floor']) ? (int) $item['floor'] : null,
                'is_furnished'     => (bool) ($item['is_furnished'] ?? false),
                'latitude'         => isset($item['lat'])  ? (float) $item['lat']  : null,
                'longitude'        => isset($item['lng'])  ? (float) $item['lng']  : null,
                'user_id'          => $owner->id,
            ]);

            // ── Images ──────────────────────────────────────────────────────
            $images = array_slice($item['images'] ?? [], 0, 6);
            foreach ($images as $pos => $url) {
                DB::table('property_images')->insert([
                    'property_id' => $property->id,
                    'url'         => $url,
                    'position'    => $pos,
                    'is_cover'    => $pos === 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // ── Amenities ────────────────────────────────────────────────────
            $amenityIds = [];
            foreach ($item['amenities'] ?? [] as $slug) {
                if (isset($amenityMap[$slug])) {
                    $amenityIds[] = $amenityMap[$slug];
                }
            }
            if ($amenityIds) {
                $property->amenities()->attach($amenityIds);
            }

            $created++;
        }

        $this->command->info("PropertySeeder: created {$created} properties across " . count($demoUsers) . " demo users.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveLocationId(string $citySlug, string $neighborhood, array $locationMap): ?int
    {
        // 1. Exact slug match
        if ($citySlug && isset($locationMap[$citySlug])) {
            return (int) $locationMap[$citySlug];
        }

        // 2. Partial slug match on neighborhood (slug-ified)
        if ($neighborhood) {
            $needle = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $neighborhood));
            foreach ($locationMap as $slug => $id) {
                if (str_contains($slug, $needle) || str_contains($needle, $slug)) {
                    return (int) $id;
                }
            }
        }

        // 3. Fallback: Tunis governorate
        return isset($locationMap['tunis']) ? (int) $locationMap['tunis'] : null;
    }

    private function resolveTitle(array $item): string
    {
        if (! empty($item['title'])) {
            return mb_substr($item['title'], 0, 255);
        }

        $typeLabel = self::TYPE_LABELS[$item['property_type'] ?? 'apartment'] ?? 'Bien';
        $txLabel   = self::TX_LABELS[$item['transaction_type'] ?? 'sale'] ?? 'vente';
        $place     = $item['neighborhood'] ?? $item['location_raw'] ?? $item['city_slug'] ?? 'Tunisie';
        $surface   = isset($item['surface']) ? " {$item['surface']}m²" : '';

        return "{$typeLabel} à {$txLabel} — {$place}{$surface}";
    }
}
