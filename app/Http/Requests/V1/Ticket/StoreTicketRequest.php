<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Ticket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Priority is deliberately not accepted here — every self-service ticket
 * starts at the DB/Ticket::create() default (Medium) and is staff-triaged
 * from there, mirroring how TicketPriority's own doc comment describes it
 * ("adjustable by staff"). Attachment rules mirror the admin reply form's
 * (Livewire\Admin\Support\Tickets\Show) exactly, so client-side and
 * server-side limits never drift apart.
 */
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id,is_active,1'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ];
    }
}
