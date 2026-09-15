<?php

namespace Tests\Feature\Submission;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubmissionRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function campus(): Campus
    {
        return Campus::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Fictional Test Campus', 'is_institute' => false, 'sort_order' => 1, 'is_active' => true]
        );
    }

    public function test_relations_resolve_on_refetched_models(): void
    {
        $submission = Submission::factory()->create();
        SubmissionActivity::create([
            'submission_id' => $submission->id,
            'user_id' => $submission->created_by,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Submission created',
        ]);

        $refetchedSubmission = Submission::query()->findOrFail($submission->id);
        $this->assertInstanceOf(User::class, $refetchedSubmission->requester);
        $this->assertInstanceOf(Campus::class, $refetchedSubmission->campus);
        $this->assertCount(1, $refetchedSubmission->activities);
        $this->assertInstanceOf(SubmissionActivity::class, $refetchedSubmission->activities->first());

        $activity = SubmissionActivity::query()->findOrFail($refetchedSubmission->activities->first()->id);
        $this->assertInstanceOf(Submission::class, $activity->submission);
        $this->assertInstanceOf(User::class, $activity->user);

        $requester = User::query()->findOrFail($submission->created_by);
        $this->assertCount(1, $requester->submissions);
        $this->assertInstanceOf(Submission::class, $requester->submissions->first());
    }

    public function test_integer_casts_and_nullable_agreement_id_after_refetch(): void
    {
        $submission = Submission::factory()->create();

        $refetched = Submission::query()->findOrFail($submission->id);
        $this->assertIsInt($refetched->created_by);
        $this->assertIsInt($refetched->campus_id);
        $this->assertNull($refetched->agreement_id);

        $linked = Submission::factory()->create([
            'agreement_id' => Agreement::factory()->create()->id,
        ]);

        $refetchedLinked = Submission::query()->findOrFail($linked->id);
        $this->assertIsInt($refetchedLinked->agreement_id);
    }

    public function test_integer_casts_apply_on_model_hydration_independently_of_sqlite(): void
    {
        $submission = Submission::factory()->create();

        // Hydrate a model from raw string attribute values, bypassing the
        // database layer entirely: SQLite already returns integer columns as
        // PHP integers, so only this proves the casts() definitions exist.
        $hydrated = (new Submission)->newFromBuilder([
            'id' => '1',
            'created_by' => (string) $submission->created_by,
            'campus_id' => (string) $submission->campus_id,
            'agreement_id' => null,
        ]);

        $this->assertIsInt($hydrated->created_by);
        $this->assertIsInt($hydrated->campus_id);
        $this->assertSame((int) $submission->created_by, $hydrated->created_by);
        $this->assertNull($hydrated->agreement_id);

        $hydratedLinked = (new Submission)->newFromBuilder([
            'id' => '2',
            'created_by' => '7',
            'campus_id' => '3',
            'agreement_id' => '55',
        ]);

        $this->assertSame(7, $hydratedLinked->created_by);
        $this->assertSame(3, $hydratedLinked->campus_id);
        $this->assertSame(55, $hydratedLinked->agreement_id);
    }

    public function test_submitted_at_is_a_datetime_instance_after_refetch(): void
    {
        $submission = Submission::factory()->create();

        $refetched = Submission::query()->findOrFail($submission->id);
        $this->assertInstanceOf(Carbon::class, $refetched->submitted_at);
    }

    public function test_submission_has_no_agreement_relationship(): void
    {
        $submission = Submission::factory()->create();

        $this->assertFalse(method_exists($submission, 'agreement'));
    }

    public function test_agreement_types_are_exactly_the_six_approved_types(): void
    {
        $this->assertSame(['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'ADDENDUM'], Submission::AGREEMENT_TYPES);
        $this->assertNotContains('MOC', Submission::AGREEMENT_TYPES);
    }

    public function test_protected_fields_are_not_fillable(): void
    {
        $submission = new Submission([
            'created_by' => 999,
            'status' => 'completed',
            'submitted_at' => now()->subDay(),
            'agreement_id' => 999,
            'title' => 'Allowed',
            'campus_id' => $this->campus()->id,
            'partner_name' => 'Partner',
            'purpose' => 'Purpose',
        ]);

        $this->assertNull($submission->created_by);
        $this->assertNull($submission->status);
        $this->assertNull($submission->submitted_at);
        $this->assertNull($submission->agreement_id);
        $this->assertSame('Allowed', $submission->title);
    }

    public function test_hard_deleting_owner_throws_and_submission_remains(): void
    {
        $submission = Submission::factory()->create();
        $owner = User::query()->findOrFail($submission->created_by);

        try {
            $owner->delete();
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException) {
            // fall through to the still-exists assertions below
        }

        $this->assertTrue(Submission::query()->where('id', $submission->id)->exists());
        $this->assertTrue(User::query()->where('id', $owner->id)->exists());
    }

    public function test_hard_deleting_campus_throws_and_submission_remains(): void
    {
        $submission = Submission::factory()->create();
        $campus = Campus::query()->findOrFail($submission->campus_id);

        try {
            $campus->delete();
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException) {
            // fall through to the still-exists assertions below
        }

        $this->assertTrue(Submission::query()->where('id', $submission->id)->exists());
        $this->assertTrue(Campus::query()->where('id', $campus->id)->exists());
    }

    public function test_force_deleting_linked_agreement_nullifies_agreement_id(): void
    {
        $agreement = Agreement::factory()->create();
        $submission = Submission::factory()->create(['agreement_id' => $agreement->id]);

        $agreement->forceDelete();

        $refetched = Submission::query()->findOrFail($submission->id);
        $this->assertNull($refetched->agreement_id);
    }

    public function test_hard_deleting_submission_with_activity_throws_and_records_remain(): void
    {
        $submission = Submission::factory()->create();
        SubmissionActivity::create([
            'submission_id' => $submission->id,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Submission created',
        ]);

        try {
            $submission->delete();
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException) {
            // fall through to the still-exist assertions below
        }

        $this->assertTrue(Submission::query()->where('id', $submission->id)->exists());
        $this->assertTrue(SubmissionActivity::query()->where('submission_id', $submission->id)->exists());
    }

    public function test_deleting_activity_actor_nullifies_user_id_and_keeps_activity(): void
    {
        $submission = Submission::factory()->create();
        $actor = User::factory()->create();
        $activity = SubmissionActivity::create([
            'submission_id' => $submission->id,
            'user_id' => $actor->id,
            'type' => SubmissionActivity::TYPE_SUBMISSION_CREATED,
            'description' => 'Submission created',
        ]);

        $actor->delete();

        $refetchedActivity = SubmissionActivity::query()->findOrFail($activity->id);
        $this->assertNull($refetchedActivity->user_id);
    }
}
