<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementCastsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->legal()->create());
    }

    public function test_all_date_columns_cast_to_carbon(): void
    {
        $date = today();
        $agreement = Agreement::factory()->create([
            'agreement_date' => $date,
            'effective_date' => $date,
            'expiry_date' => $date,
            'received_from_po_at' => $date,
            'board_approved_at' => $date,
            'signed_by_unikl_at' => $date,
            'sent_to_partner_at' => $date,
            'signed_date' => $date,
        ]);

        $this->assertInstanceOf(Carbon::class, $agreement->agreement_date);
        $this->assertInstanceOf(Carbon::class, $agreement->effective_date);
        $this->assertInstanceOf(Carbon::class, $agreement->expiry_date);
        $this->assertInstanceOf(Carbon::class, $agreement->received_from_po_at);
        $this->assertInstanceOf(Carbon::class, $agreement->board_approved_at);
        $this->assertInstanceOf(Carbon::class, $agreement->signed_by_unikl_at);
        $this->assertInstanceOf(Carbon::class, $agreement->sent_to_partner_at);
        $this->assertInstanceOf(Carbon::class, $agreement->signed_date);
    }

    public function test_project_status_updated_at_and_archived_at_cast_to_datetime(): void
    {
        $now = now();
        $agreement = Agreement::factory()->archived()->create([
            'project_status_updated_at' => $now,
        ]);

        $this->assertInstanceOf(Carbon::class, $agreement->project_status_updated_at);
        $this->assertInstanceOf(Carbon::class, $agreement->archived_at);
    }

    public function test_null_expiry_date_stays_null_and_is_indefinite(): void
    {
        $agreement = Agreement::factory()->create(['expiry_date' => null]);

        $this->assertNull($agreement->expiry_date);
        $this->assertTrue($agreement->isIndefinite());
        $this->assertFalse($agreement->isExpired());
    }

    public function test_year_is_derived_from_agreement_date_and_is_null_when_the_date_is_null(): void
    {
        $agreement = Agreement::factory()->create(['agreement_date' => '2024-06-15']);

        $this->assertSame(2024, $agreement->year);

        $agreement->agreement_date = null;
        $this->assertNull($agreement->year);
    }

    public function test_has_stale_project_status_is_true_past_the_threshold_and_when_never_set(): void
    {
        $fresh = Agreement::factory()->create([
            'project_status_updated_at' => now()->subDays(30),
        ]);
        $stale = Agreement::factory()->staleProjectStatus()->create();
        $never = Agreement::factory()->create(['project_status_updated_at' => null]);

        $this->assertFalse($fresh->hasStaleProjectStatus());
        $this->assertTrue($stale->hasStaleProjectStatus());
        $this->assertTrue($never->hasStaleProjectStatus());
    }

    public function test_archived_at_is_not_mass_assignable(): void
    {
        $agreement = Agreement::factory()->create();

        $agreement->update(['archived_at' => now()]);

        $this->assertNull($agreement->fresh()->archived_at);
    }
}
