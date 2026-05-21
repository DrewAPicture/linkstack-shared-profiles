<?php

use Illuminate\Support\Facades\Route;
use WerdsWords\LinkStack\SharedProfiles\Http\Controllers\ApiLinkController;

Route::post('/api/links', [ApiLinkController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('linkstack-shared-profiles.api.links');
