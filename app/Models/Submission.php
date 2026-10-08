<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    // Document checklist slots (V1 spec §4.1, §5.2). Which slots are mandatory
    // for a given submission depends on engagement category and partner
    // location, which LP2 intake will introduce; this is the full set of keys.
    // corporate_registration is SSM for a Local partner or the equivalent
    // business-registration document for an International partner.
    public const DOCUMENT_SLOT_AGREEMENT = 'agreement';

    public const DOCUMENT_SLOT_STAMP_CERTIFICATE = 'stamp_certificate';

    public const DOCUMENT_SLOTS = [
        self::DOCUMENT_SLOT_AGREEMENT => 'Agreement',
        'requisition_form' => 'Requisition Form',
        'memo' => 'Memo',
        'due_diligence_form' => 'Due Diligence Form',
        'company_profile' => 'Company Profile',
        'corporate_registration' => 'Corporate Registration',
        self::DOCUMENT_SLOT_STAMP_CERTIFICATE => 'LHDN Stamp Certificate',
    ];

    // Version lifecycle labels (V1 spec §5.2).
    public const DOCUMENT_LABEL_SUBMITTED_FOR_REVIEW = 'submitted_for_review';

    public const DOCUMENT_LABEL_REVISED_DURING_LEGAL_REVIEW = 'revised_during_legal_review';

    public const DOCUMENT_LABEL_UNIKL_SIGNED = 'unikl_signed';

    public const DOCUMENT_LABEL_BOTH_PARTIES_SIGNED = 'both_parties_signed';

    public const DOCUMENT_LABEL_FINAL_EXECUTED = 'final_executed';

    public const DOCUMENT_LABELS = [
        self::DOCUMENT_LABEL_SUBMITTED_FOR_REVIEW => 'Submitted for Review',
        self::DOCUMENT_LABEL_REVISED_DURING_LEGAL_REVIEW => 'Revised During Legal Review',
        self::DOCUMENT_LABEL_UNIKL_SIGNED => 'UniKL Signed',
        self::DOCUMENT_LABEL_BOTH_PARTIES_SIGNED => 'Both Parties Signed',
        self::DOCUMENT_LABEL_FINAL_EXECUTED => 'Final Executed',
    ];

    // Review-stage labels; every other Agreement label is post-review and
    // accepts PDF only (spec §5.1, §5.3, §10).
    public const DOCUMENT_REVIEW_LABELS = [
        self::DOCUMENT_LABEL_SUBMITTED_FOR_REVIEW,
        self::DOCUMENT_LABEL_REVISED_DURING_LEGAL_REVIEW,
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

    public function documentVersions(): HasMany
    {
        return $this->hasMany(SubmissionDocumentVersion::class);
    }

    /**
     * The current file for one slot: the highest version number (spec §5.2).
     */
    public function currentDocumentVersion(string $slot): ?SubmissionDocumentVersion
    {
        return $this->documentVersions()
            ->where('slot', $slot)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * The current file for every slot that has one, keyed by slot.
     *
     * @return Collection<string, SubmissionDocumentVersion>
     */
    public function currentDocumentVersions(): Collection
    {
        return $this->documentVersions()
            ->orderByDesc('version')
            ->get()
            ->unique('slot')
            ->keyBy('slot')
            ->toBase();
    }
}
