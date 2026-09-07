<?php

namespace Tests\Feature\Console;

use App\Models\Agreement;
use App\Models\AgreementActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArchiveExpiredAgreementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_command_archives_agreements_past_their_expiry_date(): void
    {
        $expired = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
        ]);

        Artisan::call('agreements:archive-expired');

        $expired->refresh();

        $this->assertNotNull($expired->archived_at);
    }

    public function test_scheduled_command_sets_archive_reason_to_expired(): void
    {
        $expired = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
        ]);

        Artisan::call('agreements:archive-expired');

        $expired->refresh();

        $this->assertSame('expired', $expired->archive_reason);
    }

    public function test_scheduled_command_does_not_touch_already_archived_agreements(): void
    {
        $expired = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
            'archived_at' => now()->subDay(),
            'archive_reason' => 'terminated',
        ]);

        $originalArchivedAt = $expired->archived_at->copy();

        Artisan::call('agreements:archive-expired');
        $firstActivityCount = AgreementActivity::where('agreement_id', $expired->id)->count();

        Artisan::call('agreements:archive-expired');

        $expired->refresh();

        $this->assertEquals($originalArchivedAt, $expired->archived_at);
        $this->assertSame('terminated', $expired->archive_reason);
        $this->assertSame($firstActivityCount, AgreementActivity::where('agreement_id', $expired->id)->count());
    }

    public function test_scheduled_command_does_not_archive_an_agreement_with_a_null_expiry_date(): void
    {
        $indefinite = Agreement::factory()->signed()->create([
            'expiry_date' => null,
        ]);

        Artisan::call('agreements:archive-expired');

        $indefinite->refresh();

        $this->assertNull($indefinite->archived_at);
        $this->assertNull($indefinite->archive_reason);
    }

    public function test_scheduled_command_boundary_matches_is_expired(): void
    {
        $expiringToday = Agreement::factory()->signed()->create([
            'expiry_date' => today(),
        ]);

        $expiringTomorrow = Agreement::factory()->signed()->create([
            'expiry_date' => today()->addDay(),
        ]);

        Artisan::call('agreements:archive-expired');

        $expiringToday->refresh();
        $expiringTomorrow->refresh();

        $this->assertNotNull($expiringToday->archived_at);
        $this->assertNull($expiringTomorrow->archived_at);
    }

    public function test_scheduled_command_writes_an_activity_entry_per_archived_agreement(): void
    {
        $first = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
        ]);
        $second = Agreement::factory()->signed()->create([
            'expiry_date' => today()->subDay(),
        ]);

        Artisan::call('agreements:archive-expired');

        $this->assertDatabaseHas('agreement_activities', [
            'agreement_id' => $first->id,
            'type' => 'archived',
        ]);
        $this->assertDatabaseHas('agreement_activities', [
            'agreement_id' => $second->id,
            'type' => 'archived',
        ]);
    }
}
