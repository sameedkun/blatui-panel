<?php

use App\Enum\PaymentProvider;
use App\Jobs\AutoCloseInactiveTickets;
use App\Jobs\PurgeClosedTickets;
use App\Jobs\PurgeExpiredAccounts;
use App\Jobs\SyncSubscriptionStatuses;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PurgeExpiredAccounts)->hourly()->name('account-deletion-purge')->withoutOverlapping();

// Only `local` subscriptions can have their status inferred from dates alone (no real
// payment gateway to confirm a renewal charge yet) — once a real provider integration
// exists, just add it here: new SyncSubscriptionStatuses([PaymentProvider::Local, PaymentProvider::Stripe]).
Schedule::job(new SyncSubscriptionStatuses([PaymentProvider::Local]))
    ->hourly()
    ->name('subscription-status-sync')
    ->withoutOverlapping();

// Day-granularity thresholds, so daily is frequent enough for both sweeps.
Schedule::job(new AutoCloseInactiveTickets(config('panel.ticket_auto_close_inactive_days')))
    ->daily()
    ->name('ticket-auto-close')
    ->withoutOverlapping();

Schedule::job(new PurgeClosedTickets(config('panel.ticket_purge_closed_after_months')))
    ->daily()
    ->name('ticket-purge-closed')
    ->withoutOverlapping();

// Prune activity-log entries older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->weekly()->name('activitylog-clean')->withoutOverlapping();
