<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        if (! App::environment('local', 'testing')) {
            return;
        }

        $password = env('DEV_USER_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => 'admin@unikl.edu.my'],
            [
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => $password,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'legal@unikl.edu.my'],
            [
                'name' => 'Legal User',
                'role' => 'legal',
                'password' => $password,
                'is_active' => true,
            ]
        );
    }
}
