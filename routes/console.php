<?php

use App\Jobs\PurgeExpiredAccounts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PurgeExpiredAccounts)->hourly()->name('account-deletion-purge')->withoutOverlapping();

// Prune activity-log entries older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->weekly()->name('activitylog-clean')->withoutOverlapping();
