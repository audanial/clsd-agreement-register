<?php

namespace Tests\Feature\Submission;

use App\Actions\StoreSubmissionDocumentVersion;
use App\Models\Submission;
use App\Models\SubmissionDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class SubmissionDocumentVersionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function storedVersion(): SubmissionDocumentVersion
    {
        Storage::fake('documents');

        $owner = User::factory()->requester()->create();
        $submission = Submission::factory()->create(['created_by' => $owner->id]);

        return app(StoreSubmissionDocumentVersion::class)(
            $submission, $owner, 'agreement', 'submitted_for_review',
            UploadedFile::fake()->create('fictional-agreement.pdf', 10, 'application/pdf')
        );
    }

    public function test_existing_version_cannot_be_updated(): void
    {
        $version = SubmissionDocumentVersion::query()->findOrFail($this->storedVersion()->id);
        $originalPath = $version->path;

        $version->path = 'submissions/elsewhere.pdf';
        $version->label = 'final_executed';

        try {
            $version->save();
            $this->fail('Expected the update to be refused.');
        } catch (LogicException) {
            // expected
        }

        $reloaded = SubmissionDocumentVersion::query()->findOrFail($version->id);
        $this->assertSame($originalPath, $reloaded->path);
        $this->assertSame('submitted_for_review', $reloaded->label);
    }

    public function test_existing_version_cannot_be_deleted(): void
    {
        $version = SubmissionDocumentVersion::query()->findOrFail($this->storedVersion()->id);

        try {
            $version->delete();
            $this->fail('Expected the delete to be refused.');
        } catch (LogicException) {
            // expected
        }

        $this->assertTrue(SubmissionDocumentVersion::query()->whereKey($version->id)->exists());
        Storage::disk('documents')->assertExists($version->path);
    }

    public function test_no_attribute_is_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        SubmissionDocumentVersion::create(['slot' => 'agreement', 'version' => 1]);
    }
}
