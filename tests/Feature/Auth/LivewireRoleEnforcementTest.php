<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire re-runs only a fixed allow-list of middleware on /livewire/update
 * requests, and our `role:` middleware is not on it by default. A route guarded
 * only by middleware therefore protects the initial page load and nothing more.
 *
 * These tests call components directly via Livewire::test(), which bypasses route
 * middleware entirely — the same path a crafted update request would take. They are
 * the proof that authorization lives in the component, not just in the route.
 *
 * They assert the SIDE EFFECT rather than a status code: Livewire's test harness
 * renders an abort() into a response instead of rethrowing it
 * (SupportTesting\RequestBroker keeps HttpException in the handled list), so
 * "did the write happen?" is both the meaningful question and the reliable one.
 * Each negative test is paired with a positive control, so a guard that blocked
 * everybody — including admins — would still be caught.
 */
class LivewireRoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_create_a_user_through_the_livewire_component(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('user-manager')
            ->set('name', 'Should Not Exist')
            ->set('email', 'should-not-exist@unikl.edu.my')
            ->set('role', 'admin')
            ->set('password', 'password123')
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'should-not-exist@unikl.edu.my']);
    }

    public function test_a_requester_cannot_create_a_user_through_the_livewire_component(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        Livewire::test('user-manager')
            ->set('name', 'Nope')
            ->set('email', 'nope@unikl.edu.my')
            ->set('role', 'admin')
            ->set('password', 'password123')
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'nope@unikl.edu.my']);
    }

    public function test_a_viewer_cannot_create_a_user_through_the_livewire_component(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        Livewire::test('user-manager')
            ->set('name', 'Also Nope')
            ->set('email', 'also-nope@unikl.edu.my')
            ->set('role', 'legal')
            ->set('password', 'password123')
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'also-nope@unikl.edu.my']);
    }

    /**
     * Positive control for the three tests above: the guard must block non-admins
     * without blocking admins.
     */
    public function test_an_admin_can_still_create_a_user_through_the_livewire_component(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')
            ->set('name', 'Legitimately Created')
            ->set('email', 'legit@unikl.edu.my')
            ->set('role', 'legal')
            ->set('password', 'password123')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'legit@unikl.edu.my', 'role' => 'legal']);
    }

    public function test_non_admin_cannot_toggle_a_user_through_the_livewire_component(): void
    {
        $target = User::factory()->viewer()->active()->create();

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('user-manager')->call('toggle', $target->id);

        $this->assertTrue(
            $target->fresh()->is_active,
            'A non-admin was able to deactivate another user through the component.'
        );
    }

    public function test_a_requester_cannot_toggle_a_user_through_the_livewire_component(): void
    {
        $target = User::factory()->viewer()->active()->create();

        $this->actingAs(User::factory()->requester()->create());

        Livewire::test('user-manager')->call('toggle', $target->id);

        $this->assertTrue(
            $target->fresh()->is_active,
            'A requester was able to deactivate another user through the component.'
        );
    }

    /**
     * Positive control for the two toggle tests above.
     */
    public function test_an_admin_can_still_toggle_a_user_through_the_livewire_component(): void
    {
        $target = User::factory()->viewer()->active()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')->call('toggle', $target->id);

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_ensure_user_has_role_is_registered_as_livewire_persistent_middleware(): void
    {
        $this->assertContains(
            EnsureUserHasRole::class,
            Livewire::getPersistentMiddleware(),
            'EnsureUserHasRole must be persistent, or role checks are skipped on Livewire update requests.'
        );
    }
}
