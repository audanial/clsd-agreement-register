<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampusSeeder extends Seeder
{
    public function run(): void
    {
        $campuses = [
            // The 12 teaching institutes.
            ['code' => 'MIIT',    'name' => 'Malaysian Institute of Information Technology',              'is_institute' => true],
            ['code' => 'RCMP',    'name' => 'Royal College of Medicine Perak',                            'is_institute' => true],
            ['code' => 'MIMET',   'name' => 'Malaysian Institute of Marine Engineering Technology',       'is_institute' => true],
            ['code' => 'MSI',     'name' => 'Malaysian Spanish Institute',                                'is_institute' => true],
            ['code' => 'MESTECH', 'name' => 'Institute of Medical Science Technology',                    'is_institute' => true],
            ['code' => 'MFI',     'name' => 'Malaysia France Institute',                                  'is_institute' => true],
            ['code' => 'MIDI',    'name' => 'Malaysia Italy Design Institute',                            'is_institute' => true],
            ['code' => 'MICET',   'name' => 'Malaysian Institute of Chemical & Bio-Engineering Technology','is_institute' => true],
            ['code' => 'MITEC',   'name' => 'Malaysian Institute of Industrial Technology',               'is_institute' => true],
            ['code' => 'MIAT',    'name' => 'Malaysian Institute of Aviation Technology',                 'is_institute' => true],
            ['code' => 'BMI',     'name' => 'British Malaysian Institute',                                'is_institute' => true],
            ['code' => 'BiS',     'name' => 'UniKL Business School',                                      'is_institute' => true],

            // Central units that own agreements but aren't teaching institutes.
            ['code' => 'UIO',     'name' => 'UniKL International Office',                                 'is_institute' => false],
            ['code' => 'ACE',     'name' => 'Centre for Advancement & Continuing Education',              'is_institute' => false],
            ['code' => 'CPS',     'name' => 'Centre for Postgraduate Studies',                            'is_institute' => false],

            // Catch-all for historical rows where campus was never recorded.
            // Preferred over nullable campus_id so filtering stays simple.
            ['code' => 'TBD',     'name' => 'Not Assigned',                                               'is_institute' => false],
        ];

        foreach ($campuses as $i => $campus) {
            DB::table('campuses')->updateOrInsert(
                ['code' => $campus['code']],
                $campus + [
                    'sort_order' => $i,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
