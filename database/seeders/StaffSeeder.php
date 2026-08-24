<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class StaffSeeder extends Seeder
{
    /**
     * Seed the application's staff users (PICs / non-Legal UniKL staff).
     */
    public function run(): void
    {
        if (! App::environment('local', 'testing')) {
            return;
        }

        $password = env('DEV_USER_PASSWORD', 'password');

        $staff = [
            ['name' => 'Ahmad bin Abdullah',    'email' => 'ahmad@unikl.edu.my'],
            ['name' => 'Siti Nurhaliza',         'email' => 'siti@unikl.edu.my'],
            ['name' => 'Rajesh Kumar',           'email' => 'rajesh@unikl.edu.my'],
            ['name' => 'Li Wei',                 'email' => 'liwei@unikl.edu.my'],
            ['name' => 'Fatimah binti Ibrahim',  'email' => 'fatimah@unikl.edu.my'],
        ];

        foreach ($staff as $person) {
            User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'role' => 'viewer',
                    'password' => $password,
                    'is_active' => true,
                ]
            );
        }
    }
}
