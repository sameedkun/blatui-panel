<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Management\Users\Form as UsersForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Guests\Index as GuestsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','panel'])->name('admin.')->group(function () {
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
});
