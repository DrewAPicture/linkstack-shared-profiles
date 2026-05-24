<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

abstract class ServiceProvider extends BaseServiceProvider
{
    abstract public function getProviderName(): string;

    /**
     * Register a CSRF-exempt POST route for platform interaction endpoints.
     *
     * Intended for endpoints that receive signed payloads without a prior page
     * load (e.g. Telegram initData, Discord interaction webhooks). The platform
     * signature on the payload serves as the authentication proof in place of
     * the CSRF token.
     *
     * @param  array<int, string>|string|\Closure  $action
     */
    protected function registerInteractionRoute(string $path, array|string|\Closure $action, string $name): void
    {
        Route::post($path, $action)
            ->middleware('web')
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->name($name);
    }
}
