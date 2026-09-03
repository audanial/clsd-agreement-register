<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Country::create(['name' => 'Malaysia', 'iso_code' => 'MY', 'is_domestic' => true]);
        Campus::create(['code' => 'TBD', 'name' => 'Not Assigned', 'is_active' => true, 'sort_order' => 9999]);
    }

    public function test_legal_can_create_an_agreement(): void
    {
        $user = User::factory()->legal()->create();
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs($user);

        Livewire::test('agreement-form')
            ->set('title', 'New Agreement')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect(route('agreements.show', Agreement::latest()->first()));

        $this->assertDatabaseHas('agreements', [
            'title' => 'New Agreement',
            'document_status' => 'pending',
        ]);
    }

    public function test_admin_can_create_an_agreement(): void
    {
        $user = User::factory()->admin()->create();
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs($user);

        Livewire::test('agreement-form')
            ->set('title', 'Admin Agreement')
            ->set('type', 'MOA')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'signed')
            ->set('project_status', 'ongoing')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('agreements', ['title' => 'Admin Agreement']);
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('document_status', '')
            ->set('project_status', '')
            ->call('save')
            ->assertHasErrors(['title', 'type', 'campus_id', 'document_status', 'project_status']);
    }

    public function test_type_must_be_one_of_the_allowed_values(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('type', 'INVALID')
            ->call('save')
            ->assertHasErrors(['type']);
    }

    public function test_sector_must_be_academic_or_industri(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('sector', 'other')
            ->call('save')
            ->assertHasErrors(['sector']);
    }

    public function test_expired_is_not_an_option_for_document_status(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('document_status', 'expired')
            ->call('save')
            ->assertHasErrors(['document_status']);
    }

    public function test_expiry_before_date_signed_warns_but_still_saves(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Date Test')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('agreement_date', '2026-01-15')
            ->set('expiry_date', '2026-01-01')
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->assertSet(
                'dateWarning',
                'Expiry date is earlier than the date signed. Save anyway if that matches the document.'
            )
            ->assertSee('Expiry date is earlier than the date signed')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('agreements', ['title' => 'Date Test']);
    }

    public function test_no_warning_when_only_one_date_is_present(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'One Date')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('agreement_date', '2026-01-15')
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->assertSet('dateWarning', null)
            ->call('save')
            ->assertRedirect();
    }

    public function test_editing_an_agreement_hydrates_date_signed_from_agreement_date(): void
    {
        $agreement = Agreement::factory()->create(['agreement_date' => '2024-08-12']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->assertSet('agreement_date', '2024-08-12');
    }

    public function test_empty_expiry_date_persists_as_null(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Indefinite Agreement')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('expiry_date', '')
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('agreements', [
            'title' => 'Indefinite Agreement',
            'expiry_date' => null,
        ]);
    }

    public function test_changing_project_status_stamps_project_status_updated_at(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Stamp Test')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'ongoing')
            ->call('save')
            ->assertRedirect();

        $agreement = Agreement::where('title', 'Stamp Test')->first();
        $this->assertNotNull($agreement->project_status_updated_at);
    }

    public function test_saving_without_changing_project_status_does_not_restamp(): void
    {
        $agreement = Agreement::factory()->create([
            'project_status' => 'not_started',
            'project_status_updated_at' => now()->subDays(10),
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $original = $agreement->project_status_updated_at->copy();

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->set('notes', 'Updated notes only')
            ->call('save')
            ->assertRedirect();

        $agreement->refresh();
        $this->assertEquals($original, $agreement->project_status_updated_at);
    }

    public function test_partner_quick_create_creates_a_partner_and_links_it(): void
    {
        $campus = Campus::active()->first();
        $country = Country::first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Quick Partner')
            ->set('type', 'MOU')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'NewCo Sdn Bhd')
            ->set('newPartnerShortName', 'NewCo')
            ->set('newPartnerCountryId', $country?->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'name' => 'NewCo Sdn Bhd',
            'short_name' => 'NewCo',
        ]);

        $this->assertDatabaseHas('agreements', [
            'title' => 'Quick Partner',
            'partner_id' => Partner::where('name', 'NewCo Sdn Bhd')->first()->id,
        ]);
    }

    public function test_partner_quick_create_requires_a_name(): void
    {
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Missing Partner')
            ->set('type', 'MOU')
            ->set('partnerMode', 'new')
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertHasErrors(['newPartnerName']);
    }

    public function test_similar_partner_name_shows_a_warning(): void
    {
        Partner::factory()->create(['name' => 'Universiti Teknologi MARA']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'Teknologi MARA')
            ->assertSee('Universiti Teknologi MARA');
    }

    public function test_similar_partner_warning_does_not_block_creation(): void
    {
        Partner::factory()->create(['name' => 'Universiti Teknologi MARA']);
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Similar Warning')
            ->set('type', 'MOU')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'Teknologi MARA')
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('partners', ['name' => 'Teknologi MARA']);
    }

    public function test_acronym_partner_name_does_not_trigger_similar_warning(): void
    {
        // CHARACTERISATION TEST for DEF-003.
        // Decision M3-4 cited "UiTM" vs "Universiti Teknologi MARA" as the
        // duplicate case the matcher must catch. The current substring
        // implementation does not catch it. This test pins that limitation;
        // it is EXPECTED to fail if M5 improves the matcher, which is the
        // signal it exists to give.
        Partner::factory()->create(['name' => 'Universiti Teknologi MARA']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'UiTM')
            ->assertDontSee('Universiti Teknologi MARA');
    }

    public function test_partner_mode_radios_are_live_bound(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreement-form')->html();

        $this->assertSame(2, substr_count($html, 'wire:model.live="partnerMode"'));
        $this->assertStringNotContainsString('wire:model="partnerMode"', $html);
    }

    public function test_switching_to_new_partner_mode_reveals_the_partner_name_fields(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->assertSeeHtml('placeholder="Partner legal name"')
            ->assertSeeHtml('placeholder="Short name (optional)"');
    }

    public function test_switching_back_to_existing_restores_the_partner_dropdown(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('partnerMode', 'existing')
            ->assertSeeHtml('<select wire:model="partner_id"')
            ->assertDontSeeHtml('placeholder="Partner legal name"');
    }

    public function test_legal_can_edit_an_existing_agreement(): void
    {
        $agreement = Agreement::factory()->create(['title' => 'Old Title']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->set('title', 'New Title')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('agreements', [
            'id' => $agreement->id,
            'title' => 'New Title',
        ]);
    }

    public function test_new_pic_mode_requires_a_name(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('title', 'Missing PIC')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('picMode', 'new')
            ->set('pic_name', '')
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertHasErrors(['pic_name']);
    }

    public function test_existing_pic_mode_offers_previously_used_names(): void
    {
        Agreement::factory()->create(['pic_name' => 'Ahmad bin Osman']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('picMode', 'existing')
            ->assertSee('Ahmad bin Osman');
    }

    public function test_pic_autocomplete_only_returns_distinct_non_null_names(): void
    {
        Agreement::factory()->create(['pic_name' => 'Ahmad bin Osman']);
        Agreement::factory()->create(['pic_name' => 'Ahmad bin Osman']);
        Agreement::factory()->create(['pic_name' => 'Siti Nurhaliza']);
        Agreement::factory()->create(['pic_name' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $names = Livewire::test('agreement-form')->instance()->existingPics;

        $this->assertCount(2, $names);
        $this->assertContains('Ahmad bin Osman', $names);
        $this->assertContains('Siti Nurhaliza', $names);
        $this->assertNotContains(null, $names);
    }

    public function test_pic_mode_radios_are_live_bound(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreement-form')->html();

        $this->assertSame(2, substr_count($html, 'wire:model.live="picMode"'));
    }

    public function test_edit_form_retains_an_existing_pic_name(): void
    {
        $agreement = Agreement::factory()->create(['pic_name' => 'Ahmad bin Osman']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->assertSee('Ahmad bin Osman');
    }

    public function test_campus_dropdown_only_lists_active_campuses(): void
    {
        Campus::active()->first()->update(['is_active' => false]);
        Campus::create(['code' => 'ACTIVE', 'name' => 'Active Campus', 'is_active' => true]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->assertSee('ACTIVE')
            ->assertDontSee('TBD');
    }
}
