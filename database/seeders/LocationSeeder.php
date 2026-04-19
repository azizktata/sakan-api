<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // ── Gouvernorats (level 1 — parent_id = null) ─────────────────────────
        $gouvernorats = [
            ['name' => 'Tunis',         'slug' => 'tunis',          'lat' => 36.8190,  'lng' => 10.1658],
            ['name' => 'Ariana',        'slug' => 'ariana',         'lat' => 36.8625,  'lng' => 10.1956],
            ['name' => 'Ben Arous',     'slug' => 'ben-arous',      'lat' => 36.7533,  'lng' => 10.2282],
            ['name' => 'Manouba',       'slug' => 'manouba',        'lat' => 36.8089,  'lng' => 10.0975],
            ['name' => 'Nabeul',        'slug' => 'nabeul',         'lat' => 36.4561,  'lng' => 10.7376],
            ['name' => 'Zaghouan',      'slug' => 'zaghouan',       'lat' => 36.4029,  'lng' => 10.1429],
            ['name' => 'Bizerte',       'slug' => 'bizerte',        'lat' => 37.2744,  'lng' => 9.8739],
            ['name' => 'Béja',          'slug' => 'beja',           'lat' => 36.7256,  'lng' => 9.1817],
            ['name' => 'Jendouba',      'slug' => 'jendouba',       'lat' => 36.5011,  'lng' => 8.7809],
            ['name' => 'Kef',           'slug' => 'kef',            'lat' => 36.1674,  'lng' => 8.7048],
            ['name' => 'Siliana',       'slug' => 'siliana',        'lat' => 36.0849,  'lng' => 9.3708],
            ['name' => 'Sousse',        'slug' => 'sousse',         'lat' => 35.8245,  'lng' => 10.6346],
            ['name' => 'Monastir',      'slug' => 'monastir',       'lat' => 35.7643,  'lng' => 10.8113],
            ['name' => 'Mahdia',        'slug' => 'mahdia',         'lat' => 35.5047,  'lng' => 11.0622],
            ['name' => 'Sfax',          'slug' => 'sfax',           'lat' => 34.7406,  'lng' => 10.7603],
            ['name' => 'Kairouan',      'slug' => 'kairouan',       'lat' => 35.6781,  'lng' => 10.0961],
            ['name' => 'Kasserine',     'slug' => 'kasserine',      'lat' => 35.1676,  'lng' => 8.8365],
            ['name' => 'Sidi Bouzid',   'slug' => 'sidi-bouzid',    'lat' => 35.0382,  'lng' => 9.4849],
            ['name' => 'Gabès',         'slug' => 'gabes',          'lat' => 33.8881,  'lng' => 9.7642],
            ['name' => 'Médenine',      'slug' => 'medenine',       'lat' => 33.3549,  'lng' => 10.5055],
            ['name' => 'Tataouine',     'slug' => 'tataouine',      'lat' => 32.9211,  'lng' => 10.4517],
            ['name' => 'Gafsa',         'slug' => 'gafsa',          'lat' => 34.4250,  'lng' => 8.7842],
            ['name' => 'Tozeur',        'slug' => 'tozeur',         'lat' => 33.9197,  'lng' => 8.1335],
            ['name' => 'Kébili',        'slug' => 'kebili',         'lat' => 33.7046,  'lng' => 8.9715],
        ];

        foreach ($gouvernorats as $g) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $g['name'],
                'slug'       => $g['slug'],
                'parent_id'  => null,
                'latitude'   => $g['lat'],
                'longitude'  => $g['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Délégations Tunis (level 2 — parent = Tunis) ─────────────────────
        $tunisId = DB::table('locations')->where('slug', 'tunis')->value('id');

        $delegationsTunis = [
            ['name' => 'La Marsa',       'slug' => 'la-marsa',        'lat' => 36.8781,  'lng' => 10.3247],
            ['name' => 'Carthage',       'slug' => 'carthage',        'lat' => 36.8528,  'lng' => 10.3233],
            ['name' => 'Sidi Bou Saïd', 'slug' => 'sidi-bou-said',   'lat' => 36.8686,  'lng' => 10.3414],
            ['name' => 'Le Bardo',       'slug' => 'le-bardo',        'lat' => 36.8091,  'lng' => 10.1400],
            ['name' => 'El Menzah',      'slug' => 'el-menzah',       'lat' => 36.8456,  'lng' => 10.1892],
            ['name' => 'El Manar',       'slug' => 'el-manar',        'lat' => 36.8378,  'lng' => 10.1675],
            ['name' => 'Cité El Khadra', 'slug' => 'cite-el-khadra',  'lat' => 36.8275,  'lng' => 10.1839],
            ['name' => 'Lac 1',          'slug' => 'lac-1',           'lat' => 36.8356,  'lng' => 10.2289],
            ['name' => 'Lac 2',          'slug' => 'lac-2',           'lat' => 36.8475,  'lng' => 10.2450],
            ['name' => 'Montplaisir',    'slug' => 'montplaisir',     'lat' => 36.8178,  'lng' => 10.1753],
            ['name' => 'Mutuelleville', 'slug' => 'mutuelleville',    'lat' => 36.8214,  'lng' => 10.1669],
            ['name' => 'Menzah 5',       'slug' => 'menzah-5',        'lat' => 36.8511,  'lng' => 10.1803],
            ['name' => 'Menzah 6',       'slug' => 'menzah-6',        'lat' => 36.8542,  'lng' => 10.1764],
            ['name' => 'Omrane',         'slug' => 'omrane',          'lat' => 36.8144,  'lng' => 10.1478],
            ['name' => 'Médina',         'slug' => 'medina-tunis',    'lat' => 36.7992,  'lng' => 10.1706],
        ];

        foreach ($delegationsTunis as $d) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $d['name'],
                'slug'       => $d['slug'],
                'parent_id'  => $tunisId,
                'latitude'   => $d['lat'],
                'longitude'  => $d['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Délégations Ariana ────────────────────────────────────────────────
        $arianaId = DB::table('locations')->where('slug', 'ariana')->value('id');

        $delegationsAriana = [
            ['name' => 'Ariana Ville',   'slug' => 'ariana-ville',    'lat' => 36.8625,  'lng' => 10.1956],
            ['name' => 'Soukra',         'slug' => 'soukra',          'lat' => 36.8939,  'lng' => 10.2117],
            ['name' => 'Raoued',         'slug' => 'raoued',          'lat' => 36.8983,  'lng' => 10.2483],
            ['name' => 'Kalâat el-Andalous', 'slug' => 'kalaat-el-andalous', 'lat' => 37.0681, 'lng' => 10.0758],
            ['name' => 'Ettadhamen',     'slug' => 'ettadhamen',      'lat' => 36.8322,  'lng' => 10.1575],
            ['name' => 'Mnihla',         'slug' => 'mnihla',          'lat' => 36.8500,  'lng' => 10.1736],
        ];

        foreach ($delegationsAriana as $d) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $d['name'],
                'slug'       => $d['slug'],
                'parent_id'  => $arianaId,
                'latitude'   => $d['lat'],
                'longitude'  => $d['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Délégations Nabeul (côte — forte demande immo) ────────────────────
        $nabeulId = DB::table('locations')->where('slug', 'nabeul')->value('id');

        $delegationsNabeul = [
            ['name' => 'Hammamet',       'slug' => 'hammamet',        'lat' => 36.4000,  'lng' => 10.6167],
            ['name' => 'Nabeul Ville',   'slug' => 'nabeul-ville',    'lat' => 36.4561,  'lng' => 10.7376],
            ['name' => 'Kélibia',        'slug' => 'kelibia',         'lat' => 36.8467,  'lng' => 11.0931],
            ['name' => 'Grombalia',      'slug' => 'grombalia',       'lat' => 36.5972,  'lng' => 10.5025],
            ['name' => 'Korba',          'slug' => 'korba',           'lat' => 36.5767,  'lng' => 10.8606],
        ];

        foreach ($delegationsNabeul as $d) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $d['name'],
                'slug'       => $d['slug'],
                'parent_id'  => $nabeulId,
                'latitude'   => $d['lat'],
                'longitude'  => $d['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Délégations Sousse ────────────────────────────────────────────────
        $sousseId = DB::table('locations')->where('slug', 'sousse')->value('id');

        $delegationsSousse = [
            ['name' => 'Sousse Ville',   'slug' => 'sousse-ville',    'lat' => 35.8245,  'lng' => 10.6346],
            ['name' => 'Sahloul',        'slug' => 'sahloul',         'lat' => 35.8564,  'lng' => 10.5958],
            ['name' => 'Hammam Sousse',  'slug' => 'hammam-sousse',   'lat' => 35.8600,  'lng' => 10.5958],
            ['name' => 'Kantaoui',       'slug' => 'kantaoui',        'lat' => 35.8942,  'lng' => 10.5761],
            ['name' => 'Msaken',         'slug' => 'msaken',          'lat' => 35.7314,  'lng' => 10.5739],
        ];

        foreach ($delegationsSousse as $d) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $d['name'],
                'slug'       => $d['slug'],
                'parent_id'  => $sousseId,
                'latitude'   => $d['lat'],
                'longitude'  => $d['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Délégations Sfax ──────────────────────────────────────────────────
        $sfaxId = DB::table('locations')->where('slug', 'sfax')->value('id');

        $delegationsSfax = [
            ['name' => 'Sfax Ville',     'slug' => 'sfax-ville',      'lat' => 34.7406,  'lng' => 10.7603],
            ['name' => 'Sakiet Ezzit',   'slug' => 'sakiet-ezzit',    'lat' => 34.7711,  'lng' => 10.7400],
            ['name' => 'Thyna',          'slug' => 'thyna',           'lat' => 34.6961,  'lng' => 10.7347],
            ['name' => 'El Ain',         'slug' => 'el-ain-sfax',     'lat' => 34.7956,  'lng' => 10.7175],
        ];

        foreach ($delegationsSfax as $d) {
            DB::table('locations')->insertOrIgnore([
                'name'       => $d['name'],
                'slug'       => $d['slug'],
                'parent_id'  => $sfaxId,
                'latitude'   => $d['lat'],
                'longitude'  => $d['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}