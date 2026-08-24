<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_can_view_a_pending_agreement(): void
    {
        $agreement = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($agreement->title);
    }

    public function test_viewer_gets_404_for_a_pending_agreement(): void
    {
        $agreement = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))->assertStatus(404);
    }

    public function test_viewer_can_view_a_signed_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee($agreement->title);
    }

    public function test_indefinite_expiry_renders_as_indefinite_not_blank(): void
    {
        $agreement = Agreement::factory()->signed()->create(['expiry_date' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Indefinite')
            ->assertDontSee('01 Jan 1970');
    }

    public function test_activity_feed_renders_newest_first(): void
    {
        $agreement = Agreement::factory()->signed()->create();
        $agreement->activities()->create([
            'user_id' => null,
            'type' => 'comment',
            'description' => 'First comment',
            'created_at' => now()->subHour(),
        ]);
        $agreement->activities()->create([
            'user_id' => null,
            'type' => 'comment',
            'description' => 'Second comment',
            'created_at' => now(),
        ]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->assertSeeInOrder(['Second comment', 'First comment']);
    }

    public function test_stale_badge_appears_on_detail_for_a_stale_agreement(): void
    {
        $agreement = Agreement::factory()->signed()->staleProjectStatus()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Stale');
    }

    public function test_never_updated_project_status_renders_as_stale(): void
    {
        $agreement = Agreement::factory()->signed()->create(['project_status_updated_at' => null]);

        $this->actingAs(User::factory()->legal()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertSee('Stale');
    }

    public function test_viewer_does_not_see_status_change_controls(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('agreements.show', $agreement))
            ->assertOk()
            ->assertDontSee('Change status');
    }
}
