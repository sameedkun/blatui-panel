<?php

use App\Http\Controllers\Webhooks\AppStoreWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider webhooks
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php outside both the `web` group (no session,
| cookies or CSRF — providers can't send a token) and the versioned `api`
| surface (these aren't client endpoints). Each controller authenticates the
| payload itself (provider signature), optionally behind a signed URL.
|
*/

Route::post('webhooks/appstore', [AppStoreWebhookController::class, 'handle'])->name('webhooks.appstore');
