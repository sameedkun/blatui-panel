<?php

use App\Jobs\AutoCloseInactiveTickets;
use App\Jobs\PruneExpiredBlockedIps;
use App\Jobs\PruneRevokedDevices;
use App\Jobs\PurgeClosedTickets;
use App\Jobs\PurgeExpiredAccounts;
use App\Jobs\SyncSubscriptionStatuses;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PurgeExpiredAccounts)->hourly()->name('account-deletion-purge')->withoutOverlapping();

// Only local subscriptions can have their status inferred from dates alone.
// Provider selection belongs to the job so the scheduler remains configuration-free.
Schedule::job(new SyncSubscriptionStatuses)
    ->hourly()
    ->name('subscription-status-sync')
    ->withoutOverlapping();

// Day-granularity thresholds, so daily is frequent enough for both sweeps.
Schedule::job(new AutoCloseInactiveTickets)
    ->daily()
    ->name('ticket-auto-close')
    ->withoutOverlapping();

Schedule::job(new PurgeClosedTickets)
    ->daily()
    ->name('ticket-purge-closed')
    ->withoutOverlapping();

// Prune activity-log entries older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->weekly()->name('activitylog-clean')->withoutOverlapping();

Schedule::job(new PruneExpiredBlockedIps)->daily()->name('blocked-ips-prune-expired')->withoutOverlapping();

Schedule::job(new PruneRevokedDevices)
    ->monthly()
    ->name('devices-prune-revoked')
    ->withoutOverlapping();
