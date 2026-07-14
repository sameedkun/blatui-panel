<?php

use App\Livewire\Admin\Account\Index as AccountIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Management\Guests\Index as GuestsIndex;
use App\Livewire\Admin\Management\Guests\Show as GuestsShow;
use App\Livewire\Admin\Management\Users\Form as UsersForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Users\Show as UsersShow;
use App\Livewire\Admin\System\ActivityLogs\Index as ActivityLogsIndex;
use App\Livewire\Admin\System\Roles\Form as RolesForm;
use App\Livewire\Admin\System\Roles\Index as RolesIndex;
use App\Livewire\Admin\System\Staff\Form as StaffForm;
use App\Livewire\Admin\System\Staff\Index as StaffIndex;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

// AuthenticateSession is what makes "log out other devices" on the account page
// actually invalidate other panel sessions: it stamps the password hash into the
// session and logs a session out when that hash no longer matches — so rotating
// the hash (Auth::logoutOtherDevices) kills every other session on its next request.
Route::middleware(['auth', 'panel', AuthenticateSession::class])->name('admin.')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // ── Users ─────────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->middleware('permission:users.view')->group(function () {
        Route::get('/', UsersIndex::class)->name('index');
        Route::get('/create', UsersForm::class)->name('create')->middleware('permission:users.create');
        Route::get('/{user}/edit', UsersForm::class)->name('edit')->middleware('permission:users.edit');
        Route::get('/{user}', UsersShow::class)->name('show')->middleware('permission:users.manage')->withTrashed();
    });

    // ── Guests ─────────────────────────────────────────────────────────────
    Route::prefix('guests')->name('guests.')->middleware('permission:guests.view')->group(function () {
        Route::get('/', GuestsIndex::class)->name('index');
        Route::get('/{user}', GuestsShow::class)->name('show')->middleware('permission:guests.manage')->withTrashed();
    });

    // ── Staff ─────────────────────────────────────────────────────────────
    Route::prefix('staff')->name('staff.')->middleware('permission:staff.view')->group(function () {
        Route::get('/', StaffIndex::class)->name('index');
        Route::get('/create', StaffForm::class)->name('create')->middleware('permission:staff.create');
        Route::get('/{user}/edit', StaffForm::class)->name('edit')->middleware('permission:staff.edit');
    });

    // ── Roles ─────────────────────────────────────────────────────────────
    Route::prefix('roles')->name('roles.')->middleware('permission:roles.view')->group(function () {
        Route::get('/', RolesIndex::class)->name('index');
        Route::get('/create', RolesForm::class)->name('create')->middleware('permission:roles.create');
        Route::get('/{role}/edit', RolesForm::class)->name('edit')->middleware('permission:roles.edit');
    });

    // ── Activity Logs (read-only audit trail) ──────────────────────────────
    Route::prefix('activity-logs')->name('activity-logs.')->middleware('permission:activity_logs.view')->group(function () {
        Route::get('/', ActivityLogsIndex::class)->name('index');
    });

    // ── My Account (self-service; every staff member, no extra permission) ──
    Route::get('/account', AccountIndex::class)->name('account');
});
