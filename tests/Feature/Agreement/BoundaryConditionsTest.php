<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryConditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->legal()->create());
    }

    /**
     * Characterisation test for DEF-008: exactly 90 days is currently treated
     * as stale (not just 91+), due to elapsed real time between record
     * creation and the check. See docs/qa/defect-log.md DEF-008.
     */
    public function test_has_stale_project_status_is_true_past_the_boundary(): void
    {
        $eightyNineDays = Agreement::factory()->create([
            'project_status_updated_at' => now()->subDays(89),
        ]);

        $ninetyDays = Agreement::factory()->create([
            'project_status_updated_at' => now()->subDays(90),
        ]);

        $ninetyOneDays = Agreement::factory()->create([
            'project_status_updated_at' => now()->subDays(91),
        ]);

        $this->assertFalse($eightyNineDays->hasStaleProjectStatus());
        $this->assertTrue($ninetyDays->hasStaleProjectStatus());
        $this->assertTrue($ninetyOneDays->hasStaleProjectStatus());
    }
}