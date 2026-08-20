<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_changes_the_password_and_the_user_can_login_with_it(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'reset@unikl.edu.my',
        ]);
        $oldToken = $user->getRememberToken();

        $this->artisan('user:password', [
            'email' => 'reset@unikl.edu.my',
        ])
            ->expectsQuestion('New password', 'new-password')
            ->expectsQuestion('Confirm new password', 'new-password')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotSame($oldToken, $user->getRememberToken());

        $response = $this->post('/login', [
            'email' => 'reset@unikl.edu.my',
            'password' => 'new-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:password', [
            'email' => 'missing@unikl.edu.my',
        ])->assertFailed();
    }

    public function test_old_password_no_longer_works_after_a_reset(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'reset-old@unikl.edu.my',
            'password' => 'old-password',
        ]);

        $this->artisan('user:password', [
            'email' => 'reset-old@unikl.edu.my',
        ])
            ->expectsQuestion('New password', 'new-password')
            ->expectsQuestion('Confirm new password', 'new-password')
            ->assertSuccessful();

        $response = $this->post('/login', [
            'email' => 'reset-old@unikl.edu.my',
            'password' => 'old-password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }
}
