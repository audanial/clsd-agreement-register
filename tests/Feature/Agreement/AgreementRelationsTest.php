<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\AgreementActivity;
use App\Models\AgreementFile;
use App\Models\Campus;
use App\Models\Partner;
use App\Models\User;
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

    public function test_pic_relation_uses_the_pic_user_id_column(): void
    {
        $pic = User::factory()->legal()->create();
        $agreement = Agreement::factory()->create(['pic_user_id' => $pic->id]);

        $this->assertInstanceOf(User::class, $agreement->pic);
        $this->assertSame($pic->id, $agreement->pic->id);
    }

    public function test_pic_may_be_null(): void
    {
        $agreement = Agreement::factory()->create(['pic_user_id' => null]);

        $this->assertNull($agreement->pic);
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
