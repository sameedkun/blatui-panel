<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enum\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
    'avatar',
    'type',
    'email_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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
}
