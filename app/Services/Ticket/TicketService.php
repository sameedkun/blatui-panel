<?php

namespace App\Services\Ticket;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The only place a ticket's state changes — mirrors {@see SubscriptionService}:
 * every caller (admin panel today, a future public API tomorrow) gets the same
 * behavior and the same audit trail for free. Auto-assignment itself is
 * delegated to {@see AssignmentService}; this class is what applies the
 * pick, appends the visible system note, and logs it.
 */
class TicketService
{
    public function __construct(protected AssignmentService $assignment) {}

    /**
     * Raise a new ticket on behalf of a requester, auto-assigning it to the
     * category's least-loaded agent (if the category has any).
     */
    public function create(
        User $requester,
        ?TicketCategory $category,
        string $subject,
        string $message,
        TicketPriority $priority,
        User $causer,
    ): Ticket {
        return DB::transaction(function () use ($requester, $category, $subject, $message, $priority, $causer): Ticket {
            $ticket = Ticket::create([
                'user_id' => $requester->id,
                'category_id' => $category?->id,
                'subject' => $subject,
                'status' => TicketStatus::Open,
                'priority' => $priority,
                'last_user_response_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $requester->id,
                'author_type' => TicketMessageAuthorType::User,
                'message' => $message,
            ]);

            $agent = $category ? $this->autoAssign($ticket, $category) : null;

            ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Created, $ticket, [
                'subject' => $ticket->subject,
                'category' => $category?->name,
                'priority' => $priority->value,
                'agent' => $agent?->name,
            ], causer: $causer);

            return $ticket->refresh();
        });
    }

    /**
     * Record a staff reply, storing any attached files under the ticket's own
     * folder ({@see storeAttachments()}). Moves an Open ticket to Pending
     * (waiting on the customer) — never silently overrides a Resolved/Closed
     * ticket; the caller should reopen it first via {@see changeStatus()}.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    public function reply(Ticket $ticket, User $staff, string $message, array $attachments = []): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $staff, $message, $attachments): TicketMessage {
            $ticketMessage = $ticket->messages()->create([
                'user_id' => $staff->id,
                'author_type' => TicketMessageAuthorType::Staff,
                'message' => $message,
                'attachments' => $this->storeAttachments($ticket, $attachments),
            ]);

            $ticket->update(['last_staff_response_at' => now()]);

            if ($ticket->status === TicketStatus::Open) {
                $ticket->update(['status' => TicketStatus::Pending]);
            }

            ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Replied, $ticket, [
                'attachments' => count($attachments),
            ], causer: $staff);

            return $ticketMessage;
        });
    }

    /**
     * Move the ticket to a new status. Sets/clears closed_at as the ticket
     * enters/leaves the Closed state.
     */
    public function changeStatus(Ticket $ticket, TicketStatus $status, User $causer): void
    {
        $before = $ticket->getOriginal();

        $ticket->update([
            'status' => $status,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
        ]);

        $changes = ActivityLogger::diff($ticket, $before);
        if ($changes !== []) {
            ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Updated, $ticket, $changes, causer: $causer);
        }
    }

    /**
     * Change a ticket's priority.
     */
    public function changePriority(Ticket $ticket, TicketPriority $priority, User $causer): void
    {
        $before = $ticket->getOriginal();

        $ticket->update(['priority' => $priority]);

        $changes = ActivityLogger::diff($ticket, $before);
        if ($changes !== []) {
            ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Updated, $ticket, $changes, causer: $causer);
        }
    }

    /**
     * Manually assign (or unassign, with $agent = null) a ticket — not
     * restricted to the category's agent pool, since an admin may need to
     * override the automatic pick. Appends a visible system note either way.
     */
    public function reassign(Ticket $ticket, ?User $agent, User $causer): void
    {
        $ticket->update(['assigned_to' => $agent?->id]);

        $ticket->messages()->create([
            'author_type' => TicketMessageAuthorType::System,
            'message' => $agent
                ? __('tickets.system.reassigned', ['agent' => $agent->name, 'causer' => $causer->name])
                : __('tickets.system.unassigned', ['causer' => $causer->name]),
        ]);

        ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Assigned, $ticket, [
            'agent' => $agent?->name,
        ], causer: $causer);
    }

    /**
     * Re-route the ticket to a different category. Re-runs auto-assignment
     * when the currently assigned agent (if any) isn't in the new category's
     * pool, so the ticket doesn't stay with an agent who no longer covers it.
     */
    public function changeCategory(Ticket $ticket, TicketCategory $category, User $causer): void
    {
        DB::transaction(function () use ($ticket, $category, $causer): void {
            $before = $ticket->getOriginal();

            $ticket->update(['category_id' => $category->id]);

            $currentAgentEligible = $ticket->assigned_to !== null
                && $category->agents()->whereKey($ticket->assigned_to)->exists();

            if (! $currentAgentEligible) {
                $this->autoAssign($ticket, $category);
            }

            $changes = ActivityLogger::diff($ticket, $before);
            if ($changes !== []) {
                ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Updated, $ticket, $changes, causer: $causer);
            }
        });
    }

    /**
     * Apply {@see AssignmentService::pickAgent()} to the ticket and, when
     * an agent is found, leave a system note in the thread so the load-balancing
     * decision is visible in the conversation, not just the audit log.
     */
    protected function autoAssign(Ticket $ticket, TicketCategory $category): ?User
    {
        $agent = $this->assignment->pickAgent($category);

        if (! $agent) {
            return null;
        }

        $ticket->update(['assigned_to' => $agent->id]);

        $ticket->messages()->create([
            'author_type' => TicketMessageAuthorType::System,
            'message' => __('tickets.system.auto_assigned', ['agent' => $agent->name]),
        ]);

        return $agent;
    }

    /**
     * Stores every attachment under the ticket's single folder
     * ({@see Ticket::attachmentsPath()}) — never a per-message subfolder, so
     * deleting the ticket (its `deleting` model event) can remove every
     * message's attachments in one `deleteDirectory()` call. No disk is
     * named here — `store()` writes to the app's default disk, same as
     * {@see User::avatarUrl()}, so this follows whatever `FILESYSTEM_DISK`
     * is configured without this class needing to know or care.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{name: string, size: int, mime: string, path: string}>
     */
    protected function storeAttachments(Ticket $ticket, array $files): array
    {
        return collect($files)
            ->map(fn (UploadedFile $file): array => [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'path' => $file->store($ticket->attachmentsPath()),
            ])
            ->all();
    }
}
