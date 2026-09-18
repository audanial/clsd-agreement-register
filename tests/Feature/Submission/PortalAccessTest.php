<?php

namespace Tests\Feature\Submission;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HTTP route-level authorization for the Legal Submission Portal.
 *
 * Layering: `auth` middleware redirects guests to /login; `role:` middleware
 * returns 403 for every role outside the route's allow-list; the
 * SubmissionPolicy (tested separately, and through the pages below) handles
 * record-level access such as the cross-requester 404.
 */
class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->submission = Submission::factory()->create();
    }

    private function portalRoutes(): array
    {
        return [
            'index' => '/submissions',
            'create' => '/submissions/create',
            'show' => '/submissions/'.$this->submission->id,
        ];
    }

    public function test_guests_are_redirected_to_login_for_every_portal_route(): void
    {
        foreach ($this->portalRoutes() as $route) {
            $this->get($route)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_viewer_receives_403_for_every_portal_route(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        foreach ($this->portalRoutes() as $name => $route) {
            $this->get($route)->assertForbidden("Route {$name} must be forbidden for viewers.");
        }
    }

    public function test_legal_and_admin_can_access_index_and_show_but_not_create(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get('/submissions')->assertOk();
            $this->get('/submissions/'.$this->submission->id)->assertOk();
            $this->get('/submissions/create')->assertForbidden("Route create must be forbidden for {$role}.");
        }
    }

    public function test_requesting_staff_can_access_index_create_and_their_own_detail(): void
    {
        $owner = User::factory()->requester()->create();
        $ownSubmission = Submission::factory()->create(['created_by' => $owner->id]);

        $this->actingAs(User::query()->findOrFail($owner->id));

        $this->get('/submissions')->assertOk();
        $this->get('/submissions/create')->assertOk();
        $this->get('/submissions/'.$ownSubmission->id)->assertOk();
    }

    public function test_cross_requester_direct_access_returns_404_and_exposes_nothing(): void
    {
        $other = User::factory()->requester()->create();

        $this->actingAs(User::query()->findOrFail($other->id));

        $this->get('/submissions/'.$this->submission->id)
            ->assertNotFound()
            ->assertDontSee($this->submission->title)
            ->assertDontSee($this->submission->purpose)
            ->assertDontSee($this->submission->partner_name);
    }

    public function test_inactive_admin_and_legal_are_denied_the_index(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->inactive()->create());

            $this->get('/submissions')->assertForbidden();
        }
    }

    public function test_inactive_admin_and_legal_are_denied_the_show_route(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->inactive()->create());

            $this->get('/submissions/'.$this->submission->id)->assertForbidden();
        }
    }

    public function test_inactive_requesting_staff_is_denied_every_applicable_portal_page(): void
    {
        $inactive = User::factory()->requester()->inactive()->create();
        $ownSubmission = Submission::factory()->create(['created_by' => $inactive->id]);

        $this->actingAs(User::query()->findOrFail($inactive->id));

        $this->get('/submissions')->assertForbidden();
        $this->get('/submissions/create')->assertForbidden();
        // Even the requester's own submission is denied while inactive.
        $this->get('/submissions/'.$ownSubmission->id)->assertForbidden();
    }

    public function test_no_update_or_delete_routes_exist(): void
    {
        $registeredNames = collect(Route::getRoutes())->pluck('name')->all();

        $this->assertNotContains('submissions.update', $registeredNames);
        $this->assertNotContains('submissions.delete', $registeredNames);

        $this->actingAs(User::factory()->legal()->create());

        // No HTTP verb other than GET may touch a submission's portal routes.
        $this->put('/submissions/'.$this->submission->id)->assertMethodNotAllowed();
        $this->delete('/submissions/'.$this->submission->id)->assertMethodNotAllowed();
        $this->post('/submissions')->assertMethodNotAllowed();
    }
}
