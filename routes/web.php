<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Hello World';
});

Route::post('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'tr'], true), 404);

    return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.switch');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
