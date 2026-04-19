<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@sakan.tn'],
            [
                'name'     => 'Admin SAKAN',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'changeme123')),
                'role'     => 'admin',
            ]
        );
    }
}