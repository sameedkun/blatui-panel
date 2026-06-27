<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
    'is_temporary',
    'password_changed_at',
    'registration_date',
    'last_login',
    'banned_at',
    'ban_reason',
    'avatar',
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
            'is_temporary' => 'boolean',
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
}