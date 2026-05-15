<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,  // locations avant properties (FK)
            AmenitySeeder::class,
            AdminSeeder::class,
            PropertySeeder::class,
        ]);
    }
}