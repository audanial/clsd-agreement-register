<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->legal()->create());
    }

    public function test_not_archived_excludes_archived_rows(): void
    {
        $active = Agreement::factory()->create();
        $archived = Agreement::factory()->archived()->create();

        $notArchived = Agreement::notArchived()->pluck('id');

        $this->assertTrue($notArchived->contains($active->id));
        $this->assertFalse($notArchived->contains($archived->id));
    }

    public function test_archived_returns_only_archived_rows(): void
    {
        Agreement::factory()->create();
        $archived = Agreement::factory()->archived()->create();

        $archivedIds = Agreement::archived()->pluck('id');

        $this->assertCount(1, $archivedIds);
        $this->assertTrue($archivedIds->contains($archived->id));
    }

    public function test_expiring_soon_excludes_agreements_with_a_null_expiry_date(): void
    {
        Agreement::factory()->create();
        Agreement::factory()->expiringSoon()->create();

        $this->assertCount(1, Agreement::expiringSoon()->get());
    }

    public function test_expiring_soon_excludes_already_expired_agreements(): void
    {
        Agreement::factory()->expired()->create();
        Agreement::factory()->expiringSoon()->create();

        $this->assertCount(1, Agreement::expiringSoon()->get());
    }

    public function test_expiring_soon_respects_a_custom_day_window(): void
    {
        Agreement::factory()->create(['expiry_date' => today()->addDays(120)]);
        Agreement::factory()->create(['expiry_date' => today()->addDays(30)]);

        $this->assertCount(1, Agreement::expiringSoon(90)->get());
        $this->assertCount(2, Agreement::expiringSoon(150)->get());
    }

    public function test_expired_excludes_agreements_with_a_null_expiry_date(): void
    {
        Agreement::factory()->create();
        Agreement::factory()->expired()->create();

        $this->assertCount(1, Agreement::expired()->get());
    }

    public function test_status_scope_accepts_a_string_and_an_array(): void
    {
        $pending = Agreement::factory()->pending()->create();
        $awaiting = Agreement::factory()->awaitingPartner()->create();
        $signed = Agreement::factory()->signed()->create();

        $stringResult = Agreement::status('pending')->pluck('id');
        $this->assertCount(1, $stringResult);
        $this->assertTrue($stringResult->contains($pending->id));

        $arrayResult = Agreement::status(['pending', 'awaiting_partner'])->pluck('id');
        $this->assertCount(2, $arrayResult);
        $this->assertTrue($arrayResult->contains($pending->id));
        $this->assertTrue($arrayResult->contains($awaiting->id));
        $this->assertFalse($arrayResult->contains($signed->id));
    }

    public function test_project_status_scope_filters_correctly(): void
    {
        $ongoing = Agreement::factory()->create(['project_status' => 'ongoing']);
        $completed = Agreement::factory()->create(['project_status' => 'completed']);

        $result = Agreement::projectStatus('ongoing')->pluck('id');

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($ongoing->id));
        $this->assertFalse($result->contains($completed->id));
    }
}
