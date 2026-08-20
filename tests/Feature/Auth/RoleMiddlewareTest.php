<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', 'role:admin'])->get('/admin-only', function () {
            return 'admin';
        });
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin-only');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_an_admin_only_route(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin-only');

        $response->assertOk();
        $response->assertSee('admin');
    }

    public function test_legal_is_forbidden_from_an_admin_only_route(): void
    {
        $user = User::factory()->legal()->create();

        $response = $this->actingAs($user)->get('/admin-only');

        $response->assertStatus(403);
    }

    public function test_viewer_is_forbidden_from_an_admin_only_route(): void
    {
        $user = User::factory()->viewer()->create();

        $response = $this->actingAs($user)->get('/admin-only');

        $response->assertStatus(403);
    }

    public function test_legal_and_viewer_can_both_reach_the_dashboard(): void
    {
        $legal = User::factory()->legal()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($legal)->get('/dashboard')->assertOk();
        $this->actingAs($viewer)->get('/dashboard')->assertOk();
    }
}
