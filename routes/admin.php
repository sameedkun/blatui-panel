<?php

use App\Livewire\Admin\Account\Index as AccountIndex;
use App\Livewire\Admin\Administration\ActivityLogs\Index as ActivityLogsIndex;
use App\Livewire\Admin\Administration\Roles\Form as RolesForm;
use App\Livewire\Admin\Administration\Roles\Index as RolesIndex;
use App\Livewire\Admin\Administration\Staff\Form as StaffForm;
use App\Livewire\Admin\Administration\Staff\Index as StaffIndex;
use App\Livewire\Admin\Application\Feedback\Index as FeedbackIndex;
use App\Livewire\Admin\Application\Feedback\Show as FeedbackShow;
use App\Livewire\Admin\Application\Language\Form as LanguageForm;
use App\Livewire\Admin\Application\Language\Index as LanguageIndex;
use App\Livewire\Admin\Application\Notification\Form as NotificationForm;
use App\Livewire\Admin\Application\Notification\Index as NotificationIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Management\Guests\Index as GuestsIndex;
use App\Livewire\Admin\Management\Guests\Show as GuestsShow;
use App\Livewire\Admin\Management\Plans\Form as PlansForm;
use App\Livewire\Admin\Management\Plans\Index as PlansIndex;
use App\Livewire\Admin\Management\Plans\Show as PlansShow;
use App\Livewire\Admin\Management\Subscriptions\Index as SubscriptionsIndex;
use App\Livewire\Admin\Management\Subscriptions\Show as SubscriptionsShow;
use App\Livewire\Admin\Management\Users\Form as UsersForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Users\Show as UsersShow;
use App\Livewire\Admin\Settings\General;
use App\Livewire\Admin\Settings\Mail;
use App\Livewire\Admin\Settings\Policies;
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

    // ── Plans ─────────────────────────────────────────────────────────────
    Route::prefix('plans')->name('plans.')->middleware('permission:plans.view')->group(function () {
        Route::get('/', PlansIndex::class)->name('index');
        Route::get('/create', PlansForm::class)->name('create')->middleware('permission:plans.create');
        Route::get('/{plan}/edit', PlansForm::class)->name('edit')->middleware('permission:plans.edit');
        Route::get('/{plan}', PlansShow::class)->name('show')->middleware('permission:plans.manage');
    });

    // ── Subscriptions ─────────────────────────────────────────────────────
    Route::prefix('subscriptions')->name('subscriptions.')->middleware('permission:subscriptions.view')->group(function () {
        Route::get('/', SubscriptionsIndex::class)->name('index');
        Route::get('/{subscription}', SubscriptionsShow::class)->name('show')->middleware('permission:subscriptions.manage');
    });

    // ── Languages ─────────────────────────────────────────────────────────
    Route::prefix('languages')->name('languages.')->middleware('permission:languages.view')->group(function () {
        Route::get('/', LanguageIndex::class)->name('index');
        Route::get('/create', LanguageForm::class)->name('create')->middleware('permission:languages.create');
        Route::get('/{language}/edit', LanguageForm::class)->name('edit')->middleware('permission:languages.edit');
    });

    // ── Feedback ──────────────────────────────────────────────────────────
    Route::prefix('feedback')->name('feedback.')->middleware('permission:feedback.view')->group(function () {
        Route::get('/', FeedbackIndex::class)->name('index');
        Route::get('/{feedback}', FeedbackShow::class)->name('show')->middleware('permission:feedback.manage');
    });

    // ── Notifications ─────────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->middleware('permission:notifications.view')->group(function () {
        Route::get('/', NotificationIndex::class)->name('index');
        Route::get('/create', NotificationForm::class)->name('create')->middleware('permission:notifications.create');
        Route::get('/{notification}/edit', NotificationForm::class)->name('edit')->middleware('permission:notifications.edit');
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

    // ── Settings ──────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', function () {
            $user = request()->user();
            foreach (array_keys(config('panel.modules.settings.children', [])) as $child) {
                if ($user->can("settings.{$child}.view")) {
                    return redirect()->route("admin.settings.{$child}");
                }
            }
            abort(403);
        })->name('index');

        Route::get('/general', General::class)->name('general')->middleware('permission:settings.general.view');
        Route::get('/mail', Mail::class)->name('mail')->middleware('permission:settings.mail.view');
        Route::get('/policies', Policies::class)->name('policies')->middleware('permission:settings.policies.view');
    });

    // ── My Account (self-service; every staff member, no extra permission) ──
    Route::get('/account', AccountIndex::class)->name('account');
});
