<?php

namespace Tests\Feature\Submission;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmissionDocumentSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function row(Submission $submission, User $user, int $version, string $path): array
    {
        return [
            'submission_id' => $submission->id,
            'slot' => 'agreement',
            'version' => $version,
            'disk' => 'documents',
            'path' => $path,
            'original_filename' => 'fictional.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1,
            'sha256' => str_repeat('0', 64),
            'label' => 'submitted_for_review',
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function test_foreign_keys_are_indexed_and_version_numbers_are_unique_per_slot(): void
    {
        $indexes = collect(Schema::getIndexes('submission_document_versions'));
        $leadingColumns = $indexes->map(fn (array $index) => $index['columns'][0] ?? null)->filter()->all();

        foreach (Schema::getForeignKeys('submission_document_versions') as $foreignKey) {
            $this->assertContains($foreignKey['columns'][0], $leadingColumns);
        }

        $this->assertTrue($indexes->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['submission_id', 'slot', 'version']
        ));

        $owner = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $owner->id]);
        DB::table('submission_document_versions')->insert($this->row($submission, $owner, 1, 'submissions/a.pdf'));

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('submission_document_versions')->insert($this->row($submission, $owner, 1, 'submissions/b.pdf'));
    }

    public function test_a_submission_with_document_versions_cannot_be_hard_deleted(): void
    {
        $owner = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $owner->id]);
        DB::table('submission_document_versions')->insert($this->row($submission, $owner, 1, 'submissions/a.pdf'));

        $this->expectException(QueryException::class);
        DB::table('submissions')->where('id', $submission->id)->delete();
    }
}
