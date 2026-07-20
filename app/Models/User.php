<?php

namespace App\Models;

use App\Enum\UserType;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\Attributes\Sluggable;

#[Sluggable(from: 'name', to: 'slug')]
#[Fillable([
    'name',
    'email',
    'password',
    'slug',
    'google_id',
    'apple_id',
    'external_id',
    'password_changed_at',
    'registration_date',
    'last_login',
    'banned_at',
    'ban_reason',
    'deletion_requested_at',
    'deletion_requested_by',
    'deletion_reason',
    'avatar',
    'type',
    'email_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasFactory, HasRoles, Notifiable, SoftDeletes;

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
            'password_changed_at' => 'datetime',
            'registration_date' => 'datetime',
            'last_login' => 'datetime',
            'banned_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'type' => UserType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($user) {
            do {
                $ulid = (string) Str::ulid();
            } while (static::where('external_id', $ulid)->exists());

            $user->external_id = $ulid;
        });
    }

    // -------------------------------------------------------------------------
    // Type scopes
    // -------------------------------------------------------------------------

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('type', UserType::Staff);
    }

    public function scopeAppUsers(Builder $query): Builder
    {
        return $query->where('type', UserType::App);
    }

    public function scopeGuests(Builder $query): Builder
    {
        return $query->where('type', UserType::Guest);
    }

    // -------------------------------------------------------------------------
    // Staff + role helpers (Spatie roles only ever attach to staff)
    // -------------------------------------------------------------------------

    /**
     * Staff who hold a specific role.
     * Usage: User::staffWithRole('admin')->get();
     */
    public function scopeStaffWithRole(Builder $query, string $role): Builder
    {
        return $query->staff()->role($role); // ->role() is Spatie's scope
    }

    /**
     * Staff with any panel role at all (excludes staff seeded without one).
     */
    public function scopeStaffWithAnyRole(Builder $query): Builder
    {
        return $query->staff()->whereHas('roles');
    }

    /**
     * The super-admin(s).
     */
    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->staff()->role(config('panel.super_admin_role'));
    }

    /**
     * Staff who can access the admin panel (by permission, not role).
     * Catches super-admin via Gate too if you check ->get() results with can();
     * as a query this filters on the explicit permission.
     */
    public function scopePanelAccessible(Builder $query): Builder
    {
        return $query->staff()->permission(config('panel.access')['admin']);
    }

    // -------------------------------------------------------------------------
    // Type predicates
    // -------------------------------------------------------------------------

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isAppUser(): bool
    {
        return $this->type === UserType::App;
    }

    public function isGuest(): bool
    {
        return $this->type === UserType::Guest;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isStaff() && $this->hasRole(config('panel.super_admin_role'));
    }

    // -------------------------------------------------------------------------
    // Ban state (from banned_at column)
    // -------------------------------------------------------------------------

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function scopeBanned(Builder $query): Builder
    {
        return $query->whereNotNull('banned_at');
    }

    public function scopeNotBanned(Builder $query): Builder
    {
        return $query->whereNull('banned_at');
    }

    // -------------------------------------------------------------------------
    // Pending deletion (grace period — app users only)
    // -------------------------------------------------------------------------

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    /** When the scheduled purge will permanently remove this account, if pending. */
    public function deletionPurgesAt(): ?CarbonInterface
    {
        return $this->deletion_requested_at
            ?->copy()
            ->addHours((int) config('panel.account_deletion_grace_hours', 24));
    }

    /** The user may only self-cancel a deletion they requested themselves. */
    public function canCancelDeletion(): bool
    {
        return $this->isPendingDeletion() && $this->deletion_requested_by === 'user';
    }

    public function scopePendingDeletion(Builder $query): Builder
    {
        return $query->whereNotNull('deletion_requested_at');
    }

    // -------------------------------------------------------------------------
    // Avatar
    // -------------------------------------------------------------------------

    /** Full URL to the avatar on the default disk, or null if unset/missing. */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Storage::exists($this->avatar) ? Storage::url($this->avatar) : null;
    }

    // -------------------------------------------------------------------------
    // Lifecycle state (active / pending deletion / soft deleted)
    // -------------------------------------------------------------------------

    /** The account's current lifecycle state, used to gate which actions apply. */
    public function lifecycleState(): string
    {
        return match (true) {
            $this->trashed() => 'trashed',
            $this->isPendingDeletion() => 'pending',
            default => 'active',
        };
    }

    // -------------------------------------------------------------------------
    // Policy Acceptances
    // -------------------------------------------------------------------------

    /**
     * Get the policy acceptances for the user.
     *
     * @return HasMany<PolicyAcceptance, $this>
     */
    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    /**
     * Record that the user has accepted a policy or a specific version of it.
     */
    public function acceptPolicy(PolicyVersion|Policy $policyOrVersion): PolicyAcceptance
    {
        $version = $policyOrVersion instanceof Policy
            ? $policyOrVersion->activeVersion()->first()
            : $policyOrVersion;

        if (! $version) {
            throw new \InvalidArgumentException('No active version found for the given policy.');
        }

        return $this->policyAcceptances()->firstOrCreate([
            'policy_version_id' => $version->id,
        ], [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Check if the user has accepted the current active version of a policy.
     */
    public function hasAcceptedPolicy(Policy|string $policy): bool
    {
        $policyModel = $policy instanceof Policy
            ? $policy
            : Policy::where('key', $policy)->first();

        if (! $policyModel) {
            return false;
        }

        $activeVersion = $policyModel->activeVersion()->first();
        if (! $activeVersion) {
            return false;
        }

        return $this->policyAcceptances()
            ->where('policy_version_id', $activeVersion->id)
            ->exists();
    }
}
