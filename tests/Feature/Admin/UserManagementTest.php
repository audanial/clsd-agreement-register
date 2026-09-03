<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reach_user_management_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('users.index'))->assertOk();
    }

    public function test_legal_user_gets_403_on_user_management_page(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('users.index'))->assertStatus(403);
    }

    public function test_viewer_gets_403_on_user_management_page(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('users.index'))->assertStatus(403);
    }

    public function test_creating_a_user_persists_the_correct_role_and_active_state(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')
            ->set('name', 'Ahmad Bin Ali')
            ->set('email', 'ahmad@unikl.edu.my')
            ->set('role', 'legal')
            ->set('password', 'password123')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Ahmad Bin Ali',
            'email' => 'ahmad@unikl.edu.my',
            'role' => 'legal',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'ahmad@unikl.edu.my']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')
            ->set('name', 'Ahmad Bin Ali')
            ->set('email', 'ahmad@unikl.edu.my')
            ->set('role', 'viewer')
            ->set('password', 'password123')
            ->call('create')
            ->assertHasErrors(['email']);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test('user-manager')
            ->call('toggle', $admin->id);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->active()->create([
            'email' => 'pic@unikl.edu.my',
            'password' => 'password123',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('user-manager')
            ->call('toggle', $user->id);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'pic@unikl.edu.my',
            'password' => 'password123',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }
}
