<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CountrySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(CountrySeeder::class);
        $firstCount = DB::table('countries')->count();

        $this->seed(CountrySeeder::class);
        $secondCount = DB::table('countries')->count();

        $this->assertSame(26, $firstCount);
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_malaysia_is_the_only_domestic_country(): void
    {
        $this->seed(CountrySeeder::class);

        $domestic = DB::table('countries')->where('is_domestic', true)->pluck('name');

        $this->assertCount(1, $domestic);
        $this->assertSame('Malaysia', $domestic->first());
    }
}
