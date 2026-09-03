<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
     * Decision M5-8: a boundary date is crossed at the start of that day.
     * Therefore exactly STALE_AFTER_DAYS days without an update is already
     * stale. The comparison is day-based (M5-10) so this is deterministic.
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

    /**
     * Decision M5-8: an agreement expires at the START of its expiry_date.
     * On the expiry_date itself the agreement is already expired, so all
     * three code paths must agree: isExpired true, expired() scope true,
     * expiringSoon() scope false. The day-after boundary checks the far end
     * of the expiringSoon window.
     */
    public function test_expiry_today_behaviour_is_consistently_recorded(): void
    {
        $expiringToday = Agreement::factory()->signed()->create([
            'expiry_date' => today(),
        ]);

        $expiringTomorrow = Agreement::factory()->signed()->create([
            'expiry_date' => today()->addDay(),
        ]);

        $this->assertTrue($expiringToday->isExpired());
        $this->assertTrue(Agreement::expired()->whereKey($expiringToday->id)->exists());
        $this->assertFalse(Agreement::expiringSoon()->whereKey($expiringToday->id)->exists());

        $this->assertFalse($expiringTomorrow->isExpired());
        $this->assertFalse(Agreement::expired()->whereKey($expiringTomorrow->id)->exists());
        $this->assertTrue(Agreement::expiringSoon()->whereKey($expiringTomorrow->id)->exists());
    }

    /**
     * DEF-010: similarPartners() must not query until the input is at least
     * three characters long. This pins the minimum-query-length floor.
     */
    public function test_similar_partners_returns_empty_for_short_input(): void
    {
        Partner::factory()->create(['name' => 'Benchmark University']);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'b')
            ->assertDontSee('Similar partners already exist');

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'be')
            ->assertDontSee('Similar partners already exist');

        Livewire::test('agreement-form')
            ->set('partnerMode', 'new')
            ->set('newPartnerName', 'ben')
            ->assertSee('Similar partners already exist:')
            ->assertSee('Benchmark University');
    }

    /**
     * DEF-009 / Decision M5-5: a null PIC must render as an em dash on the
     * detail page, and the list row must still render.
     */
    public function test_null_pic_renders_as_em_dash_and_row_still_renders(): void
    {
        $agreement = Agreement::factory()->signed()->create([
            'pic_name' => null,
        ]);

        $this->actingAs(User::factory()->legal()->create());

        $detail = Livewire::test('agreement-show', ['agreement' => $agreement]);
        $this->assertStringContainsString('PIC', $detail->html());
        $this->assertStringContainsString('<dd class="mt-1 text-sm">—</dd>', $detail->html());

        Livewire::test('agreements-index')
            ->assertSee($agreement->title);
    }
}
