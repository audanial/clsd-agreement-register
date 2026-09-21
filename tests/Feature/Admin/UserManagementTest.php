<?php

namespace Tests\Feature\Admin;

use App\Actions\DeactivateUser;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        ])->assertSessionHasErrors(['email' => EnsureUserIsActive::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_deactivation_rotates_the_remember_token_and_purges_only_that_users_database_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->viewer()->create();
        $other = User::factory()->viewer()->create();
        $oldToken = $target->getRememberToken();

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            $this->sessionRow('target-session', $target->id),
            $this->sessionRow('other-session', $other->id),
        ]);

        $this->actingAs($admin);
        Livewire::test('user-manager')->call('toggle', $target->id);

        $this->assertFalse($target->fresh()->is_active);
        $this->assertNotSame($oldToken, $target->fresh()->getRememberToken());
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }

    public function test_deactivation_uses_the_configured_session_table(): void
    {
        Schema::create('alternate_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
        });

        $target = User::factory()->viewer()->create();
        $other = User::factory()->viewer()->create();
        DB::table('alternate_sessions')->insert([
            ['id' => 'target-session', 'user_id' => $target->id],
            ['id' => 'other-session', 'user_id' => $other->id],
        ]);
        config(['session.driver' => 'database', 'session.table' => 'alternate_sessions']);

        app(DeactivateUser::class)($target);

        $this->assertDatabaseMissing('alternate_sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('alternate_sessions', ['id' => 'other-session']);
    }

    public function test_reactivation_does_not_rotate_the_token_or_purge_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->inactive()->create();
        $token = $target->getRememberToken();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert($this->sessionRow('reactivating-session', $target->id));

        $this->actingAs($admin);
        Livewire::test('user-manager')->call('toggle', $target->id);

        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame($token, $target->fresh()->getRememberToken());
        $this->assertDatabaseHas('sessions', ['id' => 'reactivating-session']);

        $this->post('/logout');
        $this->post('/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($target);
    }

    private function sessionRow(string $id, int $userId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => time(),
        ];
    }
}
