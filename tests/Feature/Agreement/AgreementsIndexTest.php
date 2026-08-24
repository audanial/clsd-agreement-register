<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->legal()->create();

        $this->actingAs($user)
            ->get(route('agreements.index'))
            ->assertOk()
            ->assertSee('Agreement Register');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('agreements.index'))->assertRedirect('/login');
    }

    public function test_viewer_does_not_see_pending_agreements_in_the_list(): void
    {
        Agreement::factory()->pending()->create(['title' => 'Hidden Pending']);
        Agreement::factory()->signed()->create(['title' => 'Visible Signed']);

        $this->actingAs(User::factory()->viewer()->create());

        Livewire::test('agreements-index')
            ->assertSee('Visible Signed')
            ->assertDontSee('Hidden Pending');
    }

    public function test_legal_sees_pending_agreements_in_the_list(): void
    {
        Agreement::factory()->pending()->create(['title' => 'Visible Pending']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index')
            ->assertSee('Visible Pending');
    }

    public function test_pending_is_not_offered_as_a_status_filter_to_a_viewer(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        Livewire::test('agreements-index')
            ->assertDontSee('value="pending"')
            ->assertDontSee('Pending');
    }

    public function test_search_matches_title(): void
    {
        Agreement::factory()->signed()->create(['title' => 'Alpha Agreement']);
        Agreement::factory()->signed()->create(['title' => 'Beta Agreement']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', ['search' => 'Alpha'])
            ->assertSee('Alpha Agreement')
            ->assertDontSee('Beta Agreement');
    }

    public function test_search_matches_partner_name(): void
    {
        $alpha = Agreement::factory()->signed()->create(['title' => 'Title One']);
        $beta = Agreement::factory()->signed()->create(['title' => 'Title Two']);
        $alpha->partner->update(['name' => 'AlphaCorp']);
        $beta->partner->update(['name' => 'BetaCorp']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', ['search' => 'AlphaCorp'])
            ->assertSee('Title One')
            ->assertDontSee('Title Two');
    }

    public function test_filters_by_campus_type_document_status_and_project_status(): void
    {
        $campus = Campus::firstOrCreate(['code' => 'TBD'], ['name' => 'TBD', 'is_institute' => false]);
        $otherCampus = Campus::create(['code' => 'MIIT', 'name' => 'MIIT', 'is_institute' => true]);

        Agreement::factory()->signed()->create([
            'title' => 'Match',
            'campus_id' => $campus->id,
            'type' => 'MOU',
            'project_status' => 'ongoing',
        ]);
        Agreement::factory()->signed()->create([
            'title' => 'No Match Campus',
            'campus_id' => $otherCampus->id,
            'type' => 'MOU',
            'project_status' => 'ongoing',
        ]);
        Agreement::factory()->pending()->create([
            'title' => 'No Match Status',
            'campus_id' => $campus->id,
            'type' => 'MOU',
            'project_status' => 'ongoing',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', [
            'campus' => (string) $campus->id,
            'type' => 'MOU',
            'documentStatus' => 'signed',
            'projectStatus' => 'ongoing',
        ])
            ->assertSee('Match')
            ->assertDontSee('No Match Campus')
            ->assertDontSee('No Match Status');
    }

    public function test_changing_a_filter_resets_to_the_first_page(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', ['search' => 'old'])
            ->set('paginators.page', 2)
            ->set('search', 'new')
            ->assertSet('paginators.page', 1);
    }

    public function test_archived_agreements_are_excluded_by_default(): void
    {
        Agreement::factory()->signed()->create(['title' => 'Active Agreement']);
        Agreement::factory()->archived()->signed()->create(['title' => 'Archived Agreement']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index')
            ->assertSee('Active Agreement')
            ->assertDontSee('Archived Agreement');
    }

    public function test_archived_agreements_appear_when_the_archived_filter_is_on(): void
    {
        Agreement::factory()->signed()->create(['title' => 'Active Agreement']);
        Agreement::factory()->archived()->signed()->create(['title' => 'Archived Agreement']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', ['showArchived' => true])
            ->assertSee('Archived Agreement')
            ->assertDontSee('Active Agreement');
    }

    public function test_stale_badge_is_shown_only_for_stale_rows(): void
    {
        Agreement::factory()->signed()->create(['title' => 'Fresh Row']);
        Agreement::factory()->signed()->staleProjectStatus()->create(['title' => 'Stale Row']);

        $this->actingAs(User::factory()->legal()->create());

        $component = Livewire::test('agreements-index');
        $html = $component->html();

        $component->assertSee('Stale Row')
            ->assertSee('Fresh Row')
            ->assertSee('Project status last updated');

        $this->assertSame(1, substr_count($html, 'Project status last updated'));
        $this->assertStringContainsString('Stale Row', $html);
        $this->assertStringContainsString('Fresh Row', $html);
    }

    public function test_viewer_does_not_see_the_create_button(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.index'))
            ->assertOk()
            ->assertDontSee('Create agreement');
    }

    public function test_viewer_pagination_total_excludes_pending_rows(): void
    {
        Agreement::factory()->signed()->count(18)->create();
        Agreement::factory()->pending()->count(3)->create();

        $this->actingAs(User::factory()->viewer()->create());

        Livewire::test('agreements-index')
            ->assertSeeHtml('<span class="font-medium">18</span>')
            ->assertDontSeeHtml('<span class="font-medium">21</span>');
    }
}
