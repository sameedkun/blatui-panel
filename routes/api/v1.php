<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Support\Facades\Route;

// Registered under the api/v1 prefix by config/apiroute.php's "v1" version
Route::middleware('guest')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup');

    // throttle:10,1 is a coarse per-IP backstop; the real brute-force defense is
    // AuthController::login()'s own per-email+IP RateLimiter lockout.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/resend', [VerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.resend');

    Route::post('/password/forgot', [PasswordController::class, 'forgot'])
        ->middleware('throttle:6,1')
        ->name('password.forgot');

    Route::post('/password/reset', [PasswordController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.reset');
});

Route::middleware(['auth:sanctum', 'device.valid'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/me', [ProfileController::class, 'show'])->name('me');
    Route::put('/me', [ProfileController::class, 'update'])->name('me.update');
    // throttle:5,1 guards against brute-forcing current_password with a stolen/leaked token.
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:5,1')
        ->name('me.password');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('/devices/{ulid}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});
