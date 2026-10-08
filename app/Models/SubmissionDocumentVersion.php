<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One immutable uploaded file in a submission checklist slot (V1 spec §5.2).
 *
 * No attribute is mass assignable: rows are written only by
 * App\Actions\StoreSubmissionDocumentVersion. Once saved, a version can never
 * be updated or deleted through Eloquent; a replacement is a new version.
 */
class SubmissionDocumentVersion extends Model
{
    protected function casts(): array
    {
        return [
            'submission_id' => 'integer',
            'uploaded_by' => 'integer',
            'version' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Submission document versions are immutable; upload a new version instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Submission document versions are immutable and cannot be deleted.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
