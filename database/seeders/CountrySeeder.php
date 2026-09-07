<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Malaysia',                  'iso_code' => 'MY', 'is_domestic' => true],
            ['name' => 'Indonesia',                 'iso_code' => 'ID', 'is_domestic' => false],
            ['name' => 'Singapore',                 'iso_code' => 'SG', 'is_domestic' => false],
            ['name' => 'Thailand',                  'iso_code' => 'TH', 'is_domestic' => false],
            ['name' => 'Brunei',                    'iso_code' => 'BN', 'is_domestic' => false],
            ['name' => 'Vietnam',                   'iso_code' => 'VN', 'is_domestic' => false],
            ['name' => 'Philippines',               'iso_code' => 'PH', 'is_domestic' => false],
            ['name' => 'Japan',                     'iso_code' => 'JP', 'is_domestic' => false],
            ['name' => 'South Korea',               'iso_code' => 'KR', 'is_domestic' => false],
            ['name' => 'China',                     'iso_code' => 'CN', 'is_domestic' => false],
            ['name' => 'India',                     'iso_code' => 'IN', 'is_domestic' => false],
            ['name' => 'Pakistan',                  'iso_code' => 'PK', 'is_domestic' => false],
            ['name' => 'Bangladesh',                'iso_code' => 'BD', 'is_domestic' => false],
            ['name' => 'France',                    'iso_code' => 'FR', 'is_domestic' => false],
            ['name' => 'Spain',                     'iso_code' => 'ES', 'is_domestic' => false],
            ['name' => 'Italy',                     'iso_code' => 'IT', 'is_domestic' => false],
            ['name' => 'Germany',                   'iso_code' => 'DE', 'is_domestic' => false],
            ['name' => 'Netherlands',               'iso_code' => 'NL', 'is_domestic' => false],
            ['name' => 'United Kingdom',            'iso_code' => 'GB', 'is_domestic' => false],
            ['name' => 'Türkiye',                   'iso_code' => 'TR', 'is_domestic' => false],
            ['name' => 'Egypt',                     'iso_code' => 'EG', 'is_domestic' => false],
            ['name' => 'Saudi Arabia',              'iso_code' => 'SA', 'is_domestic' => false],
            ['name' => 'United Arab Emirates',      'iso_code' => 'AE', 'is_domestic' => false],
            ['name' => 'Australia',                 'iso_code' => 'AU', 'is_domestic' => false],
            ['name' => 'United States',             'iso_code' => 'US', 'is_domestic' => false],

            // Placeholder for the simplified partner quick-create country control.
            ['name' => 'International',             'iso_code' => null, 'is_domestic' => false],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->updateOrInsert(
                ['name' => $country['name']],
                $country + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
