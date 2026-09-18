<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_can_view_a_pending_agreement(): void
    {
        $agreement = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($agreement->title);
    }

    public function test_viewer_gets_404_for_a_pending_agreement(): void
    {
        $agreement = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))->assertStatus(404);
    }

    public function test_requester_gets_404_for_a_pending_agreement(): void
    {
        $agreement = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.show', $agreement))->assertStatus(404);
    }

    /**
     * Livewire restores public Eloquent model properties through an unscoped
     * restoration query (newQueryForRestoration()), so the pending global scope
     * does not re-apply on /livewire/update. This test exercises that
     * stale-snapshot path: an agreement visible when the component was mounted
     * must stop rendering once it moves to document_status = pending. The direct
     * DB update deliberately bypasses the Eloquent scope and application
     * mutation code.
     */
    public function test_a_stale_snapshot_cannot_render_an_agreement_after_it_becomes_pending(): void
    {
        foreach (['requester', 'viewer'] as $role) {
            $title = 'Stale Snapshot Probe '.ucfirst($role);
            $agreement = Agreement::factory()->signed()->create(['title' => $title]);

            $this->actingAs(User::factory()->{$role}()->create());

            // Positive control: the agreement is visible while still signed.
            $component = Livewire::test('agreement-show', ['agreement' => $agreement]);
            $component->assertSee($title);

            DB::table('agreements')
                ->where('id', $agreement->getKey())
                ->update(['document_status' => 'pending']);

            $component->call('$refresh')->assertNotFound();
        }
    }

    public function test_requester_can_view_a_signed_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($agreement->title);
    }

    public function test_requester_does_not_see_mutation_controls(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertDontSee('Change status')
            ->assertDontSee('Archive this agreement')
            ->assertDontSee('Update status');
    }

    public function test_requester_cannot_manually_archive_an_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->requester()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->call('archive')
            ->assertStatus(403);

        $agreement->refresh();

        $this->assertNull($agreement->archived_at);
    }

    public function test_requester_cannot_change_status_through_a_livewire_update(): void
    {
        $agreement = Agreement::factory()->signed()->create([
            'document_status' => 'signed',
            'project_status' => 'ongoing',
        ]);

        $this->actingAs(User::factory()->requester()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('document_status', 'pending')
            ->set('project_status', 'completed')
            ->call('updateStatus')
            ->assertStatus(403);

        $agreement->refresh();

        $this->assertSame('signed', $agreement->document_status);
        $this->assertSame('ongoing', $agreement->project_status);
    }

    public function test_viewer_can_view_a_signed_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($agreement->title);
    }

    public function test_indefinite_expiry_renders_as_indefinite_not_blank(): void
    {
        $agreement = Agreement::factory()->signed()->create(['expiry_date' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Indefinite')
            ->assertDontSee('01 Jan 1970');
    }

    public function test_editing_an_agreement_with_a_null_pic_name_renders_a_dash(): void
    {
        $agreement = Agreement::factory()->signed()->create(['pic_name' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('—');
    }

    public function test_detail_page_shows_one_date_signed_row(): void
    {
        $agreement = Agreement::factory()->signed()->create(['agreement_date' => '2024-08-12']);

        $this->actingAs(User::factory()->legal()->create());

        $response = $this->get(route('agreements.show', $agreement));

        $response->assertOk();
        $this->assertStringContainsString('Date Signed', $response->content());
        $this->assertStringContainsString('12 Aug 2024', $response->content());
        $this->assertStringNotContainsString('Effective date', $response->content());
        $this->assertStringNotContainsString('Agreement date', $response->content());
    }

    public function test_activity_feed_renders_newest_first(): void
    {
        $agreement = Agreement::factory()->signed()->create();
        $agreement->activities()->create([
            'user_id' => null,
            'type' => 'comment',
            'description' => 'First comment',
            'created_at' => now()->subHour(),
        ]);
        $agreement->activities()->create([
            'user_id' => null,
            'type' => 'comment',
            'description' => 'Second comment',
            'created_at' => now(),
        ]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->assertSeeInOrder(['Second comment', 'First comment']);
    }

    public function test_stale_badge_appears_on_detail_for_a_stale_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->staleProjectStatus()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Stale');
    }

    public function test_never_updated_project_status_renders_as_stale(): void
    {
        $agreement = Agreement::factory()->signed()->create(['project_status_updated_at' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Stale');
    }

    public function test_viewer_does_not_see_status_change_controls(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertDontSee('Change status');
    }

    public function test_legal_can_manually_archive_an_agreement_with_terminated_reason(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->call('archive');

        $agreement->refresh();

        $this->assertNotNull($agreement->archived_at);
        $this->assertSame('terminated', $agreement->archive_reason);
        $this->assertDatabaseHas('agreement_activities', [
            'agreement_id' => $agreement->id,
            'type' => 'archived',
        ]);
    }

    public function test_viewer_cannot_manually_archive_an_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->call('archive')
            ->assertStatus(403);

        $agreement->refresh();

        $this->assertNull($agreement->archived_at);
    }

    public function test_an_already_archived_agreement_does_not_show_the_archive_action(): void
    {
        $agreement = Agreement::factory()->signed()->create([
            'archived_at' => now()->subDay(),
            'archive_reason' => 'expired',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertDontSee('Archive this agreement');
    }

    public function test_manually_archived_agreement_is_excluded_from_the_scheduled_commands_update(): void
    {
        $terminated = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
            'archived_at' => now()->subDay(),
            'archive_reason' => 'terminated',
        ]);

        Artisan::call('agreements:archive-expired');

        $terminated->refresh();

        $this->assertSame('terminated', $terminated->archive_reason);
    }
}
