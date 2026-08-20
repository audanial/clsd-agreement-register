<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Partner;
use App\Models\Scopes\HidePendingFromNonLegalScope;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_see_pending_agreements(): void
    {
        $pending = Agreement::factory()->pending()->create();
        $signed1 = Agreement::factory()->signed()->create();
        $signed2 = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $visible = Agreement::pluck('id');

        $this->assertCount(2, $visible);
        $this->assertFalse($visible->contains($pending->id));
        $this->assertTrue($visible->contains($signed1->id));
        $this->assertTrue($visible->contains($signed2->id));
    }

    public function test_legal_can_see_pending_agreements(): void
    {
        $pending = Agreement::factory()->pending()->create();
        $signed1 = Agreement::factory()->signed()->create();
        $signed2 = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->legal()->create());

        $this->assertCount(3, Agreement::all());
        $this->assertTrue(Agreement::pluck('id')->contains($pending->id));
    }

    public function test_admin_can_see_pending_agreements(): void
    {
        $pending = Agreement::factory()->pending()->create();
        Agreement::factory()->signed()->create();
        Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->assertCount(3, Agreement::all());
        $this->assertTrue(Agreement::pluck('id')->contains($pending->id));
    }

    public function test_viewer_gets_model_not_found_for_a_pending_agreement_by_id(): void
    {
        $pending = Agreement::factory()->pending()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->expectException(ModelNotFoundException::class);

        Agreement::findOrFail($pending->id);
    }

    public function test_viewer_cannot_reach_pending_agreements_through_a_relation(): void
    {
        $campus = Campus::firstOrCreate(
            ['code' => 'TBD'],
            ['name' => 'Not Assigned', 'is_institute' => false, 'sort_order' => 999, 'is_active' => true]
        );
        $partner = Partner::factory()->create();

        $pending = Agreement::factory()->pending()->create([
            'campus_id' => $campus->id,
            'partner_id' => $partner->id,
        ]);
        $signed = Agreement::factory()->signed()->create([
            'campus_id' => $campus->id,
            'partner_id' => $partner->id,
        ]);

        $this->actingAs(User::factory()->viewer()->create());

        $this->assertFalse($campus->agreements->pluck('id')->contains($pending->id));
        $this->assertTrue($campus->agreements->pluck('id')->contains($signed->id));

        $this->assertFalse($partner->agreements->pluck('id')->contains($pending->id));
        $this->assertTrue($partner->agreements->pluck('id')->contains($signed->id));
    }

    public function test_pending_rows_are_excluded_from_relation_counts_for_a_viewer(): void
    {
        Agreement::factory()->pending()->create();
        Agreement::factory()->signed()->create();
        Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $campus = Campus::withCount('agreements')->first();

        $this->assertNotNull($campus);
        $this->assertSame(2, $campus->agreements_count);
    }

    public function test_unauthenticated_context_is_not_scoped(): void
    {
        Agreement::factory()->pending()->create();
        Agreement::factory()->signed()->create();
        Agreement::factory()->signed()->create();

        $this->assertCount(3, Agreement::all());
    }

    public function test_without_global_scope_reveals_pending_rows(): void
    {
        $pending = Agreement::factory()->pending()->create();
        Agreement::factory()->signed()->create();
        Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $all = Agreement::withoutGlobalScope(HidePendingFromNonLegalScope::class)->pluck('id');

        $this->assertCount(3, $all);
        $this->assertTrue($all->contains($pending->id));
    }
}
