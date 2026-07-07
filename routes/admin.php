<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Management\Guests\Index as GuestsIndex;
use App\Livewire\Admin\Management\Users\Form as UsersForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\System\Roles\Form as RolesForm;
use App\Livewire\Admin\System\Roles\Index as RolesIndex;
use App\Livewire\Admin\System\Staff\Form as StaffForm;
use App\Livewire\Admin\System\Staff\Index as StaffIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'panel'])->name('admin.')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // ── Users ─────────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->middleware('permission:users.view')->group(function () {
        Route::get('/', UsersIndex::class)->name('index');
        Route::get('/create', UsersForm::class)->name('create')->middleware('permission:users.create');
        Route::get('/{user}/edit', UsersForm::class)->name('edit')->middleware('permission:users.edit');
    });

    // ── Guests ─────────────────────────────────────────────────────────────
    Route::prefix('guests')->name('guests.')->middleware('permission:guests.view')->group(function () {
        Route::get('/', GuestsIndex::class)->name('index');
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
});
