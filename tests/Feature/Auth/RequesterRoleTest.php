<?php

namespace Tests\Feature\Auth;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequesterRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_role_persists_and_helpers_report_correctly(): void
    {
        $requester = User::factory()->requester()->create();

        $this->assertSame('requester', $requester->fresh()->role);

        $this->assertTrue($requester->isRequester());
        $this->assertTrue($requester->canAccessPortal());

        $this->assertFalse($requester->isAdmin());
        $this->assertFalse($requester->isLegal());
        $this->assertFalse($requester->isViewer());
        $this->assertFalse($requester->isLegalStaff());
        $this->assertFalse($requester->canWrite());
        $this->assertFalse($requester->canSeePending());
        $this->assertFalse($requester->canManageUsers());
        $this->assertFalse($requester->canAccessRegister());
    }

    public function test_requester_is_forbidden_from_the_agreements_index(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.index'))->assertStatus(403);
    }

    public function test_requester_is_forbidden_from_an_agreement_show_page(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.show', $agreement))->assertStatus(403);
    }

    public function test_requester_is_forbidden_from_agreement_create_and_edit(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('agreements.create'))->assertStatus(403);
        $this->get(route('agreements.edit', $agreement))->assertStatus(403);
    }

    public function test_requester_is_forbidden_from_user_management(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('users.index'))->assertStatus(403);
    }

    public function test_requester_can_reach_the_dashboard(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    /**
     * The no-regression assertion: gating the register must not change anything
     * for the three roles that already had access to it.
     */
    public function test_admin_legal_and_viewer_still_reach_the_register(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        foreach (['admin', 'legal', 'viewer'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('agreements.index'))->assertOk();
            $this->get(route('agreements.show', $agreement))->assertOk();
        }
    }

    public function test_admin_can_create_a_requester_through_the_user_manager(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')
            ->set('name', 'Nurul Huda')
            ->set('email', 'nurul.huda@unikl.edu.my')
            ->set('role', 'requester')
            ->set('password', 'password123')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'nurul.huda@unikl.edu.my',
            'role' => 'requester',
            'is_active' => true,
        ]);
    }

    public function test_the_register_nav_link_is_hidden_from_a_requester(): void
    {
        $this->actingAs(User::factory()->requester()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('agreements.index'));
    }

    public function test_the_register_nav_link_is_still_shown_to_a_viewer(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('agreements.index'));
    }
}
