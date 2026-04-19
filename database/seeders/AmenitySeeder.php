<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['name' => 'Ascenseur',          'slug' => 'ascenseur',          'icon' => 'arrow-up-square'],
            ['name' => 'Garage',             'slug' => 'garage',             'icon' => 'car'],
            ['name' => 'Terrasse',           'slug' => 'terrasse',           'icon' => 'sun'],
            ['name' => 'Meublé',             'slug' => 'meuble',             'icon' => 'sofa'],
            ['name' => 'Piscine',            'slug' => 'piscine',            'icon' => 'waves'],
            ['name' => 'Gardien',            'slug' => 'gardien',            'icon' => 'shield'],
            ['name' => 'Climatisation',      'slug' => 'climatisation',      'icon' => 'wind'],
            ['name' => 'Proche mosquée',     'slug' => 'proche-mosquee',     'icon' => 'map-pin'],
            ['name' => 'Proche école',       'slug' => 'proche-ecole',       'icon' => 'school'],
            ['name' => 'Proche transports',  'slug' => 'proche-transports',  'icon' => 'bus'],
            ['name' => 'Proche commerces',   'slug' => 'proche-commerces',   'icon' => 'shopping-bag'],
            ['name' => 'Jardin',             'slug' => 'jardin',             'icon' => 'tree-pine'],
            ['name' => 'Interphone',         'slug' => 'interphone',         'icon' => 'phone'],
            ['name' => 'Fibre optique',      'slug' => 'fibre-optique',      'icon' => 'wifi'],
        ];

        DB::table('amenities')->insertOrIgnore(
            array_map(fn ($a) => array_merge($a, [
                'created_at' => now(),
                'updated_at' => now(),
            ]), $amenities)
        );
    }
}