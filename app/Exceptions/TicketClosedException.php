<?php

namespace App\Exceptions;

use App\Enum\TicketStatus;
use App\Services\Ticket\TicketService;
use RuntimeException;

/**
 * Thrown by {@see TicketService::replyAsUser()} when the ticket is in a
 * terminal state ({@see TicketStatus::isTerminal()}) — a customer
 * can't revive a resolved/closed ticket by replying to it; staff must reopen
 * it first via {@see TicketService::changeStatus()}.
 */
class TicketClosedException extends RuntimeException {}
