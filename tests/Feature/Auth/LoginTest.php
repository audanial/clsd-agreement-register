<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_an_invalid_password(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@unikl.edu.my',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_login_error_does_not_reveal_whether_the_email_exists(): void
    {
        $response = $this->post('/login', [
            'email' => 'missing@unikl.edu.my',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@unikl.edu.my',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@unikl.edu.my',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_session_is_regenerated_on_login(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        $session = $this->app->make('session.store');
        $oldId = $session->getId();

        $response = $this->post('/login', [
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, $session->getId());
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@unikl.edu.my',
            'password' => 'password',
        ]);

        foreach (range(1, 5) as $i) {
            $response = $this->post('/login', [
                'email' => 'admin@unikl.edu.my',
                'password' => 'wrong-password',
            ]);

            if ($i < 5) {
                $response->assertSessionHasErrors(['email']);
            }
        }

        $this->post('/login', [
            'email' => 'admin@unikl.edu.my',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
