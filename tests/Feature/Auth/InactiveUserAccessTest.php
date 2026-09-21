<?php

namespace Tests\Feature\Auth;

use App\Actions\DeactivateUser;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * These tests use real HTTP routes. Livewire::test() bypasses route middleware,
 * so it cannot prove the active-user check runs on /livewire/update.
 */
class InactiveUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_session_is_rejected_after_out_of_band_deactivation(): void
    {
        $user = $this->loginViewer();

        $this->get('/agreements')->assertOk();

        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->get('/agreements')
            ->assertRedirect('/login')
            ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

        $this->assertGuest();
    }

    public function test_a_real_livewire_update_is_rejected_after_out_of_band_deactivation(): void
    {
        $user = $this->loginViewer();
        $html = $this->get('/agreements')->assertOk()->getContent();
        $snapshot = $this->snapshotFrom($html);

        // A valid active-user update proves the request and snapshot are usable.
        $this->postUpdate($snapshot)->assertOk();

        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->postUpdate($snapshot)
            ->assertRedirect('/login?deactivated=1');

        $this->assertGuest();
        $this->get('/login?deactivated=1')
            ->assertOk()
            ->assertSee(EnsureUserIsActive::DEACTIVATED_MESSAGE);
    }

    public function test_a_failed_login_from_the_deactivated_marker_returns_to_the_clean_login_url(): void
    {
        User::factory()->viewer()->create([
            'email' => 'viewer@unikl.edu.my',
            'password' => 'password123',
        ]);

        $this->from('/login?deactivated=1')
            ->post('/login', [
                'email' => 'viewer@unikl.edu.my',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->get('/login')
            ->assertOk()
            ->assertDontSee(EnsureUserIsActive::DEACTIVATED_MESSAGE);
    }

    public function test_a_rotated_remember_token_cannot_restore_a_deactivated_user(): void
    {
        $user = $this->loginViewer(remember: true);
        $name = Auth::guard('web')->getRecallerName();
        $recaller = $this->recallerFor($user->fresh());

        app(DeactivateUser::class)($user);
        $this->freshBrowser();

        $this->withCookie($name, $recaller)
            ->get('/agreements')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_an_unrotated_remember_token_is_rejected_by_the_middleware(): void
    {
        $user = $this->loginViewer(remember: true);
        $name = Auth::guard('web')->getRecallerName();
        $recaller = $this->recallerFor($user->fresh());

        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->freshBrowser();

        $this->withCookie($name, $recaller)
            ->get('/agreements')
            ->assertRedirect('/login')
            ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

        $this->assertGuest();
    }

    public function test_a_fresh_browser_without_a_recaller_remains_a_guest(): void
    {
        $this->loginViewer();
        $this->freshBrowser();

        $this->get('/agreements')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_valid_recaller_restores_an_active_user_in_a_fresh_browser(): void
    {
        $user = $this->loginViewer(remember: true);
        $name = Auth::guard('web')->getRecallerName();
        $recaller = $this->recallerFor($user->fresh());
        $this->freshBrowser();

        $this->withCookie($name, $recaller)
            ->get('/agreements')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_can_open_login_without_a_redirect_loop(): void
    {
        $this->get('/login')->assertOk();
    }

    private function loginViewer(bool $remember = false): User
    {
        $user = User::factory()->viewer()->create([
            'email' => 'viewer@unikl.edu.my',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => $remember,
        ])->assertRedirect('/dashboard');

        return $user;
    }

    private function freshBrowser(): void
    {
        // The array session driver and guard persist across test requests.
        // Clear both so only an explicitly supplied recaller can log in.
        $this->flushSession();
        Auth::forgetGuards();
    }

    private function recallerFor(User $user): string
    {
        return $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$user->getAuthPassword();
    }

    private function snapshotFrom(string $html): string
    {
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function postUpdate(string $snapshot)
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['search' => 'verification'],
                'calls' => [],
            ]],
        ]);
    }
}
