<?php

namespace App\Actions;

use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\SubmissionDocumentVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreSubmissionDocumentVersion
{
    public const DISK = 'documents';

    // Initial technical limit, inclusive (spec §5.1).
    public const MAX_BYTES = 20 * 1024 * 1024;

    // Accepted extensions and the MIME type the file's content must be
    // detected as. Anything else (.doc, .docm, ZIP, executables, images) is
    // rejected (spec §5.1).
    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Store an uploaded file as the next immutable version of a checklist slot.
     *
     * This is an internal building block, like RecordSubmissionActivity: it
     * does NOT authorise. The calling boundary (intake, reopened-slot response,
     * signing upload) must authorise the actor, slot and stage first.
     *
     * The file is written under a server-generated path before the database
     * transaction; if the version row or its audit event cannot be saved, the
     * file is deleted again and the slot's current version is unchanged.
     *
     * @throws ValidationException
     */
    public function __invoke(Submission $submission, User $uploader, string $slot, ?string $label, UploadedFile $file): SubmissionDocumentVersion
    {
        $extension = $this->validate($slot, $label, $file);

        $checksum = hash_file('sha256', $file->getRealPath());
        $disk = Storage::disk(self::DISK);
        $path = $disk->putFileAs(
            "submissions/{$submission->id}/{$slot}",
            $file,
            Str::uuid()->toString().'.'.$extension,
        );

        if ($path === false) {
            throw new RuntimeException('The document could not be stored.');
        }

        try {
            return DB::transaction(function () use ($submission, $uploader, $slot, $label, $file, $extension, $checksum, $path) {
                // Serialise version allocation per submission. SQLite ignores
                // FOR UPDATE but serialises writers; the unique index on
                // (submission_id, slot, version) is the backstop either way.
                Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

                $next = (int) SubmissionDocumentVersion::query()
                    ->where('submission_id', $submission->id)
                    ->where('slot', $slot)
                    ->max('version') + 1;

                $version = new SubmissionDocumentVersion;
                $version->forceFill([
                    'submission_id' => $submission->id,
                    'slot' => $slot,
                    'version' => $next,
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $extension,
                    'size_bytes' => $file->getSize(),
                    'sha256' => $checksum,
                    'label' => $label,
                    'uploaded_by' => $uploader->id,
                ])->save();

                app(RecordSubmissionActivity::class)(
                    $submission,
                    $uploader,
                    SubmissionActivity::TYPE_DOCUMENT_UPLOADED,
                    Submission::DOCUMENT_SLOTS[$slot].' version '.$next.' uploaded',
                    [
                        'slot' => $slot,
                        'version' => $next,
                        'label' => $label,
                        'document_version_id' => $version->id,
                    ],
                );

                return $version;
            });
        } catch (UniqueConstraintViolationException) {
            $disk->delete($path);

            // A concurrent upload took this version number: fail safely and
            // ask the user to refresh (spec §14) rather than overwrite.
            throw ValidationException::withMessages([
                'file' => 'This document changed while you were uploading. Refresh the page and try again.',
            ]);
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }

    /**
     * Validate slot, label and file; return the normalised extension.
     *
     * @throws ValidationException
     */
    private function validate(string $slot, ?string $label, UploadedFile $file): string
    {
        $isStampCertificate = $slot === Submission::DOCUMENT_SLOT_STAMP_CERTIFICATE;
        $allowedLabels = match (true) {
            $slot === Submission::DOCUMENT_SLOT_AGREEMENT => array_keys(Submission::DOCUMENT_LABELS),
            $isStampCertificate => [],
            default => Submission::DOCUMENT_REVIEW_LABELS,
        };

        Validator::make(
            ['slot' => $slot, 'label' => $label, 'file' => $file],
            [
                'slot' => ['required', Rule::in(array_keys(Submission::DOCUMENT_SLOTS))],
                // The stamp certificate is not an Agreement version and carries
                // no lifecycle label; every other slot requires one (spec §5.2).
                'label' => $isStampCertificate
                    ? ['prohibited']
                    : ['required', Rule::in($allowedLabels)],
                'file' => ['required', 'file'],
            ],
        )->validate();

        $extension = strtolower($file->getClientOriginalExtension());
        $pdfOnly = $isStampCertificate || ! in_array($label, Submission::DOCUMENT_REVIEW_LABELS, true);
        $allowed = $pdfOnly ? ['pdf'] : array_keys(self::MIME_TYPES);

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => $pdfOnly ? 'This document must be a PDF.' : 'Upload a PDF or Word (.docx) file.',
            ]);
        }

        if ($file->getMimeType() !== self::MIME_TYPES[$extension]) {
            throw ValidationException::withMessages([
                'file' => 'The file content does not match its .'.$extension.' extension.',
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'The file must not be larger than 20 MB.',
            ]);
        }

        if (mb_strlen($file->getClientOriginalName()) > 255) {
            throw ValidationException::withMessages([
                'file' => 'The file name must not be longer than 255 characters.',
            ]);
        }

        return $extension;
    }
}
