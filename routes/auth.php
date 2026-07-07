<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Logout;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->name('login')->middleware('guest');
Route::get('/logout', Logout::class)->name('logout')->middleware('auth');
