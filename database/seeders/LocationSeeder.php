<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // ── Gouvernorats (level 1 — parent_id = null) ─────────────────────────
        $gouvernorats = [
            ['name' => 'Tunis',       'slug' => 'tunis',       'zone_score' => 4, 'lat' => 36.8190,  'lng' => 10.1658],
            ['name' => 'Ariana',      'slug' => 'ariana',      'zone_score' => 3, 'lat' => 36.8625,  'lng' => 10.1956],
            ['name' => 'Ben Arous',   'slug' => 'ben-arous',   'zone_score' => 3, 'lat' => 36.7533,  'lng' => 10.2282],
            ['name' => 'Manouba',     'slug' => 'manouba',     'zone_score' => 2, 'lat' => 36.8089,  'lng' => 10.0975],
            ['name' => 'Nabeul',      'slug' => 'nabeul',      'zone_score' => 3, 'lat' => 36.4561,  'lng' => 10.7376],
            ['name' => 'Zaghouan',    'slug' => 'zaghouan',    'zone_score' => 1, 'lat' => 36.4029,  'lng' => 10.1429],
            ['name' => 'Bizerte',     'slug' => 'bizerte',     'zone_score' => 2, 'lat' => 37.2744,  'lng' => 9.8739],
            ['name' => 'Béja',        'slug' => 'beja',        'zone_score' => 1, 'lat' => 36.7256,  'lng' => 9.1817],
            ['name' => 'Jendouba',    'slug' => 'jendouba',    'zone_score' => 1, 'lat' => 36.5011,  'lng' => 8.7809],
            ['name' => 'Kef',         'slug' => 'kef',         'zone_score' => 1, 'lat' => 36.1674,  'lng' => 8.7048],
            ['name' => 'Siliana',     'slug' => 'siliana',     'zone_score' => 1, 'lat' => 36.0849,  'lng' => 9.3708],
            ['name' => 'Sousse',      'slug' => 'sousse',      'zone_score' => 3, 'lat' => 35.8245,  'lng' => 10.6346],
            ['name' => 'Monastir',    'slug' => 'monastir',    'zone_score' => 3, 'lat' => 35.7643,  'lng' => 10.8113],
            ['name' => 'Mahdia',      'slug' => 'mahdia',      'zone_score' => 2, 'lat' => 35.5047,  'lng' => 11.0622],
            ['name' => 'Sfax',        'slug' => 'sfax',        'zone_score' => 3, 'lat' => 34.7406,  'lng' => 10.7603],
            ['name' => 'Kairouan',    'slug' => 'kairouan',    'zone_score' => 1, 'lat' => 35.6781,  'lng' => 10.0961],
            ['name' => 'Kasserine',   'slug' => 'kasserine',   'zone_score' => 1, 'lat' => 35.1676,  'lng' => 8.8365],
            ['name' => 'Sidi Bouzid', 'slug' => 'sidi-bouzid', 'zone_score' => 1, 'lat' => 35.0382,  'lng' => 9.4849],
            ['name' => 'Gabès',       'slug' => 'gabes',       'zone_score' => 2, 'lat' => 33.8881,  'lng' => 9.7642],
            ['name' => 'Médenine',    'slug' => 'medenine',    'zone_score' => 2, 'lat' => 33.3549,  'lng' => 10.5055],
            ['name' => 'Tataouine',   'slug' => 'tataouine',   'zone_score' => 1, 'lat' => 32.9211,  'lng' => 10.4517],
            ['name' => 'Gafsa',       'slug' => 'gafsa',       'zone_score' => 1, 'lat' => 34.4250,  'lng' => 8.7842],
            ['name' => 'Tozeur',      'slug' => 'tozeur',      'zone_score' => 2, 'lat' => 33.9197,  'lng' => 8.1335],
            ['name' => 'Kébili',      'slug' => 'kebili',      'zone_score' => 1, 'lat' => 33.7046,  'lng' => 8.9715],
        ];

        foreach ($gouvernorats as $g) {
            $existing = DB::table('locations')->where('slug', $g['slug'])->first();
            if ($existing) {
                DB::table('locations')->where('slug', $g['slug'])->update(['zone_score' => $g['zone_score']]);
            } else {
                DB::table('locations')->insert([
                    'name'       => $g['name'],
                    'slug'       => $g['slug'],
                    'parent_id'  => null,
                    'zone_score' => $g['zone_score'],
                    'latitude'   => $g['lat'],
                    'longitude'  => $g['lng'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Helper to upsert a city under a parent governorate ────────────────
        $upsertCity = function (string $parentSlug, array $city) {
            $parentId = DB::table('locations')->where('slug', $parentSlug)->value('id');
            if (!$parentId) return;

            $existing = DB::table('locations')->where('slug', $city['slug'])->first();
            if ($existing) {
                DB::table('locations')->where('slug', $city['slug'])->update([
                    'zone_score'    => $city['zone_score'],
                    'neighborhoods' => isset($city['neighborhoods']) ? json_encode($city['neighborhoods']) : null,
                ]);
            } else {
                DB::table('locations')->insert([
                    'name'          => $city['name'],
                    'slug'          => $city['slug'],
                    'parent_id'     => $parentId,
                    'zone_score'    => $city['zone_score'],
                    'neighborhoods' => isset($city['neighborhoods']) ? json_encode($city['neighborhoods']) : null,
                    'latitude'      => $city['lat'] ?? null,
                    'longitude'     => $city['lng'] ?? null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        };

        // ── Tunis ────────────────────────────────────────────────────────────
        $upsertCity('tunis', [
            'name' => 'La Marsa', 'slug' => 'la-marsa', 'zone_score' => 5,
            'lat' => 36.8781, 'lng' => 10.3247,
            'neighborhoods' => ['La Marsa Plage', 'La Marsa Centre', 'Gammarth'],
        ]);
        foreach ([
            ['name' => 'Carthage',       'slug' => 'carthage',       'zone_score' => 5, 'lat' => 36.8528, 'lng' => 10.3233],
            ['name' => 'Sidi Bou Saïd',  'slug' => 'sidi-bou-said',  'zone_score' => 5, 'lat' => 36.8686, 'lng' => 10.3414],
            ['name' => 'Le Bardo',        'slug' => 'le-bardo',        'zone_score' => 3, 'lat' => 36.8091, 'lng' => 10.1400],
            ['name' => 'El Menzah',       'slug' => 'el-menzah',       'zone_score' => 4, 'lat' => 36.8456, 'lng' => 10.1892],
            ['name' => 'El Manar',        'slug' => 'el-manar',        'zone_score' => 4, 'lat' => 36.8378, 'lng' => 10.1675],
            ['name' => 'Cité El Khadra',  'slug' => 'cite-el-khadra',  'zone_score' => 3, 'lat' => 36.8275, 'lng' => 10.1839],
            ['name' => 'Lac 1',           'slug' => 'lac-1',           'zone_score' => 4, 'lat' => 36.8356, 'lng' => 10.2289],
            ['name' => 'Lac 2',           'slug' => 'lac-2',           'zone_score' => 5, 'lat' => 36.8475, 'lng' => 10.2450],
            ['name' => 'Montplaisir',     'slug' => 'montplaisir',     'zone_score' => 4, 'lat' => 36.8178, 'lng' => 10.1753],
            ['name' => 'Mutuelleville',   'slug' => 'mutuelleville',   'zone_score' => 4, 'lat' => 36.8214, 'lng' => 10.1669],
            ['name' => 'Menzah 5',        'slug' => 'menzah-5',        'zone_score' => 4, 'lat' => 36.8511, 'lng' => 10.1803],
            ['name' => 'Menzah 6',        'slug' => 'menzah-6',        'zone_score' => 4, 'lat' => 36.8542, 'lng' => 10.1764],
            ['name' => 'Omrane',          'slug' => 'omrane',          'zone_score' => 3, 'lat' => 36.8144, 'lng' => 10.1478],
            ['name' => 'Médina',          'slug' => 'medina-tunis',    'zone_score' => 3, 'lat' => 36.7992, 'lng' => 10.1706],
        ] as $city) {
            $upsertCity('tunis', $city);
        }

        // ── Ariana ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Ariana Ville',        'slug' => 'ariana-ville',       'zone_score' => 3, 'lat' => 36.8625, 'lng' => 10.1956],
            ['name' => 'La Soukra',           'slug' => 'soukra',             'zone_score' => 3, 'lat' => 36.8939, 'lng' => 10.2117],
            ['name' => 'Raoued',              'slug' => 'raoued',             'zone_score' => 3, 'lat' => 36.8983, 'lng' => 10.2483],
            ['name' => 'Ettadhamen',          'slug' => 'ettadhamen',         'zone_score' => 2, 'lat' => 36.8322, 'lng' => 10.1575],
            ['name' => 'Mnihla',              'slug' => 'mnihla',             'zone_score' => 2, 'lat' => 36.8500, 'lng' => 10.1736],
            ['name' => 'Kalâat el-Andalous',  'slug' => 'kalaat-el-andalous', 'zone_score' => 2, 'lat' => 37.0681, 'lng' => 10.0758],
        ] as $city) {
            $upsertCity('ariana', $city);
        }

        // ── Ben Arous ─────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Hammam Lif',  'slug' => 'hammam-lif',  'zone_score' => 3, 'lat' => 36.7286, 'lng' => 10.3308],
            ['name' => 'Radès',       'slug' => 'rades',        'zone_score' => 3, 'lat' => 36.7697, 'lng' => 10.2736],
            ['name' => 'Mégrine',     'slug' => 'megrine',      'zone_score' => 3, 'lat' => 36.7517, 'lng' => 10.2206],
            ['name' => 'Ezzahra',     'slug' => 'ezzahra',      'zone_score' => 3, 'lat' => 36.7464, 'lng' => 10.2742],
            ['name' => 'El Mourouj',  'slug' => 'el-mourouj',   'zone_score' => 2, 'lat' => 36.7200, 'lng' => 10.1897],
            ['name' => 'Fouchana',    'slug' => 'fouchana',     'zone_score' => 2, 'lat' => 36.6997, 'lng' => 10.1628],
        ] as $city) {
            $upsertCity('ben-arous', $city);
        }

        // ── Manouba ───────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Oued Ellil',   'slug' => 'oued-ellil',   'zone_score' => 2, 'lat' => 36.8178, 'lng' => 10.0472],
            ['name' => 'Douar Hicher', 'slug' => 'douar-hicher', 'zone_score' => 2, 'lat' => 36.8214, 'lng' => 10.0819],
            ['name' => 'Mohamedia',    'slug' => 'mohamedia',    'zone_score' => 2, 'lat' => 36.8072, 'lng' => 10.0394],
            ['name' => 'Jedaïda',      'slug' => 'jedaida',      'zone_score' => 1, 'lat' => 36.8361, 'lng' => 9.9131],
        ] as $city) {
            $upsertCity('manouba', $city);
        }

        // ── Nabeul ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Hammamet',      'slug' => 'hammamet',      'zone_score' => 4, 'lat' => 36.4000, 'lng' => 10.6167,
             'neighborhoods' => ['Yasmine Hammamet', 'Hammamet Nord', 'Hammamet Sud', 'Hammamet Centre']],
            ['name' => 'Nabeul Ville',  'slug' => 'nabeul-ville',  'zone_score' => 3, 'lat' => 36.4561, 'lng' => 10.7376],
            ['name' => 'Kélibia',       'slug' => 'kelibia',        'zone_score' => 2, 'lat' => 36.8467, 'lng' => 11.0931],
            ['name' => 'Korba',         'slug' => 'korba',          'zone_score' => 2, 'lat' => 36.5767, 'lng' => 10.8606],
            ['name' => 'Grombalia',     'slug' => 'grombalia',      'zone_score' => 2, 'lat' => 36.5972, 'lng' => 10.5025],
            ['name' => 'Menzel Temime', 'slug' => 'menzel-temime',  'zone_score' => 2, 'lat' => 36.7811, 'lng' => 10.9936],
            ['name' => 'Soliman',       'slug' => 'soliman',        'zone_score' => 2, 'lat' => 36.7003, 'lng' => 10.4911],
            ['name' => 'Takelsa',       'slug' => 'takelsa',        'zone_score' => 1, 'lat' => 36.6658, 'lng' => 10.7514],
            ['name' => 'Bou Argoub',    'slug' => 'bou-argoub',     'zone_score' => 1, 'lat' => 36.5342, 'lng' => 10.5467],
        ] as $city) {
            $upsertCity('nabeul', $city);
        }

        // ── Zaghouan ──────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'El Fahs',     'slug' => 'el-fahs',      'zone_score' => 1, 'lat' => 36.3739, 'lng' => 10.0058],
            ['name' => 'Bir Mcherga', 'slug' => 'bir-mcherga',  'zone_score' => 1, 'lat' => 36.4947, 'lng' => 9.8600],
        ] as $city) {
            $upsertCity('zaghouan', $city);
        }

        // ── Bizerte ───────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Bizerte Ville',    'slug' => 'bizerte-ville',    'zone_score' => 2, 'lat' => 37.2744, 'lng' => 9.8739],
            ['name' => 'Menzel Bourguiba', 'slug' => 'menzel-bourguiba', 'zone_score' => 2, 'lat' => 37.1572, 'lng' => 9.7861],
            ['name' => 'Mateur',           'slug' => 'mateur',           'zone_score' => 1, 'lat' => 37.0403, 'lng' => 9.6678],
            ['name' => 'Menzel Jemil',     'slug' => 'menzel-jemil',     'zone_score' => 2, 'lat' => 37.2242, 'lng' => 9.8244],
            ['name' => 'Ras Jebel',        'slug' => 'ras-jebel',        'zone_score' => 1, 'lat' => 37.2194, 'lng' => 10.1189],
            ['name' => 'Sejnane',          'slug' => 'sejnane',          'zone_score' => 1, 'lat' => 37.0606, 'lng' => 9.2372],
        ] as $city) {
            $upsertCity('bizerte', $city);
        }

        // ── Béja ──────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Testour',      'slug' => 'testour',      'zone_score' => 1, 'lat' => 36.5539, 'lng' => 9.4614],
            ['name' => 'Medjez El Bab','slug' => 'medjez-el-bab','zone_score' => 1, 'lat' => 36.6567, 'lng' => 9.6114],
            ['name' => 'Nefza',        'slug' => 'nefza',        'zone_score' => 1, 'lat' => 37.0289, 'lng' => 9.0303],
        ] as $city) {
            $upsertCity('beja', $city);
        }

        // ── Jendouba ──────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Tabarka',    'slug' => 'tabarka',    'zone_score' => 2, 'lat' => 36.9547, 'lng' => 8.7564],
            ['name' => 'Aïn Draham', 'slug' => 'ain-draham', 'zone_score' => 1, 'lat' => 36.7817, 'lng' => 8.6942],
            ['name' => 'Fernana',    'slug' => 'fernana',    'zone_score' => 1, 'lat' => 36.6389, 'lng' => 8.7039],
        ] as $city) {
            $upsertCity('jendouba', $city);
        }

        // ── Le Kef ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Le Kef',              'slug' => 'le-kef',              'zone_score' => 1, 'lat' => 36.1674, 'lng' => 8.7048],
            ['name' => 'Sakiet Sidi Youssef', 'slug' => 'sakiet-sidi-youssef', 'zone_score' => 1, 'lat' => 36.2233, 'lng' => 8.3636],
            ['name' => 'Tajerouine',          'slug' => 'tajerouine',          'zone_score' => 1, 'lat' => 35.8814, 'lng' => 8.5469],
        ] as $city) {
            $upsertCity('kef', $city);
        }

        // ── Siliana ───────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Gaâfour', 'slug' => 'gaafour', 'zone_score' => 1, 'lat' => 36.3272, 'lng' => 9.3217],
            ['name' => 'Makthar', 'slug' => 'makthar', 'zone_score' => 1, 'lat' => 35.8564, 'lng' => 9.2042],
        ] as $city) {
            $upsertCity('siliana', $city);
        }

        // ── Sousse ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Sousse Ville',  'slug' => 'sousse-ville',  'zone_score' => 3, 'lat' => 35.8245, 'lng' => 10.6346,
             'neighborhoods' => ['Khezama Est', 'Khezama Ouest', 'Sahloul', 'Sousse Ville', 'Hammam Sousse', 'Akouda']],
            ['name' => 'Sahloul',       'slug' => 'sahloul',       'zone_score' => 3, 'lat' => 35.8564, 'lng' => 10.5958],
            ['name' => 'Hammam Sousse', 'slug' => 'hammam-sousse', 'zone_score' => 3, 'lat' => 35.8600, 'lng' => 10.5958],
            ['name' => 'Kantaoui',      'slug' => 'kantaoui',      'zone_score' => 3, 'lat' => 35.8942, 'lng' => 10.5761],
            ['name' => 'Msaken',        'slug' => 'msaken',        'zone_score' => 2, 'lat' => 35.7314, 'lng' => 10.5739],
            ['name' => 'Enfidha',       'slug' => 'enfidha',       'zone_score' => 2, 'lat' => 36.1394, 'lng' => 10.3800],
            ['name' => 'Sidi Bou Ali',  'slug' => 'sidi-bou-ali',  'zone_score' => 2, 'lat' => 36.0531, 'lng' => 10.5025],
        ] as $city) {
            $upsertCity('sousse', $city);
        }

        // ── Monastir ──────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Monastir Ville', 'slug' => 'monastir-ville', 'zone_score' => 3, 'lat' => 35.7643, 'lng' => 10.8113,
             'neighborhoods' => ['Monastir Centre', 'Skanes', 'Lamta']],
            ['name' => 'Ksar Hellal',    'slug' => 'ksar-hellal',    'zone_score' => 2, 'lat' => 35.6419, 'lng' => 10.8919],
            ['name' => 'Moknine',        'slug' => 'moknine',        'zone_score' => 2, 'lat' => 35.6336, 'lng' => 10.9067],
            ['name' => 'Bembla',         'slug' => 'bembla',         'zone_score' => 2, 'lat' => 35.7447, 'lng' => 10.7975],
            ['name' => 'Sayada',         'slug' => 'sayada',         'zone_score' => 2, 'lat' => 35.7167, 'lng' => 10.7667],
            ['name' => 'Téboulba',       'slug' => 'teboulba',       'zone_score' => 2, 'lat' => 35.6889, 'lng' => 10.9081],
        ] as $city) {
            $upsertCity('monastir', $city);
        }

        // ── Mahdia ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Mahdia Ville', 'slug' => 'mahdia-ville', 'zone_score' => 2, 'lat' => 35.5047, 'lng' => 11.0622],
            ['name' => 'El Jem',       'slug' => 'el-jem',       'zone_score' => 1, 'lat' => 35.2961, 'lng' => 10.7133],
            ['name' => 'Chebba',       'slug' => 'chebba',       'zone_score' => 1, 'lat' => 35.2347, 'lng' => 11.1147],
            ['name' => 'Ksour Essef',  'slug' => 'ksour-essef',  'zone_score' => 1, 'lat' => 35.4197, 'lng' => 11.0681],
            ['name' => 'Salakta',      'slug' => 'salakta',      'zone_score' => 1, 'lat' => 35.3492, 'lng' => 11.0458],
        ] as $city) {
            $upsertCity('mahdia', $city);
        }

        // ── Kairouan ──────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Sbikha',    'slug' => 'sbikha',    'zone_score' => 1, 'lat' => 35.9058, 'lng' => 9.8072],
            ['name' => 'Haffouz',   'slug' => 'haffouz',   'zone_score' => 1, 'lat' => 35.6292, 'lng' => 9.6731],
            ['name' => 'Nasrallah', 'slug' => 'nasrallah', 'zone_score' => 1, 'lat' => 35.6733, 'lng' => 9.9786],
        ] as $city) {
            $upsertCity('kairouan', $city);
        }

        // ── Kasserine ─────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Sbeitla',  'slug' => 'sbeitla',  'zone_score' => 1, 'lat' => 35.2342, 'lng' => 9.0997],
            ['name' => 'Feriana',  'slug' => 'feriana',  'zone_score' => 1, 'lat' => 34.9494, 'lng' => 8.5708],
            ['name' => 'Thala',    'slug' => 'thala',    'zone_score' => 1, 'lat' => 35.5661, 'lng' => 8.6717],
        ] as $city) {
            $upsertCity('kasserine', $city);
        }

        // ── Sidi Bouzid ───────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Jelma', 'slug' => 'jelma', 'zone_score' => 1, 'lat' => 35.4022, 'lng' => 9.5208],
        ] as $city) {
            $upsertCity('sidi-bouzid', $city);
        }

        // ── Sfax ──────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Sfax Ville',          'slug' => 'sfax-ville',          'zone_score' => 3, 'lat' => 34.7406, 'lng' => 10.7603,
             'neighborhoods' => ['Sfax Ville', 'Sakiet Ezzit', 'Sakiet Eddaier', 'El Ain', 'Chihia', 'Agareb', 'Thyna']],
            ['name' => 'Sakiet Ezzit',        'slug' => 'sakiet-ezzit',        'zone_score' => 3, 'lat' => 34.7711, 'lng' => 10.7400],
            ['name' => 'Thyna',               'slug' => 'thyna',               'zone_score' => 2, 'lat' => 34.6961, 'lng' => 10.7347],
            ['name' => 'El Ain',              'slug' => 'el-ain-sfax',         'zone_score' => 3, 'lat' => 34.7956, 'lng' => 10.7175],
            ['name' => 'Skhira',              'slug' => 'skhira',              'zone_score' => 2, 'lat' => 34.2950, 'lng' => 10.0678],
            ['name' => 'Mahres',              'slug' => 'mahres',              'zone_score' => 2, 'lat' => 34.5356, 'lng' => 10.5022],
            ['name' => 'El Hencha',           'slug' => 'el-hencha',           'zone_score' => 1, 'lat' => 35.0600, 'lng' => 10.2733],
            ['name' => 'Bir Ali Ben Khalifa', 'slug' => 'bir-ali-ben-khalifa', 'zone_score' => 1, 'lat' => 34.7228, 'lng' => 10.0894],
        ] as $city) {
            $upsertCity('sfax', $city);
        }

        // ── Gafsa ─────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Metlaoui', 'slug' => 'metlaoui', 'zone_score' => 1, 'lat' => 34.3231, 'lng' => 8.4064],
            ['name' => 'El Ksar',  'slug' => 'el-ksar',  'zone_score' => 1, 'lat' => 34.4072, 'lng' => 8.7722],
        ] as $city) {
            $upsertCity('gafsa', $city);
        }

        // ── Tozeur ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Nefta',   'slug' => 'nefta',   'zone_score' => 1, 'lat' => 33.8728, 'lng' => 7.8786],
            ['name' => 'Degache', 'slug' => 'degache', 'zone_score' => 1, 'lat' => 33.9706, 'lng' => 8.2072],
        ] as $city) {
            $upsertCity('tozeur', $city);
        }

        // ── Kébili ────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Douz', 'slug' => 'douz', 'zone_score' => 1, 'lat' => 33.4581, 'lng' => 9.0189],
        ] as $city) {
            $upsertCity('kebili', $city);
        }

        // ── Gabès ─────────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Gabès Ville', 'slug' => 'gabes-ville', 'zone_score' => 2, 'lat' => 33.8881, 'lng' => 9.7642,
             'neighborhoods' => ['Gabès Ville', 'Gabès Médina', 'Chott']],
            ['name' => 'Ghannouch',  'slug' => 'ghannouch',   'zone_score' => 2, 'lat' => 33.9239, 'lng' => 10.0417],
            ['name' => 'El Hamma',   'slug' => 'el-hamma',    'zone_score' => 1, 'lat' => 33.8875, 'lng' => 9.7961],
            ['name' => 'Matmata',    'slug' => 'matmata',     'zone_score' => 1, 'lat' => 33.5447, 'lng' => 9.9736],
        ] as $city) {
            $upsertCity('gabes', $city);
        }

        // ── Médenine ──────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Djerba',      'slug' => 'djerba',      'zone_score' => 4, 'lat' => 33.8667, 'lng' => 10.8500,
             'neighborhoods' => ['Houmt Souk', 'Midoun', 'Aghir', 'Ajim']],
            ['name' => 'Zarzis',      'slug' => 'zarzis',      'zone_score' => 2, 'lat' => 33.5042, 'lng' => 11.1125,
             'neighborhoods' => ['Zarzis Plage']],
            ['name' => 'Médenine Ville','slug' => 'medenine-ville','zone_score' => 1, 'lat' => 33.3549, 'lng' => 10.5055],
            ['name' => 'Ben Gardane', 'slug' => 'ben-gardane', 'zone_score' => 1, 'lat' => 33.1383, 'lng' => 11.2208],
        ] as $city) {
            $upsertCity('medenine', $city);
        }

        // ── Tataouine ─────────────────────────────────────────────────────────
        foreach ([
            ['name' => 'Ghomrassen', 'slug' => 'ghomrassen', 'zone_score' => 1, 'lat' => 32.9872, 'lng' => 10.1861],
            ['name' => 'Remada',     'slug' => 'remada',     'zone_score' => 1, 'lat' => 32.3139, 'lng' => 10.3825],
        ] as $city) {
            $upsertCity('tataouine', $city);
        }
    }
}
