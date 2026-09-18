<?php

namespace Tests\Feature\Submission;

use App\Models\Campus;
use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The requester-only submission form: campus/type option integrity, trusted
 * creation through the CreateSubmission boundary, validation mapping, and the
 * absence of any protected controls.
 */
class SubmissionFormTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = User::factory()->requester()->create();
        $this->actingAs(User::query()->findOrFail($this->requester->id));
    }

    private function fictionalCampus(): Campus
    {
        return Campus::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Fictional Test Campus', 'is_institute' => false, 'sort_order' => 1, 'is_active' => true]
        );
    }

    public function test_form_lists_only_active_non_tbd_campuses(): void
    {
        $active = $this->fictionalCampus();
        Campus::create(['code' => 'INA', 'name' => 'Inactive Campus', 'is_institute' => false, 'sort_order' => 2, 'is_active' => false]);
        Campus::create(['code' => 'TBD', 'name' => 'Unassigned Campus', 'is_institute' => false, 'sort_order' => 999, 'is_active' => true]);

        $options = Livewire::test('submissions.submission-form')
            ->instance()
            ->campuses;

        $this->assertSame(
            [$active->id],
            $options->pluck('id')->all(),
            'The dropdown must contain only active campuses and must exclude TBD.'
        );
    }

    public function test_agreement_type_select_offers_exactly_the_seven_options_in_order(): void
    {
        $html = Livewire::test('submissions.submission-form')->html();

        $document = new \DOMDocument;
        $previousErrorState = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        $xpath = new \DOMXPath($document);

        // Exactly one agreement_type select may exist. The wire:model attribute
        // contains a colon, so XPath needs name() instead of a namespace prefix.
        $selects = $xpath->query('//select[@*[name()="wire:model"] = "agreement_type"]');
        $this->assertSame(1, $selects->length, 'The agreement_type select must be rendered exactly once.');

        // Every option element inside that select — including any value-less
        // option — is captured, so a missing, duplicate, reordered, extra
        // (e.g. <option>LEASE</option>), MOC, or malformed option fails.
        $options = $xpath->query('.//option', $selects->item(0));
        $this->assertCount(
            7,
            $options,
            'The agreement_type select must offer exactly seven options.'
        );

        $values = [];
        foreach ($options as $index => $option) {
            $this->assertTrue(
                $option->hasAttribute('value'),
                'Every agreement_type option must carry an explicit value attribute; option #'
                .$index.' has none: '.$document->saveHTML($option)
            );

            $values[] = $option->getAttribute('value');
        }

        // The exact ordered option list: '' is the "Not sure" blank option.
        $this->assertSame(
            ['', 'LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'ADDENDUM'],
            $values,
        );

        $this->assertSame(
            'Not sure',
            trim($options[0]->textContent),
            'The blank option must be labelled "Not sure".'
        );

        $this->assertStringNotContainsString('MOC', $document->saveHTML($selects->item(0)));
    }

    public function test_valid_livewire_submission_creates_one_submission_and_one_activity_and_redirects(): void
    {
        $campus = $this->fictionalCampus();

        Livewire::test('submissions.submission-form')
            ->set('title', 'Fictional Portal Title')
            ->set('campus_id', $campus->id)
            ->set('partner_name', 'Fictional Portal Partner')
            ->set('agreement_type', 'MOU')
            ->set('purpose', 'A fictional portal purpose.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('submissions.show', Submission::query()->firstOrFail()));

        $refetched = Submission::query()->firstOrFail();

        $this->assertSame($this->requester->id, $refetched->created_by);
        $this->assertSame('Fictional Portal Title', $refetched->title);
        $this->assertSame($campus->id, $refetched->campus_id);
        $this->assertSame(1, Submission::query()->count());
        $this->assertSame(1, SubmissionActivity::query()->count());

        $activity = SubmissionActivity::query()->firstOrFail();
        $this->assertSame(SubmissionActivity::TYPE_SUBMISSION_CREATED, $activity->type);
        $this->assertSame($this->requester->id, $activity->user_id);
        $this->assertSame($refetched->id, $activity->submission_id);
    }

    public function test_not_sure_persists_as_null(): void
    {
        $campus = $this->fictionalCampus();

        Livewire::test('submissions.submission-form')
            ->set('title', 'Fictional Portal Title')
            ->set('campus_id', $campus->id)
            ->set('partner_name', 'Fictional Portal Partner')
            ->set('agreement_type', '')
            ->set('purpose', 'A fictional portal purpose.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(DB::table('submissions')->value('agreement_type'));
    }

    public function test_invalid_form_data_reports_the_field_and_writes_neither_table(): void
    {
        $cases = [
            'empty title' => ['', 'title'],
            'empty partner name' => ['', 'partner_name'],
            'overlong purpose' => [str_repeat('a', 5001), 'purpose'],
            'MOC agreement type' => ['MOC', 'agreement_type'],
            'inactive campus' => [
                Campus::create(['code' => 'INA', 'name' => 'Inactive Campus', 'is_institute' => false, 'sort_order' => 9, 'is_active' => false])->id,
                'campus_id',
            ],
        ];

        foreach ($cases as $case => [$value, $field]) {
            $component = Livewire::test('submissions.submission-form')
                ->set('title', 'Fictional Portal Title')
                ->set('campus_id', $this->fictionalCampus()->id)
                ->set('partner_name', 'Fictional Portal Partner')
                ->set('agreement_type', 'MOU')
                ->set('purpose', 'A fictional portal purpose.');

            $component->set($field, $value);

            $component->call('save')->assertHasErrors([$field]);

            $this->assertSame(0, DB::table('submissions')->count(), "{$case} wrote a submission.");
            $this->assertSame(0, DB::table('submission_activities')->count(), "{$case} wrote an activity.");
        }
    }

    public function test_missing_required_fields_report_their_own_field(): void
    {
        foreach (['title', 'partner_name', 'purpose'] as $field) {
            $input = [
                'title' => 'Fictional Portal Title',
                'campus_id' => $this->fictionalCampus()->id,
                'partner_name' => 'Fictional Portal Partner',
                'agreement_type' => 'MOU',
                'purpose' => 'A fictional portal purpose.',
            ];
            $input[$field] = '';

            $component = Livewire::test('submissions.submission-form');
            foreach ($input as $key => $value) {
                $component->set($key, $value);
            }

            $component->call('save')->assertHasErrors([$field]);

            $this->assertSame(0, DB::table('submissions')->count());
            $this->assertSame(0, DB::table('submission_activities')->count());
        }
    }

    public function test_form_exposes_no_protected_ownership_status_timestamp_or_agreement_controls(): void
    {
        $html = Livewire::test('submissions.submission-form')->html();

        foreach (['status', 'created_by', 'submitted_at', 'agreement_id'] as $protected) {
            $this->assertStringNotContainsString(
                "wire:model=\"{$protected}\"",
                $html,
                "The form must not expose a {$protected} control."
            );
        }

        // No draft or save-and-continue feature exists in LP1.
        $this->assertStringNotContainsString('wire:click="draft"', $html);
    }

    public function test_form_shows_the_private_and_confidential_notice(): void
    {
        Livewire::test('submissions.submission-form')->assertSee('Private & Confidential');
    }

    /**
     * A requester legitimately opens the form and receives a hydrated
     * snapshot; a Legal user then replays it on the update path. Livewire's
     * harness renders the boot() denial into a response instead of rethrowing
     * it, so the forbidden status is asserted exactly on the replayed update.
     */
    public function test_a_legal_session_cannot_submit_through_a_replayed_form_snapshot(): void
    {
        $campus = $this->fictionalCampus();

        // Positive control: the requester's initial render shows the form.
        $this->actingAs(User::query()->findOrFail($this->requester->id));

        $component = Livewire::test('submissions.submission-form')
            ->set('title', 'Legal Replay')
            ->set('campus_id', $campus->id)
            ->set('partner_name', 'Fictional Partner')
            ->set('agreement_type', 'MOU')
            ->set('purpose', 'Purpose');
        $this->assertStringContainsString('wire:submit="save"', $component->html());

        $this->actingAs(User::factory()->legal()->create());

        $component->call('save')->assertForbidden();

        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('submission_activities')->count());
    }

    /**
     * The form must re-authorize independently of CreateSubmission. A
     * harmless update (no save action, no CreateSubmission invocation) is
     * denied with 403 for every non-requester role, so removing the
     * component's boot() authorization fails this test even though the
     * action keeps its own authorization.
     */
    public function test_admin_legal_and_viewer_cannot_refresh_a_replayed_form_snapshot(): void
    {
        foreach (['admin', 'legal', 'viewer'] as $role) {
            // Positive control: the legitimate requester renders the form and
            // receives the snapshot each role then replays.
            $this->actingAs(User::query()->findOrFail($this->requester->id));

            $component = Livewire::test('submissions.submission-form');
            $this->assertStringContainsString('wire:submit="save"', $component->html());

            $this->actingAs(User::factory()->{$role}()->create());

            $component->call('$refresh')->assertForbidden();

            $this->assertSame(0, DB::table('submissions')->count());
            $this->assertSame(0, DB::table('submission_activities')->count());
        }
    }

    /**
     * The form re-authorization also holds for a deactivated requester on the
     * update path: the account is deactivated in the database and the session
     * is authenticated against the stored inactive state.
     */
    public function test_an_inactive_requester_cannot_refresh_the_form_component(): void
    {
        $this->actingAs(User::query()->findOrFail($this->requester->id));

        $component = Livewire::test('submissions.submission-form');
        $this->assertStringContainsString('wire:submit="save"', $component->html());

        $this->requester->forceFill(['is_active' => false])->save();
        $this->actingAs(User::query()->findOrFail($this->requester->id));

        $component->call('$refresh')->assertForbidden();

        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('submission_activities')->count());
    }
}
