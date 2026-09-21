<?php

use App\Http\Controllers\Auth\AuthenticateUsingPasskeyController;
use App\Http\Controllers\Auth\PasskeyAuthenticationOptionsController;
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

// Passkey login endpoints (options + verify) used by the <x-authenticate-passkey />
// component on the login page. Route names/paths match what that vendor component's
// JS expects (route('passkeys.authentication_options') / route('passkeys.login')) —
// see the controllers themselves for why they replace Route::passkeys()'s defaults.
// Throttled like every other unauthenticated auth endpoint in this app; staff/ban
// re-validation happens in App\Services\Auth\FindPasskeyToAuthenticateAction.
Route::middleware(['guest', 'throttle:10,1'])->prefix('passkeys')->group(function () {
    Route::get('authentication-options', PasskeyAuthenticationOptionsController::class)
        ->name('passkeys.authentication_options');

    Route::post('authenticate', AuthenticateUsingPasskeyController::class)
        ->name('passkeys.login');
});
