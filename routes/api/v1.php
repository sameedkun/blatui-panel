<?php

use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Registered under the api/v1 prefix by config/apiroute.php's "v1" version
// definition — see ApiRouteManager::registerRoutes(). No middleware is
// applied at that level, so authenticated and guest routes can share this
// file: authenticated endpoints go inside the group below, guest ones
// (login, signup, ...) go outside it.

Route::middleware(['auth:sanctum', 'device.valid'])->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user())->name('user');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('/devices/{ulid}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});

// Guest routes go here, outside the group above, e.g.:
// Route::post('/login', [AuthController::class, 'login'])->name('login');
