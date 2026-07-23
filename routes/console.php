<?php

use App\Enum\PaymentProvider;
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

// Prune activity-log entries older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->weekly()->name('activitylog-clean')->withoutOverlapping();
