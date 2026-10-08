<?php

namespace Tests\Feature\Submission;

use App\Actions\RecordSubmissionActivity;
use App\Actions\StoreSubmissionDocumentVersion;
use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\SubmissionDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class StoreSubmissionDocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
    }

    private function store(Submission $submission, User $user, string $slot, ?string $label, UploadedFile $file): SubmissionDocumentVersion
    {
        return app(StoreSubmissionDocumentVersion::class)($submission, $user, $slot, $label, $file);
    }

    private function pdf(string $name = 'fictional-agreement.pdf', int $kilobytes = 10): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    private function docx(string $name = 'fictional-agreement.docx', int $kilobytes = 10): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, self::DOCX);
    }

    /**
     * @return array{0: Submission, 1: User}
     */
    private function submissionWithOwner(): array
    {
        $owner = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $owner->id]);

        return [$submission, $owner];
    }

    private function assertRejected(callable $upload, string $field = 'file'): void
    {
        try {
            $upload();
            $this->fail('Expected the upload to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, SubmissionDocumentVersion::query()->count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame(0, SubmissionActivity::query()->where('type', SubmissionActivity::TYPE_DOCUMENT_UPLOADED)->count());
    }

    public function test_first_upload_stores_version_one_on_the_documents_disk_with_a_server_path(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $version = $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf('Fictional Draft Agreement.pdf'));

        $this->assertSame(1, $version->version);
        $this->assertMatchesRegularExpression(
            '#^submissions/'.$submission->id.'/agreement/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$#',
            $version->path
        );
        $this->assertStringNotContainsString('Fictional', $version->path);
        Storage::disk('documents')->assertExists($version->path);
    }

    public function test_metadata_records_uploader_filename_mime_size_checksum_disk_and_label(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();
        $content = '%PDF-1.4 fictional synthetic content';

        $stored = $this->store(
            $submission, $owner, 'agreement', 'submitted_for_review',
            UploadedFile::fake()->createWithContent('Fictional Draft.pdf', $content)
        );

        $version = SubmissionDocumentVersion::query()->findOrFail($stored->id);
        $this->assertSame($submission->id, $version->submission_id);
        $this->assertSame('agreement', $version->slot);
        $this->assertSame('documents', $version->disk);
        $this->assertSame('Fictional Draft.pdf', $version->original_filename);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertSame('pdf', $version->extension);
        $this->assertSame(strlen($content), $version->size_bytes);
        $this->assertSame(hash('sha256', $content), $version->sha256);
        $this->assertSame('submitted_for_review', $version->label);
        $this->assertSame($owner->id, $version->uploaded_by);
        $this->assertNotNull($version->created_at);
        $this->assertSame($content, Storage::disk('documents')->get($version->path));
    }

    public function test_replacement_creates_version_two_and_keeps_version_one(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();
        $legal = User::factory()->legal()->create();

        $first = $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf());
        $second = $this->store($submission, $legal, 'agreement', 'revised_during_legal_review', $this->docx());

        $this->assertSame(2, $second->version);
        $this->assertNotSame($first->path, $second->path);

        $reloadedFirst = SubmissionDocumentVersion::query()->findOrFail($first->id);
        $this->assertSame(1, $reloadedFirst->version);
        $this->assertSame($first->path, $reloadedFirst->path);
        $this->assertSame($first->sha256, $reloadedFirst->sha256);
        Storage::disk('documents')->assertExists([$first->path, $second->path]);
        $this->assertSame(2, $submission->documentVersions()->count());
    }

    public function test_current_version_is_the_highest_version_in_each_slot(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf());
        $agreementV2 = $this->store($submission, $owner, 'agreement', 'revised_during_legal_review', $this->pdf());
        $memo = $this->store($submission, $owner, 'memo', 'submitted_for_review', $this->pdf('fictional-memo.pdf'));

        $this->assertSame($agreementV2->id, $submission->currentDocumentVersion('agreement')->id);
        $this->assertSame($memo->id, $submission->currentDocumentVersion('memo')->id);
        $this->assertNull($submission->currentDocumentVersion('company_profile'));

        $current = $submission->currentDocumentVersions();
        $this->assertEqualsCanonicalizing(['agreement', 'memo'], $current->keys()->all());
        $this->assertSame($agreementV2->id, $current['agreement']->id);
    }

    public function test_disallowed_types_are_rejected_and_nothing_is_written(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $files = [
            ['fictional.doc', 'application/msword'],
            ['fictional.docm', 'application/vnd.ms-word.document.macroEnabled.12'],
            ['fictional.zip', 'application/zip'],
            ['fictional.exe', 'application/x-msdownload'],
            ['fictional.jpg', 'image/jpeg'],
            ['fictional.png', 'image/png'],
        ];

        foreach ($files as [$name, $mime]) {
            $this->assertRejected(fn () => $this->store(
                $submission, $owner, 'agreement', 'submitted_for_review',
                UploadedFile::fake()->create($name, 10, $mime)
            ));
        }

        $this->assertNothingStored();
    }

    public function test_file_over_20_mb_is_rejected_and_exactly_20_mb_is_accepted(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $this->assertRejected(fn () => $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf('too-large.pdf', 20 * 1024 + 1)));
        $this->assertNothingStored();

        $version = $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf('at-limit.pdf', 20 * 1024));

        $this->assertSame(20 * 1024 * 1024, $version->size_bytes);
    }

    public function test_extension_and_mime_mismatch_is_rejected(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $this->assertRejected(fn () => $this->store($submission, $owner, 'agreement', 'submitted_for_review', UploadedFile::fake()->create('renamed.pdf', 10, 'application/zip')));
        $this->assertRejected(fn () => $this->store($submission, $owner, 'agreement', 'submitted_for_review', UploadedFile::fake()->create('renamed.docx', 10, 'application/pdf')));

        $this->assertNothingStored();
    }

    public function test_mime_type_is_detected_from_file_content_not_the_client(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        // Real temporary files (not fakes), so the MIME type comes from
        // content sniffing exactly as it would for a browser upload.
        $text = tempnam(sys_get_temp_dir(), 'lp2a');
        file_put_contents($text, 'Plain text pretending to be a PDF.');
        $this->assertRejected(fn () => $this->store(
            $submission, $owner, 'agreement', 'submitted_for_review',
            new UploadedFile($text, 'pretend.pdf', 'application/pdf', null, true)
        ));
        $this->assertNothingStored();

        $pdf = tempnam(sys_get_temp_dir(), 'lp2a');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        $version = $this->store(
            $submission, $owner, 'agreement', 'submitted_for_review',
            new UploadedFile($pdf, 'fictional-real.pdf', 'application/octet-stream', null, true)
        );

        $this->assertSame('application/pdf', $version->mime_type);
    }

    public function test_final_stage_labels_accept_pdf_only(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();
        $legal = User::factory()->legal()->create();

        foreach (['unikl_signed', 'both_parties_signed', 'final_executed'] as $label) {
            $this->assertRejected(fn () => $this->store($submission, $legal, 'agreement', $label, $this->docx()));
            $this->assertSame($label, $this->store($submission, $legal, 'agreement', $label, $this->pdf())->label);
        }

        // The LHDN stamp certificate is PDF-only and carries no Agreement label.
        $this->assertRejected(fn () => $this->store($submission, $owner, 'stamp_certificate', null, $this->docx()));
        $this->assertRejected(fn () => $this->store($submission, $owner, 'stamp_certificate', 'final_executed', $this->pdf()), 'label');
        $this->assertNull($this->store($submission, $owner, 'stamp_certificate', null, $this->pdf('fictional-stamp.pdf'))->label);

        // Supporting intake documents take review-stage labels only.
        $this->assertRejected(fn () => $this->store($submission, $owner, 'memo', 'unikl_signed', $this->pdf()), 'label');
        $this->assertRejected(fn () => $this->store($submission, $owner, 'memo', null, $this->pdf()), 'label');
        $this->assertRejected(fn () => $this->store($submission, $owner, 'agreement', 'not_a_label', $this->pdf()), 'label');

        $this->assertSame(4, SubmissionDocumentVersion::query()->count());
    }

    public function test_unknown_slot_key_is_rejected(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $this->assertRejected(fn () => $this->store($submission, $owner, 'passport', 'submitted_for_review', $this->pdf()), 'slot');
        $this->assertRejected(fn () => $this->store($submission, $owner, '../agreement', 'submitted_for_review', $this->pdf()), 'slot');

        $this->assertNothingStored();
    }

    public function test_database_failure_removes_the_stored_file_and_keeps_the_current_version(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();
        $first = $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf());

        $this->mock(RecordSubmissionActivity::class, function ($mock) {
            $mock->shouldReceive('__invoke')->andThrow(new RuntimeException('Simulated audit write failure.'));
        });

        try {
            $this->store($submission, $owner, 'agreement', 'revised_during_legal_review', $this->pdf());
            $this->fail('Expected the audit failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated audit write failure.', $e->getMessage());
        }

        $this->assertSame(1, SubmissionDocumentVersion::query()->count());
        $this->assertSame($first->id, $submission->currentDocumentVersion('agreement')->id);
        $this->assertSame([$first->path], Storage::disk('documents')->allFiles());
    }

    public function test_upload_appends_a_document_uploaded_activity_atomically(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        $version = $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf('Fictional Secret Name.pdf'));

        $activity = SubmissionActivity::query()
            ->where('submission_id', $submission->id)
            ->where('type', SubmissionActivity::TYPE_DOCUMENT_UPLOADED)
            ->sole();

        $this->assertSame($owner->id, $activity->user_id);
        $this->assertSame('Agreement version 1 uploaded', $activity->description);
        $this->assertSame([
            'slot' => 'agreement',
            'version' => 1,
            'label' => 'submitted_for_review',
            'document_version_id' => $version->id,
        ], $activity->meta);
        $this->assertStringNotContainsString('Fictional Secret Name', json_encode($activity->meta));
    }

    public function test_sequential_uploads_allocate_versions_one_and_two_per_slot_independently(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();
        [$otherSubmission, $otherOwner] = $this->submissionWithOwner();

        $this->assertSame(1, $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf())->version);
        $this->assertSame(2, $this->store($submission, $owner, 'agreement', 'revised_during_legal_review', $this->pdf())->version);
        $this->assertSame(1, $this->store($submission, $owner, 'memo', 'submitted_for_review', $this->pdf())->version);
        $this->assertSame(1, $this->store($otherSubmission, $otherOwner, 'agreement', 'submitted_for_review', $this->pdf())->version);
    }

    public function test_next_version_follows_the_highest_existing_version(): void
    {
        [$submission, $owner] = $this->submissionWithOwner();

        foreach ([1, 3] as $number) {
            $this->insertRawVersion($submission, $owner, $number);
        }

        $this->assertSame(4, $this->store($submission, $owner, 'agreement', 'revised_during_legal_review', $this->pdf())->version);
    }

    public function test_a_version_number_taken_concurrently_fails_safely_and_removes_the_file(): void
    {
        // True concurrency cannot be reproduced on SQLite. Simulate the race:
        // just before the row is inserted, another writer takes the same
        // version number, so the unique index rejects this insert.
        [$submission, $owner] = $this->submissionWithOwner();

        SubmissionDocumentVersion::creating(function (SubmissionDocumentVersion $version) use ($submission, $owner) {
            $this->insertRawVersion($submission, $owner, $version->version);
        });

        $this->assertRejected(fn () => $this->store($submission, $owner, 'agreement', 'submitted_for_review', $this->pdf()));

        $this->assertNothingStored();
    }

    private function insertRawVersion(Submission $submission, User $user, int $number): void
    {
        DB::table('submission_document_versions')->insert([
            'submission_id' => $submission->id,
            'slot' => 'agreement',
            'version' => $number,
            'disk' => 'documents',
            'path' => "submissions/{$submission->id}/agreement/raw-{$number}.pdf",
            'original_filename' => "raw-{$number}.pdf",
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1,
            'sha256' => str_repeat('0', 64),
            'label' => 'submitted_for_review',
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
