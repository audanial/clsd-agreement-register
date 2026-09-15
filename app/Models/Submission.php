<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['campus_id', 'title', 'partner_name', 'agreement_type', 'purpose'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUSES = [
        self::STATUS_PENDING,
        'in_review',
        'revision_required',
        'completed',
    ];

    public const AGREEMENT_TYPES = [
        'LOI',
        'NDA',
        'MOA',
        'MOU',
        'SEA',
        'ADDENDUM',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'campus_id' => 'integer',
            'agreement_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SubmissionActivity::class);
    }
}
