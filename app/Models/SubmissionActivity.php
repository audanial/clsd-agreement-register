<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'user_id', 'type', 'description', 'meta'])]
class SubmissionActivity extends Model
{
    public const TYPE_SUBMISSION_CREATED = 'submission_created';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
