<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LP2-A: immutable document versions for submission checklist slots
        // (V1 spec §5.2, §13). Each row is one uploaded file. A replacement adds
        // a new row with the next version number; earlier rows and their files
        // are never overwritten or deleted. The current file for a slot is the
        // row with the highest version number, so no mutable "current" pointer
        // exists to drift out of sync.
        Schema::create('submission_document_versions', function (Blueprint $table) {
            $table->id();

            // Same deliberate exception as submission_activities: version
            // history is part of the legal record and must never disappear with
            // an ordinary delete, so the parent submission cannot be hard-deleted
            // while it has versions.
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();

            // Checklist slot key (Submission::DOCUMENT_SLOTS). Plain string, not
            // a database enum, so later slots do not require a SQLite rebuild.
            $table->string('slot', 64);

            // 1, 2, 3 ... per (submission, slot). Allocated by the trusted
            // upload action under a lock on the parent submission row; the
            // unique index below is the backstop against concurrent uploads.
            $table->unsignedInteger('version');

            // The disk the file was written to, so a later driver switch
            // (local -> S3-compatible) does not strand existing files.
            $table->string('disk', 64);

            // Server-generated path: submissions/{id}/{slot}/{uuid}.{ext}. The
            // requester's filename never becomes part of the path (spec §14).
            $table->string('path')->unique();

            // Metadata required by spec §5.2.
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);

            // Lifecycle label (Submission::DOCUMENT_LABELS). Nullable only for
            // the LHDN stamp certificate, which has its own slot and history and
            // is not an Agreement version (spec §5.2).
            $table->string('label')->nullable();

            // Required uploader. Users are deactivated, not deleted, so deletion
            // of an uploader is prevented rather than nulled out.
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            // One row per version number per slot. Also the leading index for
            // the submission_id foreign key.
            $table->unique(['submission_id', 'slot', 'version']);

            // SQLite does not index foreign keys automatically.
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_document_versions');
    }
};
