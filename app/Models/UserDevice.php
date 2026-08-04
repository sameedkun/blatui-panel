<?php

namespace App\Models;

use App\Enum\DeviceType;
use App\Services\Device\DeviceService;
use Database\Factories\UserDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One row per physical device currently (or previously) logged into an
 * account — holds only the *current* token, never a login history. Revoking
 * or blocking a device always deletes its {@see PersonalAccessToken} row and
 * nulls `token_id` (see the `updated` hook below) so access dies immediately;
 * revoked rows are kept afterward (soft state via `revoked_at`, no
 * device-rotation cooldown) rather than deleted, so usage patterns can be
 * measured later. All mutations go through {@see DeviceService}
 * — never write to this model directly from a controller/Livewire component.
 */
class UserDevice extends Model
{
    /** @use HasFactory<UserDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_id',
        'name',
        'model',
        'platform',
        'os',
        'device_type',
        'app_version',
        'browser',
        'device_fingerprint',
        'push_token',
        'push_provider',
        'ip_address',
        'city',
        'country',
        'country_code',
        'blocked_at',
        'blocked_reason',
        'revoked_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'blocked_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserDevice $device) {
            $device->ulid ??= (string) Str::ulid();
        });

        // Revoking or blocking must kill API access immediately — delete the
        // token row (not just null the FK) so it can never be used again,
        // then clear the pointer. saveQuietly() avoids re-firing `updated`.
        static::updated(function (UserDevice $device) {
            $justRevoked = $device->wasChanged('revoked_at') && $device->revoked_at !== null;
            $justBlocked = $device->wasChanged('blocked_at') && $device->blocked_at !== null;

            if (! $justRevoked && ! $justBlocked) {
                return;
            }

            if ($device->token_id !== null) {
                $device->token()->delete();
                $device->token_id = null;
                $device->saveQuietly();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->whereNull('blocked_at');
    }

    /** Blocked takes visual/state priority over revoked — a blocked device is never "just revoked". */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at')->whereNull('blocked_at');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereNotNull('blocked_at');
    }

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->revoked_at === null && $this->blocked_at === null,
        );
    }

    protected function isBlocked(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->blocked_at !== null,
        );
    }

    protected function isRevoked(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->blocked_at === null && $this->revoked_at !== null,
        );
    }

    /** A human-friendly label for toasts/CSV export when `name` wasn't supplied by the client. */
    public function displayName(): string
    {
        return $this->name ?: ($this->model ?: __('devices.status.unnamed_device'));
    }
}
