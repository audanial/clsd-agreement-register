<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_gets_403_on_the_create_route(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.create'))->assertStatus(403);
    }

    public function test_viewer_gets_403_on_the_edit_route(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.edit', $agreement))->assertStatus(403);
    }

    public function test_legal_can_reach_create_and_edit(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.create'))->assertOk();
        $this->get(route('agreements.edit', $agreement))->assertOk();
    }

    public function test_admin_can_reach_create_and_edit(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('agreements.create'))->assertOk();
        $this->get(route('agreements.edit', $agreement))->assertOk();
    }

    public function test_guests_are_redirected_from_every_agreement_route(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->get(route('agreements.index'))->assertRedirect('/login');
        $this->get(route('agreements.create'))->assertRedirect('/login');
        $this->get(route('agreements.show', $agreement))->assertRedirect('/login');
        $this->get(route('agreements.edit', $agreement))->assertRedirect('/login');
    }
}
