<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'device.valid'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::delete('/devices/{ulid}', [DeviceController::class, 'destroy']);
});
