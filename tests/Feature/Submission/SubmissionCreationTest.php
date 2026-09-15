<?php

namespace Tests\Feature\Submission;

use App\Actions\CreateSubmission;
use App\Actions\RecordSubmissionActivity;
use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubmissionCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function validInput(): array
    {
        $campus = Campus::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Fictional Test Campus', 'is_institute' => false, 'sort_order' => 1, 'is_active' => true]
        );

        return [
            'title' => 'Fictional Submission Title',
            'campus_id' => $campus->id,
            'partner_name' => 'Fictional Partner Sdn Bhd',
            'agreement_type' => 'MOU',
            'purpose' => 'A fictional purpose for testing.',
        ];
    }

    public function test_active_requester_can_create_a_submission(): void
    {
        $user = User::factory()->requester()->create();
        $this->actingAs($user);

        $submission = app(CreateSubmission::class)($this->validInput());

        $refetched = Submission::query()->findOrFail($submission->id);
        $this->assertSame($user->id, $refetched->created_by);
        $this->assertSame(Submission::STATUS_PENDING, $refetched->status);
        $this->assertNotNull($refetched->submitted_at);
        $this->assertNull($refetched->agreement_id);

        $this->assertSame(1, SubmissionActivity::query()->where('submission_id', $submission->id)->count());
        $activity = SubmissionActivity::query()->where('submission_id', $submission->id)->first();
        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame(SubmissionActivity::TYPE_SUBMISSION_CREATED, $activity->type);
    }

    public function test_injected_owner_status_submitted_at_and_agreement_id_are_ignored(): void
    {
        // Freeze server time so the injected value and the real submitted_at
        // provably differ; submitted_at stays exclusively server-controlled.
        $frozen = Carbon::parse('2026-09-14 10:00:00');
        Carbon::setTestNow($frozen);

        $user = User::factory()->requester()->create();
        $otherUser = User::factory()->requester()->create();
        $agreement = Agreement::factory()->create();
        $injectedSubmittedAt = '2020-01-01 00:00:00';
        $this->actingAs($user);

        $submission = app(CreateSubmission::class)([
            ...$this->validInput(),
            'created_by' => $otherUser->id,
            'status' => 'completed',
            'submitted_at' => $injectedSubmittedAt,
            'agreement_id' => $agreement->id,
        ]);

        $refetched = Submission::query()->findOrFail($submission->id);
        $this->assertSame($user->id, $refetched->created_by);
        $this->assertSame(Submission::STATUS_PENDING, $refetched->status);
        $this->assertNull($refetched->agreement_id);

        $this->assertInstanceOf(Carbon::class, $refetched->submitted_at);
        $this->assertTrue($refetched->submitted_at->equalTo($frozen));
        $this->assertNotSame(
            $injectedSubmittedAt,
            $refetched->submitted_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_unauthenticated_user_cannot_create(): void
    {
        try {
            app(CreateSubmission::class)($this->validInput());
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            // fall through to the write-nothing assertions below
        }

        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('submission_activities')->count());
    }

    public function test_admin_legal_and_viewer_cannot_create(): void
    {
        foreach (['admin', 'legal', 'viewer'] as $role) {
            $user = User::factory()->{$role}()->create();
            $this->actingAs($user);

            try {
                app(CreateSubmission::class)($this->validInput());
                $this->fail("{$role} should not be able to create a submission.");
            } catch (AuthorizationException) {
                // fall through to the write-nothing assertions below
            }

            $this->assertSame(0, DB::table('submissions')->count());
            $this->assertSame(0, DB::table('submission_activities')->count());
        }
    }

    public function test_inactive_admin_legal_and_requester_cannot_create(): void
    {
        foreach (['admin', 'legal', 'requester'] as $role) {
            $user = User::factory()->{$role}()->inactive()->create();
            $this->actingAs($user);

            try {
                app(CreateSubmission::class)($this->validInput());
                $this->fail("Inactive {$role} should not be able to create a submission.");
            } catch (AuthorizationException) {
                // fall through to the write-nothing assertions below
            }

            $this->assertSame(0, DB::table('submissions')->count());
            $this->assertSame(0, DB::table('submission_activities')->count());
        }
    }

    public function test_authorization_occurs_before_validation(): void
    {
        // A role that can never create a submission must hit authorization
        // first: even invalid input must produce AuthorizationException, not
        // ValidationException, proving validation never runs for the denied.
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer);

        try {
            app(CreateSubmission::class)(['title' => '', 'campus_id' => null, 'partner_name' => '', 'purpose' => '']);
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            // fall through to the write-nothing assertions below
        } catch (ValidationException $e) {
            $this->fail('Validation ran before authorization: '.json_encode($e->errors()));
        }

        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('submission_activities')->count());
    }

    public function test_activity_failure_rolls_back_submission_creation(): void
    {
        $user = User::factory()->requester()->create();
        $this->actingAs($user);

        // A specific exception type keeps PHPUnit's fail() (AssertionFailedError)
        // outside the catch, so an unthrown exception still fails the test.
        $mock = $this->mock(RecordSubmissionActivity::class);
        $mock->shouldReceive('__invoke')
            ->once()
            ->andThrow(new \UnexpectedValueException('Recorder failure'));

        try {
            app(CreateSubmission::class)($this->validInput());
            $this->fail('Expected recorder exception was not thrown.');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame('Recorder failure', $e->getMessage());
        }

        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('submission_activities')->count());
    }

    public function test_action_has_exactly_one_array_input_parameter_and_no_user_parameter(): void
    {
        $reflection = new \ReflectionMethod(CreateSubmission::class, '__invoke');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->getType() instanceof \ReflectionNamedType);
        $this->assertSame('array', $parameters[0]->getType()->getName());
    }
}
