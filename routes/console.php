<?php

use App\Jobs\PurgeExpiredAccounts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PurgeExpiredAccounts)->hourly()->name('account-deletion-purge')->withoutOverlapping();
