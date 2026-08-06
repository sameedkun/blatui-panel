<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Registered under the api/v1 prefix by config/apiroute.php's "v1" version
Route::middleware('guest')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup');

    // throttle:10,1 is a coarse per-IP backstop; the real brute-force defense is
    // AuthController::login()'s own per-email+IP RateLimiter lockout.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');
});

Route::middleware(['auth:sanctum', 'device.valid'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/user', fn (Request $request) => $request->user())->name('user');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('/devices/{ulid}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});
