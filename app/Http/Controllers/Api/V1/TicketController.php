<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\TicketPriority;
use App\Exceptions\TicketClosedException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Ticket\ReplyTicketRequest;
use App\Http\Requests\V1\Ticket\StoreTicketRequest;
use App\Http\Resources\V1\TicketCategoryResource;
use App\Http\Resources\V1\TicketResource;
use App\Models\TicketCategory;
use App\Services\Ticket\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-service support tickets — a user raising and following up on their
 * own tickets. Every lookup here is scoped to $request->user()->tickets(),
 * same convention as DeviceController/SubscriptionController: a ticket id
 * belonging to another account 404s via findOrFail(), never a manual 403.
 * Status/priority/category/agent changes stay admin-only, via TicketService
 * called from the panel.
 */
class TicketController extends ApiController
{
    /** Categories a ticket can be raised under — inactive ones stay admin-only. */
    public function categories(): JsonResponse
    {
        return $this->success(
            TicketCategoryResource::collection(
                TicketCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            ),
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            TicketResource::collection(
                $request->user()->tickets()->with('category')->latest()->get(),
            ),
        );
    }

    public function store(StoreTicketRequest $request, TicketService $tickets): JsonResponse
    {
        $user = $request->user();
        $category = $request->validated('category_id')
            ? TicketCategory::findOrFail($request->validated('category_id'))
            : null;

        $ticket = $tickets->create(
            requester: $user,
            category: $category,
            subject: $request->validated('subject'),
            message: $request->validated('message'),
            priority: TicketPriority::Medium,
            causer: $user,
            attachments: $request->file('attachments', []),
        );

        return $this->created(new TicketResource($ticket->load(['category', 'messages'])));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $request->user()->tickets()->with(['category', 'messages'])->findOrFail($id);

        return $this->success(new TicketResource($ticket));
    }

    public function reply(ReplyTicketRequest $request, int $id, TicketService $tickets): JsonResponse
    {
        $user = $request->user();
        $ticket = $user->tickets()->findOrFail($id);

        try {
            $tickets->replyAsUser($ticket, $user, $request->validated('message'), $request->file('attachments', []));
        } catch (TicketClosedException $e) {
            return $this->error($e->getMessage(), Response::HTTP_CONFLICT, ['code' => 'TICKET_CLOSED']);
        }

        return $this->success(new TicketResource($ticket->fresh(['category', 'messages'])), 'Reply sent.');
    }
}
