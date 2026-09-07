<?php

namespace Tests\Feature\Agreement;

use App\Models\Campus;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerCountryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        Campus::create(['code' => 'TBD', 'name' => 'Not Assigned', 'is_active' => true, 'sort_order' => 9999]);
    }

    public function test_selecting_local_resolves_the_new_partners_country_to_malaysia(): void
    {
        $campus = Campus::active()->first();
        $malaysia = Country::where('is_domestic', true)->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Local Partner')
            ->set('type', 'MOU')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'LocalCo Sdn Bhd')
            ->set('newPartnerCountry', 'local')
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $partner = Partner::where('name', 'LocalCo Sdn Bhd')->first();

        $this->assertNotNull($partner);
        $this->assertSame($malaysia?->id, $partner->country_id);
    }

    public function test_selecting_international_resolves_the_new_partners_country_to_the_placeholder_row(): void
    {
        $campus = Campus::active()->first();
        $international = Country::where('name', 'International')->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Global Partner')
            ->set('type', 'MOU')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'GlobalCo Ltd')
            ->set('newPartnerCountry', 'international')
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $partner = Partner::where('name', 'GlobalCo Ltd')->first();

        $this->assertNotNull($partner);
        $this->assertSame($international?->id, $partner->country_id);
    }

    public function test_country_seeder_is_still_idempotent_with_the_new_row(): void
    {
        $this->seed(CountrySeeder::class);
        $firstCount = DB::table('countries')->count();

        $this->seed(CountrySeeder::class);
        $secondCount = DB::table('countries')->count();

        $this->assertSame(26, $firstCount);
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_existing_partners_country_id_is_unaffected_by_this_change(): void
    {
        $campus = Campus::active()->first();
        $existingPartner = Partner::factory()->create(['country_id' => Country::where('name', 'Singapore')->first()?->id]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Uses Existing Partner')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $existingPartner->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $existingPartner->refresh();

        $this->assertSame('Singapore', $existingPartner->country->name);
    }
}
