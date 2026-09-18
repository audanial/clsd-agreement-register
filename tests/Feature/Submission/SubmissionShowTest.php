<?php

namespace Tests\Feature\Submission;

use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The read-only submission detail page: ownership for Requesting Staff, the
 * shared Legal/Admin view with requester identity, the initial audit event,
 * escaping, and denial on the real Livewire update/hydration path.
 */
class SubmissionShowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function submissionFor(User $owner, array $attributes = []): Submission
    {
        $submission = Submission::factory()->create([
            'created_by' => $owner->id,
            'title' => 'Detail Test Title',
            'partner_name' => 'Detail Test Partner',
            'purpose' => 'Detail test purpose.',
            ...$attributes,
        ]);

        SubmissionActivity::create([
            'submission_id' => $submission->id,
            'user_id' => $owner->id,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Submission created',
        ]);

        return Submission::query()->findOrFail($submission->id);
    }

    public function test_requesting_staff_can_open_their_own_submission(): void
    {
        // Freeze the server clock so both timestamps render deterministically:
        // 10:00 UTC is 6:00 PM Malaysian time, the <x-datetime> display format.
        Carbon::setTestNow('2026-09-14 10:00:00');

        $owner = User::factory()->requester()->create(['name' => 'Fictional Owner']);
        $submission = $this->submissionFor($owner, ['agreement_type' => 'MOU']);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $this->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('#'.$submission->id)
            ->assertSee('Detail Test Title')
            ->assertSee('TEST')
            ->assertSee('Fictional Test Campus')
            ->assertSee('Detail Test Partner')
            ->assertSee('MOU')
            ->assertSee('Detail test purpose.')
            ->assertSee('Pending')
            ->assertSee('14 Sep 2026, 6:00 PM')
            ->assertSee('Submission created')
            ->assertSee('Fictional Owner')
            ->assertDontSee('Requested by');
    }

    public function test_legal_and_admin_detail_identifies_the_requester(): void
    {
        Carbon::setTestNow('2026-09-14 10:00:00');

        $owner = User::factory()->requester()->create(['name' => 'Fictional Owner']);
        $submission = $this->submissionFor($owner, ['agreement_type' => 'MOU']);

        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('submissions.show', $submission))
                ->assertOk()
                ->assertSee('Detail Test Title')
                ->assertSee('Requested by')
                ->assertSee('Fictional Owner')
                ->assertSee('Created')
                ->assertSee('14 Sep 2026, 6:00 PM')
                ->assertSee('Submission created');
        }
    }

    public function test_detail_displays_the_initial_activity_history(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $this->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Activity')
            ->assertSee('Submission created');
    }

    public function test_cross_requester_direct_access_returns_404_and_exposes_no_content(): void
    {
        $owner = User::factory()->requester()->create();
        $foreignActor = User::factory()->create(['name' => 'Distinctive Show Actor']);
        $submission = Submission::factory()->create([
            'created_by' => $owner->id,
            'title' => 'Detail Test Title',
            'partner_name' => 'Detail Test Partner',
            'purpose' => 'Detail test purpose.',
        ]);
        SubmissionActivity::create([
            'submission_id' => $submission->id,
            'user_id' => $foreignActor->id,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Distinctive audit description ABC',
        ]);

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('submissions.show', $submission))
            ->assertNotFound()
            ->assertDontSee('Detail Test Title')
            ->assertDontSee('Detail test purpose.')
            ->assertDontSee('Distinctive audit description ABC')
            ->assertDontSee('Distinctive Show Actor');

        // Positive control: the owner sees the record and its activity.
        $this->actingAs(User::query()->findOrFail($owner->id));

        $this->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Detail Test Title')
            ->assertSee('Distinctive audit description ABC');
    }

    /**
     * Livewire's test harness renders an abort() into a response instead of
     * rethrowing it, so a denied update surfaces as a non-OK response whose
     * status is asserted exactly. A denied update also invalidates the
     * component snapshot, so each testable performs a single update after the
     * session switch. Every denial is paired with an in-test positive control
     * proving the component renders for a legitimate user first.
     */
    public function test_cross_requester_denial_holds_on_a_real_livewire_update_request(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        // The owner opens the page and receives a hydrated component snapshot...
        $this->actingAs(User::query()->findOrFail($owner->id));

        $component = Livewire::test('submissions.submission-show', ['submission' => $submission]);
        $this->assertStringContainsString('Detail Test Title', $component->html());

        // ...then a different requester replays that snapshot on the
        // /livewire/update path. The component's boot() hook re-authorization
        // must deny as not found, regardless of the snapshot's original owner.
        $this->actingAs(User::factory()->requester()->create());

        $component->call('$refresh')->assertNotFound();
    }

    public function test_the_owner_can_still_refresh_their_own_component(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        $this->actingAs(User::query()->findOrFail($owner->id));

        Livewire::test('submissions.submission-show', ['submission' => $submission])
            ->call('$refresh')
            ->assertOk()
            ->assertSee('Detail Test Title');
    }

    public function test_an_inactive_requester_is_denied_on_the_livewire_update_path(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $component = Livewire::test('submissions.submission-show', ['submission' => $submission]);
        $this->assertStringContainsString('Detail Test Title', $component->html());

        // Deactivation is persisted to the database, and the session is then
        // authenticated against the stored inactive user state.
        $owner->forceFill(['is_active' => false])->save();
        $this->actingAs(User::query()->findOrFail($owner->id));

        $component->call('$refresh')->assertForbidden();
    }

    public function test_inactive_admin_and_legal_are_denied_on_the_livewire_update_path(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $owner = User::factory()->requester()->create();
            $submission = $this->submissionFor($owner);
            $staffUser = User::factory()->{$role}()->create();

            // Positive control: the active staff member opens the submission.
            $this->actingAs(User::query()->findOrFail($staffUser->id));

            $component = Livewire::test('submissions.submission-show', ['submission' => $submission]);
            $this->assertStringContainsString('Detail Test Title', $component->html());

            // Deactivate in the database and authenticate the stored state.
            $staffUser->forceFill(['is_active' => false])->save();
            $this->actingAs(User::query()->findOrFail($staffUser->id));

            $component->call('$refresh')->assertForbidden();
        }
    }

    public function test_requester_controlled_html_is_escaped_on_detail(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = Submission::factory()->create([
            'created_by' => $owner->id,
            'title' => "<script>alert('xss')</script>",
            'partner_name' => '<img src=x onerror=alert(1)>',
            'purpose' => 'Detail test purpose.',
        ]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $response = $this->get(route('submissions.show', $submission));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_detail_is_read_only_with_no_mutation_controls(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        $this->actingAs(User::factory()->legal()->create());

        $html = $this->get(route('submissions.show', $submission))->getContent();

        $this->assertStringNotContainsString('wire:click', $html);
        $this->assertStringNotContainsString('wire:submit', $html);
        $this->assertStringNotContainsString('agreement_id', $html);
        $this->assertStringNotContainsString('Delete', $html);
        $this->assertStringNotContainsString('Archive', $html);
    }

    public function test_detail_does_not_expose_the_dormant_agreement_link(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = $this->submissionFor($owner);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $html = $this->get(route('submissions.show', $submission))->getContent();

        $this->assertStringNotContainsString('agreement_id', $html);

        // Asserted against the component HTML only: since LP1 Amendment 2 the
        // global navigation legitimately shows the Register link to requesters,
        // so the full page now contains route('agreements.index') in the nav.
        $componentHtml = Livewire::test('submissions.submission-show', ['submission' => $submission])->html();

        $this->assertStringNotContainsString(route('agreements.index'), $componentHtml);
    }
}
