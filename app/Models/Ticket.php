<?php

namespace App\Models;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'category_id',
    'assigned_to',
    'subject',
    'status',
    'priority',
    'last_user_response_at',
    'last_staff_response_at',
    'closed_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // TicketMessage rows cascade-delete at the DB level (FK constraint), but
        // that never touches the filesystem — this is what actually removes the
        // stored files, in one shot, regardless of how many messages/attachments
        // existed.
        static::deleting(function (Ticket $ticket): void {
            Storage::deleteDirectory($ticket->attachmentsPath());
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_user_response_at' => 'datetime',
            'last_staff_response_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The requester who raised this ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category this ticket was routed under, if any.
     *
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /**
     * The staff member currently handling this ticket, if assigned.
     *
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The conversation thread, oldest first.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to !== null;
    }

    /** The single folder every message attachment for this ticket is stored under. */
    public function attachmentsPath(): string
    {
        return "ticket-attachments/{$this->id}";
    }

    /**
     * Scopes tickets to what a staff member is allowed to see: a super admin
     * sees everything; anyone else sees only tickets assigned to them, plus
     * unassigned tickets in a category they're an agent for. Shared by the
     * index listing, its stats, bulk actions, and the detail page so the
     * restriction can't be bypassed by visiting a ticket URL directly.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $categoryIds = $user->agentCategories()->pluck('categories.id');

        return $query->where(function (Builder $q) use ($user, $categoryIds): void {
            $q->where('assigned_to', $user->id)
                ->orWhere(function (Builder $q2) use ($categoryIds): void {
                    $q2->whereNull('assigned_to')->whereIn('category_id', $categoryIds);
                });
        });
    }
}
