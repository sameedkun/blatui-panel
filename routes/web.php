<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

Route::post('/locale/{locale}', function (string $locale) {
    abort_unless(array_key_exists($locale, config('panel.locales')), 404);

    return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.switch');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
