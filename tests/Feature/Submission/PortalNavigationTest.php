<?php

namespace Tests\Feature\Submission;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role-aware navigation and dashboard entry points for the Legal Submission
 * Portal. Since LP1 Amendment 2 (17 Sep 2026) requesters also see the Register
 * link — they use it as a read-only reference — but still never see the queue.
 */
class PortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_navigation_says_my_submissions_and_hides_the_queue(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('My Submissions')
            ->assertSee('Register')
            ->assertDontSee('Submission Queue');
    }

    public function test_admin_and_legal_navigation_says_submission_queue_and_keeps_register(): void
    {
        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get('/dashboard')
                ->assertOk()
                ->assertSee('Submission Queue')
                ->assertSee('Register')
                ->assertDontSee('My Submissions');
        }
    }

    public function test_viewer_navigation_shows_no_portal_link_but_keeps_register(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Register')
            ->assertDontSee('My Submissions')
            ->assertDontSee('Submission Queue');
    }

    public function test_requester_dashboard_links_to_create_submission_my_submissions_and_the_register(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Create Submission')
            ->assertSee(route('submissions.create'))
            ->assertSee('My Submissions')
            ->assertSee(route('submissions.index'))
            ->assertSee('Open register')
            ->assertSee(route('agreements.index'));
    }

    public function test_non_requester_dashboards_do_not_show_the_portal_entry_points(): void
    {
        foreach (['admin', 'legal', 'viewer'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $html = $this->get('/dashboard')->getContent();

            $this->assertStringNotContainsString('Create Submission', $html, "{$role} saw the portal entry point.");
        }
    }

    public function test_portal_index_heading_matches_the_role(): void
    {
        $this->actingAs(User::factory()->requester()->create());
        $this->get('/submissions')->assertOk()->assertSee('My Submissions');

        foreach (['admin', 'legal'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            $this->get('/submissions')->assertOk()->assertSee('Submission Queue');
        }
    }
}
