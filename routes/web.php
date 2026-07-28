<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'ur'], true), 404);

    return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.switch');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
