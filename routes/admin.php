<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Management\Users\Form as UsersForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->name('admin.')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // ── Users ─────────────────────────────────────────────────────────────
    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/users/create', UsersForm::class)->name('users.create');
    Route::get('/users/{user}/edit', UsersForm::class)->name('users.edit');
});
