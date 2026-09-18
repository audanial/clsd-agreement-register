<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLegal(): bool
    {
        return $this->role === 'legal';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    /**
     * Non-Legal UniKL staff who submit agreement requests through the Legal
     * Submission Portal. Sees only their own submissions and uses the
     * Agreement Register as a read-only reference (LP1 Amendment 2).
     */
    public function isRequester(): bool
    {
        return $this->role === 'requester';
    }

    /**
     * Human-readable role name for the interface.
     *
     * `requester` remains the internal role value used by authorization, while
     * Legal staff see the clearer label "Requesting Staff".
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'legal' => 'Legal',
            'viewer' => 'Viewer',
            'requester' => 'Requesting Staff',
            default => ucfirst($this->role),
        };
    }

    /**
     * Anyone inside Legal — the people who vet agreements and review submissions.
     */
    public function isLegalStaff(): bool
    {
        return $this->isAdmin() || $this->isLegal();
    }

    public function canSeePending(): bool
    {
        return $this->isAdmin() || $this->isLegal();
    }

    /**
     * Access to the Agreement Register.
     *
     * Deliberately an allow-list, not a deny-list: a role added later is
     * denied the register by default and has to be granted it on purpose.
     * Requesters were added by LP1 Amendment 2 (17 Sep 2026) as read-only
     * reference users, like viewers; pending agreements stay hidden from
     * them via canSeePending() and the pending global scope.
     */
    public function canAccessRegister(): bool
    {
        return in_array($this->role, ['admin', 'legal', 'viewer', 'requester'], true);
    }

    /**
     * Access to the Legal Submission Portal.
     */
    public function canAccessPortal(): bool
    {
        return in_array($this->role, ['admin', 'legal', 'requester'], true);
    }

    public function canWrite(): bool
    {
        return $this->isAdmin() || $this->isLegal();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'created_by');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
