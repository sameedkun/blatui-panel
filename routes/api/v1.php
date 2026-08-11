<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TicketController;
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

    Route::prefix('me')->name('me.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('password');
    });

    Route::prefix('devices')->name('devices.')->group(function () {
        Route::get('/', [DeviceController::class, 'index'])->name('index');
        Route::delete('/others', [DeviceController::class, 'revokeAllExceptCurrent'])->name('revoke-others');
        Route::delete('/{ulid}', [DeviceController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'current'])->name('current');
        Route::get('/history', [SubscriptionController::class, 'history'])->name('history');
    });

    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/categories', [TicketController::class, 'categories'])->name('categories.index');
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [TicketController::class, 'reply'])->name('reply');
    });
});
