<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_type_column_is_not_rendered_in_the_list(): void
    {
        Agreement::factory()->signed()->create(['title' => 'Type Column Test']);

        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreements-index')->html();

        $this->assertStringNotContainsString('>Type</th>', $html);
        $this->assertStringContainsString('Type</label>', $html);
    }

    public function test_columns_render_in_the_agreed_order(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreements-index')->html();

        preg_match_all('/<th[^>]*>(.*?)<\/th>/i', $html, $matches);

        $headers = array_map('trim', array_map('strip_tags', $matches[1]));

        $this->assertSame(
            ['Title', 'Partner', 'Duration', 'Scope', 'Status', 'Project', 'PIC', 'Campus', ''],
            $headers
        );
    }

    public function test_scope_is_truncated_in_the_list(): void
    {
        $scope = str_repeat('a', 100);

        $agreement = Agreement::factory()->signed()->create([
            'title' => 'Scope Truncation Test',
            'scope' => $scope,
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreements-index')->html();

        $this->assertStringContainsString(Str::limit($scope, 80), $html);
        $this->assertSame(1, substr_count($html, $scope));
        $this->assertStringContainsString('title="'.$scope.'"', $html);
    }

    public function test_full_scope_remains_available_on_the_detail_page(): void
    {
        $scope = str_repeat('b', 100);

        $agreement = Agreement::factory()->signed()->create([
            'title' => 'Full Scope Detail Test',
            'scope' => $scope,
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($scope);
    }

    public function test_null_scope_renders_as_a_dash(): void
    {
        Agreement::factory()->signed()->create([
            'title' => 'Null Scope Test',
            'scope' => null,
            'pic_name' => 'Ahmad bin Osman',
            'agreement_date' => '2024-08-12',
            'expiry_date' => '2027-08-12',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreements-index')->html();

        $this->assertStringContainsString('<td class="px-4 py-3 text-sm" title="">—</td>', $html);
    }

    public function test_pic_column_shows_the_pic_name_and_a_dash_when_unset(): void
    {
        Agreement::factory()->signed()->create([
            'title' => 'With PIC',
            'pic_name' => 'Ahmad bin Osman',
            'scope' => 'Some scope text',
            'agreement_date' => '2024-08-12',
            'expiry_date' => '2027-08-12',
        ]);

        Agreement::factory()->signed()->create([
            'title' => 'Without PIC',
            'pic_name' => null,
            'scope' => 'Some scope text',
            'agreement_date' => '2024-08-12',
            'expiry_date' => '2027-08-12',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreements-index')->html();

        $this->assertStringContainsString('Ahmad bin Osman', $html);
        $this->assertStringContainsString('With PIC', $html);
        $this->assertStringContainsString('Without PIC', $html);

        $tableBody = strstr($html, '<tbody');
        $this->assertSame(1, substr_count($tableBody, '—'));
    }

    public function test_search_still_matches_title_and_partner_only(): void
    {
        $titleMatch = Agreement::factory()->signed()->create(['title' => 'Alpha Title Match']);
        $titleMatch->partner->update(['name' => 'BetaCorp']);

        $partnerMatch = Agreement::factory()->signed()->create(['title' => 'Delta Title']);
        $partnerMatch->partner->update(['name' => 'AlphaCorp']);

        $scopeMatch = Agreement::factory()->signed()->create([
            'title' => 'Zeta Title',
            'scope' => 'Alpha appears only in the scope',
        ]);
        $scopeMatch->partner->update(['name' => 'EtaCorp']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreements-index', ['search' => 'Alpha'])
            ->assertSee('Alpha Title Match')
            ->assertSee('Delta Title')
            ->assertDontSee('Zeta Title');
    }
}
