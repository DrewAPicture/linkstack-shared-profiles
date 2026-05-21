<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use WerdsWords\LinkStack\SharedProfiles\Http\Controllers\TelegramAuthController;

// Approach A: browser-based Telegram Login Widget
Route::middleware('web')->group(function () {
    Route::get('/telegram-auth', [TelegramAuthController::class, 'redirect'])
        ->name('linkstack-shared-profiles.telegram.redirect');

    Route::get('/telegram-auth/callback', [TelegramAuthController::class, 'callback'])
        ->name('linkstack-shared-profiles.telegram.callback');
});

// Approach B: Telegram Mini App initData — needs sessions but no CSRF token
// (HMAC-signed initData is the security; Mini Apps cannot send a CSRF token)
Route::post('/telegram-login', [TelegramAuthController::class, 'initDataLogin'])
    ->middleware('web')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('linkstack-shared-profiles.telegram.initdata');
