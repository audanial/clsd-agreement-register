<?php

namespace Tests\Feature\Submission;

use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Role-aware behaviour of the submissions index: database-level scoping for
 * Requesting Staff, the shared Legal/Admin queue, and requester-controlled
 * content rendered safely.
 */
class SubmissionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_list_contains_only_their_own_records(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();

        Submission::factory()->create(['created_by' => $owner->id, 'title' => 'Owned Alpha']);
        Submission::factory()->create(['created_by' => $owner->id, 'title' => 'Owned Beta']);
        Submission::factory()->create(['created_by' => $other->id, 'title' => 'Foreign One']);
        Submission::factory()->create(['created_by' => $other->id, 'title' => 'Foreign Two']);

        $this->actingAs(User::query()->findOrFail($owner->id));

        Livewire::test('submissions.submissions-index')
            ->assertSee('Owned Alpha')
            ->assertSee('Owned Beta')
            ->assertDontSee('Foreign One')
            ->assertDontSee('Foreign Two');
    }

    public function test_requester_pagination_counts_exclude_other_requesters_records(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();

        // 17 own + 3 foreign: an unscoped fetch would put foreign rows on page 1.
        Submission::factory()->count(17)->sequence(
            fn ($sequence) => ['title' => 'Owned number '.$sequence->index, 'submitted_at' => now()->addMinutes($sequence->index)]
        )->create(['created_by' => $owner->id]);
        Submission::factory()->count(3)->sequence(
            fn ($sequence) => ['title' => 'Foreign number '.$sequence->index, 'submitted_at' => now()->addMinutes(200 + $sequence->index)]
        )->create(['created_by' => $other->id]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $component = Livewire::test('submissions.submissions-index');

        $this->assertSame(
            17,
            $component->instance()->submissions->total(),
            'The paginator total must be computed from the ownership-scoped query.'
        );

        $html = $component->html();

        foreach (['Foreign number 0', 'Foreign number 1', 'Foreign number 2'] as $foreign) {
            $this->assertStringNotContainsString($foreign, $html);
        }

        // Page 1 holds the 15 newest owned rows; page 2 holds the remaining two.
        $this->assertStringContainsString('Owned number 16', $html);
        $this->assertStringNotContainsString('Owned number 0', $html);
        $this->assertStringContainsString('Owned number 0', Livewire::test('submissions.submissions-index')->call('gotoPage', 2)->html());
    }

    public function test_requester_output_excludes_another_requesters_title_identity_and_activity(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();
        $foreignActor = User::factory()->create(['name' => 'Distinctive Actor Name']);

        $foreignSubmission = Submission::factory()->create(['created_by' => $other->id, 'title' => 'Secret Foreign Title']);
        SubmissionActivity::create([
            'submission_id' => $foreignSubmission->id,
            'user_id' => $foreignActor->id,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Distinctive audit description XYZ',
        ]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $html = Livewire::test('submissions.submissions-index')->html();

        $this->assertStringNotContainsString('Secret Foreign Title', $html);
        $this->assertStringNotContainsString($other->name, $html);
        $this->assertStringNotContainsString('Distinctive audit description XYZ', $html);
        $this->assertStringNotContainsString('Distinctive Actor Name', $html);
        // The requester's queue never renders a requester-identity column.
        $this->assertStringNotContainsString('Requested by', $html);
    }

    public function test_legal_and_admin_see_all_submissions(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();

        Submission::factory()->create(['created_by' => $owner->id, 'title' => 'Alpha Request']);
        Submission::factory()->create(['created_by' => $other->id, 'title' => 'Beta Request']);

        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            Livewire::test('submissions.submissions-index')
                ->assertSee('Submission Queue')
                ->assertSee('Alpha Request')
                ->assertSee('Beta Request');
        }
    }

    public function test_legal_and_admin_queue_identifies_the_requester(): void
    {
        $requester = User::factory()->requester()->create(['name' => 'Fictional Requester']);
        Submission::factory()->create(['created_by' => $requester->id]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('submissions.submissions-index')
            ->assertSee('Requested by')
            ->assertSee('Fictional Requester');
    }

    public function test_null_agreement_type_renders_as_not_sure(): void
    {
        $owner = User::factory()->requester()->create();
        Submission::factory()->create(['created_by' => $owner->id, 'agreement_type' => null]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        Livewire::test('submissions.submissions-index')->assertSee('Not sure');
    }

    public function test_requester_sees_the_create_action_and_legal_does_not(): void
    {
        $owner = User::factory()->requester()->create();
        Submission::factory()->create(['created_by' => $owner->id]);

        $this->actingAs(User::query()->findOrFail($owner->id));
        $html = Livewire::test('submissions.submissions-index')->html();

        $this->assertStringContainsString('New Submission', $html);
        $this->assertStringContainsString(route('submissions.create'), $html);

        $this->actingAs(User::factory()->legal()->create());
        $html = Livewire::test('submissions.submissions-index')->html();

        $this->assertStringNotContainsString('New Submission', $html);
    }

    public function test_index_renders_an_appropriate_empty_state(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        Livewire::test('submissions.submissions-index')
            ->assertSee('No submissions yet');
    }

    public function test_legal_queue_renders_an_appropriate_empty_state(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('submissions.submissions-index')
            ->assertSee('No submissions yet');
    }

    public function test_requester_controlled_html_is_escaped_in_the_list(): void
    {
        $owner = User::factory()->requester()->create();
        Submission::factory()->create([
            'created_by' => $owner->id,
            'title' => "<script>alert('xss')</script>",
            'partner_name' => '<img src=x onerror=alert(1)>',
            'purpose' => 'Purpose',
        ]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $html = Livewire::test('submissions.submissions-index')->html();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Livewire's test harness renders an abort() into a response instead of
     * rethrowing it, so a denied update surfaces as a non-OK response whose
     * status is asserted exactly. A denied update also invalidates the
     * component snapshot, so each testable performs a single update after the
     * session switch. Every denial is paired with an in-test positive control
     * proving the component renders for a legitimate user first.
     */
    public function test_an_inactive_requester_is_denied_on_the_index_update_path(): void
    {
        $owner = User::factory()->requester()->create();
        Submission::factory()->create(['created_by' => $owner->id, 'title' => 'Inactive Owner Title']);

        // Positive control: the active owner renders their own row.
        $this->actingAs(User::query()->findOrFail($owner->id));

        $component = Livewire::test('submissions.submissions-index');
        $this->assertStringContainsString('Inactive Owner Title', $component->html());

        // The account is deactivated in the database, and the session is
        // re-established against the stored inactive state.
        $owner->forceFill(['is_active' => false])->save();
        $this->actingAs(User::query()->findOrFail($owner->id));

        // Livewire::test bypasses route middleware — the same path a crafted
        // update request would take — so the component's boot() check is what
        // must deny here; without it the row above would render.
        $component->call('$refresh')->assertForbidden();
    }

    public function test_inactive_admin_and_legal_are_denied_on_the_index_update_path(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $user = User::factory()->{$role}()->create();
            Submission::factory()->create(['title' => 'Queue Row For '.ucfirst($role)]);

            // Positive control: the active role sees the whole queue.
            $this->actingAs(User::query()->findOrFail($user->id));

            $component = Livewire::test('submissions.submissions-index');
            $this->assertStringContainsString('Queue Row For '.ucfirst($role), $component->html());

            // Deactivate in the database and authenticate the stored state.
            $user->forceFill(['is_active' => false])->save();
            $this->actingAs(User::query()->findOrFail($user->id));

            $component->call('$refresh')->assertForbidden();
        }
    }

    public function test_a_viewer_cannot_render_the_list_through_a_livewire_update(): void
    {
        // The submission is owned by the viewer: with the component's boot()
        // viewAny check removed, the requester-scoped query would expose this
        // exact row on the update response.
        $viewer = User::factory()->viewer()->create();
        Submission::factory()->create(['created_by' => $viewer->id, 'title' => 'Viewer Owned Title']);

        // Positive control: a legitimate requester renders their own row.
        $requester = User::factory()->requester()->create();
        Submission::factory()->create(['created_by' => $requester->id, 'title' => 'Legit Requester Title']);

        $this->actingAs(User::query()->findOrFail($requester->id));

        $component = Livewire::test('submissions.submissions-index');
        $this->assertStringContainsString('Legit Requester Title', $component->html());

        // The viewer replays the snapshot on the update path.
        $this->actingAs(User::query()->findOrFail($viewer->id));

        $component->call('$refresh')->assertForbidden();
    }
}
