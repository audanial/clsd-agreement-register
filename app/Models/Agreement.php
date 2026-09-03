<?php

namespace App\Models;

use App\Models\Scopes\HidePendingFromNonLegalScope;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'type', 'partner_id', 'campus_id', 'pic_name', 'sector',
    'agreement_date', 'expiry_date',
    'received_from_po_at', 'board_approved_at', 'signed_by_unikl_at', 'sent_to_partner_at', 'signed_date',
    'document_status', 'project_status', 'project_status_updated_at',
    'scope', 'notes',
])]
#[ScopedBy(HidePendingFromNonLegalScope::class)]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory, SoftDeletes;

    public const STALE_AFTER_DAYS = 90;

    protected function casts(): array
    {
        return [
            'agreement_date' => 'date',
            'expiry_date' => 'date',
            'received_from_po_at' => 'date',
            'board_approved_at' => 'date',
            'signed_by_unikl_at' => 'date',
            'sent_to_partner_at' => 'date',
            'signed_date' => 'date',
            'project_status_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AgreementFile::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AgreementActivity::class);
    }

    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    #[Scope]
    protected function status(Builder $query, $status): void
    {
        $query->whereIn('document_status', (array) $status);
    }

    #[Scope]
    protected function projectStatus(Builder $query, $status): void
    {
        $query->whereIn('project_status', (array) $status);
    }

    #[Scope]
    protected function expiringSoon(Builder $query, int $days = 90): void
    {
        $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today());
    }

    protected function year(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->agreement_date?->year,
        );
    }

    public function durationInMonths(): ?int
    {
        if ($this->agreement_date === null || $this->expiry_date === null) {
            return null;
        }

        if ($this->expiry_date->lt($this->agreement_date)) {
            return null;
        }

        return abs($this->agreement_date->diffInMonths($this->expiry_date));
    }

    public function durationLabel(): ?string
    {
        $months = $this->durationInMonths();

        if ($months === null) {
            return null;
        }

        if ($months === 0) {
            return 'less than a month';
        }

        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years > 0 && $remainingMonths > 0) {
            return ($years === 1 ? '1 year' : $years.' years').', '
                .($remainingMonths === 1 ? '1 month' : $remainingMonths.' months');
        }

        if ($years > 0) {
            return $years === 1 ? '1 year' : $years.' years';
        }

        return $remainingMonths === 1 ? '1 month' : $remainingMonths.' months';
    }

    public function isIndefinite(): bool
    {
        return is_null($this->expiry_date);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->startOfDay()->lte(today());
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasStaleProjectStatus(): bool
    {
        return $this->project_status_updated_at === null
            || $this->project_status_updated_at->startOfDay()
                ->lte(now()->subDays(self::STALE_AFTER_DAYS)->startOfDay());
    }
}
