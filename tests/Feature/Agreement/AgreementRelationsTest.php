<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\AgreementActivity;
use App\Models\AgreementFile;
use App\Models\Campus;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_campus_and_pic_relations_resolve(): void
    {
        $agreement = Agreement::factory()->create();

        $this->assertInstanceOf(Partner::class, $agreement->partner);
        $this->assertInstanceOf(Campus::class, $agreement->campus);
    }

    public function test_pic_name_is_fillable_and_saves_as_a_plain_string(): void
    {
        $agreement = Agreement::factory()->create();

        $agreement->update(['pic_name' => 'Ahmad bin Osman']);

        $this->assertSame('Ahmad bin Osman', $agreement->fresh()->pic_name);
    }

    public function test_pic_relation_no_longer_exists_on_the_model(): void
    {
        $agreement = Agreement::factory()->create();

        $this->assertFalse(method_exists($agreement, 'pic'));
    }

    public function test_files_and_activities_relations_resolve(): void
    {
        $agreement = Agreement::factory()->create();

        AgreementFile::create([
            'agreement_id' => $agreement->id,
            'document_type' => 'draft',
            'original_filename' => 'draft.pdf',
            'storage_path' => 'agreements/draft.pdf',
        ]);

        AgreementActivity::create([
            'agreement_id' => $agreement->id,
            'type' => 'created',
            'description' => 'Agreement created',
        ]);

        $this->assertCount(1, $agreement->files);
        $this->assertInstanceOf(AgreementFile::class, $agreement->files->first());
        $this->assertCount(1, $agreement->activities);
        $this->assertInstanceOf(AgreementActivity::class, $agreement->activities->first());
    }
}
