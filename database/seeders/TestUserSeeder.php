<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'dev-admin@afrodita.local',
        ], [
            'name' => 'Dev Admin',
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
        ]);
    }
}
