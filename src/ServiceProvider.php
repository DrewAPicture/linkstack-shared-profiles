<?php

namespace WerdsWords\LinkStack\SharedProfiles;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Contracts\HasApiTokenContract;
use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
use WerdsWords\LinkStack\SharedProfiles\Providers\Contracts\NotifierContract;
use WerdsWords\LinkStack\SharedProfiles\Providers\Listeners\NotifyProvidersOfPendingLink;

class ServiceProvider extends BaseServiceProvider
{
    /** @var list<NotifierContract> */
    private static array $notifiers = [];

    public static function registerNotifier(NotifierContract $notifier): void
    {
        self::$notifiers[] = $notifier;
    }

    /** @return list<NotifierContract> */
    public static function registeredNotifiers(): array
    {
        return self::$notifiers;
    }

    public static function flushNotifiers(): void
    {
        self::$notifiers = [];
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/linkstack-shared-profiles.php', 'linkstack-shared-profiles'
        );

        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');

        /** @var class-string $model */
        $model = $config->get('auth.providers.users.model', '');

        if (! is_a($model, HasApiTokenContract::class, true)) {
            $config->set('auth.providers.users.model', Models\User::class);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'linkstack-shared-profiles');

        $this->publishes([
            __DIR__.'/../config/linkstack-shared-profiles.php' => config_path('linkstack-shared-profiles.php'),
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'linkstack-shared-profiles');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/linkstack-shared-profiles'),
        ], 'linkstack-shared-profiles-views');

        // UserController::littlelink() uses DB::table() not Eloquent, so a global
        // scope cannot intercept it. This view composer fires just before the Blade
        // template renders and strips non-published links from the $links collection.
        View::composer('linkstack.linkstack', function ($view) {
            /** @var array<int, \stdClass> $raw */
            $raw = $view->getData()['links'] ?? [];
            $links = collect($raw);
            $view->with('links', $links->filter(
                fn ($link) => ! isset($link->status) || $link->status === 'published'
            ));
        });

        Event::listen(PendingLinkSubmitted::class, NotifyProvidersOfPendingLink::class);
    }
}
