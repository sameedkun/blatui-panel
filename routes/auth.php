<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Logout;
use App\Livewire\Auth\PasswordReset;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->name('login')->middleware('guest');
Route::get('/logout', Logout::class)->name('logout')->middleware('auth');

Route::get('/verify-email/{id}/{hash}', VerifyEmail::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/reset-password/{token}', PasswordReset::class)
    ->middleware('guest')
    ->name('password.reset');
