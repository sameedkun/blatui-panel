<?php

namespace App\Http\Resources\V1;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_type' => $this->author_type->value,
            'author_name' => $this->user?->name,
            'message' => $this->message,
            'attachments' => $this->attachmentsWithUrls(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
