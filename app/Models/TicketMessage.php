<?php

namespace App\Models;

use App\Enum\TicketMessageAuthorType;
use App\Services\Ticket\TicketService;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'ticket_id',
    'user_id',
    'author_type',
    'message',
    'attachments',
])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'author_type' => TicketMessageAuthorType::class,
            'attachments' => 'array',
        ];
    }

    /**
     * The ticket this message belongs to.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The author, when this isn't a system-generated message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->author_type === TicketMessageAuthorType::System;
    }

    /**
     * Attachment metadata with a resolved download URL added per entry —
     * the stored `path` stays disk-agnostic (see
     * {@see TicketService::storeAttachments()}), the URL is
     * only ever resolved here at render time via the app's default disk.
     *
     * @return array<int, array{name: string, size: int, mime: string, path: string, url: ?string}>
     */
    public function attachmentsWithUrls(): array
    {
        return collect($this->attachments ?? [])
            ->map(fn (array $attachment): array => [
                ...$attachment,
                'url' => Storage::exists($attachment['path']) ? Storage::url($attachment['path']) : null,
            ])
            ->all();
    }
}
