<?php

use App\Jobs\Account\PurgeExpiredAccounts;
use App\Jobs\ApiLog\AggregateApiRequestStats;
use App\Jobs\ApiLog\FlushApiRequestLogs;
use App\Jobs\ApiLog\PruneApiRequestLogs;
use App\Jobs\Auth\PruneExpiredBlockedIps;
use App\Jobs\Device\PruneRevokedDevices;
use App\Jobs\Subscription\SyncSubscriptionStatuses;
use App\Jobs\Ticket\CloseInactiveTickets;
use App\Jobs\Ticket\PurgeClosedTickets;
use Illuminate\Support\Facades\Schedule;

// Every minute
// Drains the Redis API-log buffer in bulk; a no-op under the sync buffer.
// Not sub-minute on purpose — that would keep every schedule:run alive for the whole minute.
Schedule::job(new FlushApiRequestLogs)
    ->everyMinute()
    ->name('api-logs-flush')
    ->withoutOverlapping();

// Hourly
// At :05, so the previous hour's buffered logs have been flushed first.
Schedule::job(new AggregateApiRequestStats)
    ->hourlyAt(5)
    ->name('api-logs-aggregate')
    ->withoutOverlapping();

Schedule::job(new PurgeExpiredAccounts)
    ->hourly()
    ->name('account-deletion-purge')
    ->withoutOverlapping();

// Only local subscriptions can have their status inferred from dates alone.
Schedule::job(new SyncSubscriptionStatuses)
    ->hourly()
    ->name('subscription-status-sync')
    ->withoutOverlapping();

// Daily
// Day-granularity thresholds, so daily is frequent enough for both sweeps.
Schedule::job(new CloseInactiveTickets)
    ->daily()
    ->name('ticket-auto-close')
    ->withoutOverlapping();

Schedule::job(new PurgeClosedTickets)
    ->daily()
    ->name('ticket-purge-closed')
    ->withoutOverlapping();

Schedule::job(new PruneApiRequestLogs)
    ->dailyAt('03:30')
    ->name('api-logs-prune')
    ->withoutOverlapping();

Schedule::job(new PruneExpiredBlockedIps)
    ->daily()
    ->name('blocked-ips-prune-expired')
    ->withoutOverlapping();

// Weekly
// Prune activity-log entries older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')
    ->weekly()
    ->name('activitylog-clean')
    ->withoutOverlapping();

// Monthly
Schedule::job(new PruneRevokedDevices)
    ->monthly()
    ->name('devices-prune-revoked')
    ->withoutOverlapping();
