<?php

namespace Tests\Feature\Submission;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_requester_can_view_any_create_and_view_own_submission(): void
    {
        $user = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $user->id]);

        $user = User::query()->findOrFail($user->id);
        $submission = Submission::query()->findOrFail($submission->id);

        $this->assertTrue($user->can('viewAny', Submission::class));
        $this->assertTrue($user->can('create', Submission::class));
        $this->assertTrue($user->can('view', $submission));
    }

    public function test_active_requester_viewing_another_submission_is_denied_as_not_found(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $owner->id]);

        $other = User::query()->findOrFail($other->id);
        $submission = Submission::query()->findOrFail($submission->id);

        $response = Gate::forUser($other)->inspect('view', $submission);

        $this->assertFalse($response->allowed());
        $this->assertSame(404, $response->status());
    }

    public function test_active_legal_and_admin_can_view_any_and_view_but_not_create(): void
    {
        $submission = Submission::factory()->create();
        $submission = Submission::query()->findOrFail($submission->id);

        foreach (['admin', 'legal'] as $role) {
            $user = User::factory()->{$role}()->create();
            $user = User::query()->findOrFail($user->id);

            $this->assertTrue($user->can('viewAny', Submission::class));
            $this->assertTrue($user->can('view', $submission));
            $this->assertFalse($user->can('create', Submission::class));
        }
    }

    public function test_active_viewer_is_denied_all_abilities(): void
    {
        $user = User::factory()->viewer()->create();
        $submission = Submission::factory()->create();

        $user = User::query()->findOrFail($user->id);
        $submission = Submission::query()->findOrFail($submission->id);

        $this->assertFalse($user->can('viewAny', Submission::class));
        $this->assertFalse($user->can('view', $submission));
        $this->assertFalse($user->can('create', Submission::class));
    }

    public function test_inactive_admin_is_denied_all_abilities(): void
    {
        $this->assertInactiveUserDeniedAll(User::factory()->admin()->inactive()->create());
    }

    public function test_inactive_legal_is_denied_all_abilities(): void
    {
        $this->assertInactiveUserDeniedAll(User::factory()->legal()->inactive()->create());
    }

    public function test_inactive_requester_is_denied_all_abilities_including_own_submission(): void
    {
        $user = User::factory()->requester()->inactive()->create();
        $submission = Submission::factory()->create(['created_by' => $user->id]);

        $user = User::query()->findOrFail($user->id);
        $submission = Submission::query()->findOrFail($submission->id);

        $this->assertFalse($user->can('viewAny', Submission::class));
        $this->assertFalse($user->can('create', Submission::class));
        $this->assertFalse($user->can('view', $submission));
    }

    public function test_active_requester_can_view_own_submission_hydrated_from_string_created_by(): void
    {
        $owner = User::factory()->requester()->create();
        Submission::factory()->create(['created_by' => $owner->id]);

        $owner = User::query()->findOrFail($owner->id);

        // Route-model binding in LP1-B will pass a database-hydrated model;
        // hydrate one with created_by forced through as a string so the
        // integer cast, not the database driver, produces the comparison.
        $raw = (array) DB::table('submissions')->firstOrFail();
        $raw['created_by'] = (string) $owner->id;

        $hydrated = (new Submission)->newFromBuilder($raw);
        $this->assertIsInt($hydrated->created_by);
        $this->assertTrue($owner->can('view', $hydrated));
    }

    private function assertInactiveUserDeniedAll(User $user): void
    {
        $submission = Submission::factory()->create();
        $user = User::query()->findOrFail($user->id);
        $submission = Submission::query()->findOrFail($submission->id);

        $this->assertFalse($user->can('viewAny', Submission::class));
        $this->assertFalse($user->can('view', $submission));
        $this->assertFalse($user->can('create', Submission::class));
    }

    public function test_update_and_delete_are_denied_for_every_role_active_and_inactive(): void
    {
        $roles = ['admin', 'legal', 'viewer', 'requester'];
        $states = [true, false];

        foreach ($roles as $role) {
            foreach ($states as $active) {
                $user = User::factory()->{$role}()->create(['is_active' => $active]);
                $submission = Submission::factory()->create(['created_by' => $user->id]);

                $user = User::query()->findOrFail($user->id);
                $submission = Submission::query()->findOrFail($submission->id);

                $this->assertFalse($user->can('update', $submission));
                $this->assertFalse($user->can('delete', $submission));
            }
        }
    }
}
