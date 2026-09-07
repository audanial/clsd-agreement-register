<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\CampusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CampusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_campus_seeder_includes_the_three_new_campuses(): void
    {
        $this->seed(CampusSeeder::class);

        $this->assertDatabaseHas('campuses', ['code' => 'MCI', 'name' => 'UniKL Malaysia China Institute']);
        $this->assertDatabaseHas('campuses', ['code' => 'CIL', 'name' => 'Centre for Industrial Linkages']);
        $this->assertDatabaseHas('campuses', ['code' => 'CoRI', 'name' => 'Centre for Research and Innovation']);
    }

    public function test_campus_seeder_is_still_idempotent_with_the_new_rows(): void
    {
        $this->seed(CampusSeeder::class);
        $firstCount = DB::table('campuses')->count();

        $this->seed(CampusSeeder::class);
        $secondCount = DB::table('campuses')->count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_new_campuses_appear_in_the_agreement_forms_campus_dropdown(): void
    {
        $this->seed(CampusSeeder::class);

        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreement-form')->html();

        $this->assertStringContainsString('MCI', $html);
        $this->assertStringContainsString('UniKL Malaysia China Institute', $html);
        $this->assertStringContainsString('CIL', $html);
        $this->assertStringContainsString('Centre for Industrial Linkages', $html);
        $this->assertStringContainsString('CoRI', $html);
        $this->assertStringContainsString('Centre for Research and Innovation', $html);
    }
}
