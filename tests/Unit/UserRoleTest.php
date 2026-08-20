<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_helpers_reflect_the_role_column(): void
    {
        $admin = User::factory()->admin()->make();
        $legal = User::factory()->legal()->make();
        $viewer = User::factory()->viewer()->make();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isLegal());
        $this->assertFalse($admin->isViewer());

        $this->assertTrue($legal->isLegal());
        $this->assertFalse($legal->isAdmin());
        $this->assertFalse($legal->isViewer());

        $this->assertTrue($viewer->isViewer());
        $this->assertFalse($viewer->isAdmin());
        $this->assertFalse($viewer->isLegal());
    }

    public function test_can_see_pending_is_true_for_admin_and_legal_and_false_for_viewer(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->canSeePending());
        $this->assertTrue(User::factory()->legal()->make()->canSeePending());
        $this->assertFalse(User::factory()->viewer()->make()->canSeePending());
    }

    public function test_role_is_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Role Test',
            'email' => 'role-test@unikl.edu.my',
            'password' => 'password',
            'role' => 'legal',
            'is_active' => true,
        ]);

        $this->assertSame('legal', $user->role);
    }

    public function test_is_active_is_mass_assignable_and_cast_to_boolean(): void
    {
        $user = User::create([
            'name' => 'Active Test',
            'email' => 'active-test@unikl.edu.my',
            'password' => 'password',
            'role' => 'viewer',
            'is_active' => false,
        ]);

        $this->assertFalse($user->is_active);
        $this->assertIsBool($user->is_active);
    }

    public function test_active_scope_excludes_inactive_users(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $activeIds = User::active()->pluck('id');

        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($inactive->id));
    }
}
