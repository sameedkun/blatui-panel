<?php

namespace App\Models;

use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'email',
        'subject',
        'message',
        'type',
        'status',
        'admin_notes',
        'read_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'status' => FeedbackStatus::class,
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAnonymous(): bool
    {
        return $this->user_id === null;
    }

    /** The account matching this submission's email, if any — surfaced on the Show page. */
    public function matchingAccount(): ?User
    {
        if (! $this->email) {
            return null;
        }

        return User::query()->where('email', $this->email)->first();
    }
}
