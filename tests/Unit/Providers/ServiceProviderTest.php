<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use WerdsWords\LinkStack\SharedProfiles\Providers\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider as CoreServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(ServiceProvider::class)]
final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    // -------------------------------------------------------------------------
    // getProviderName()
    // -------------------------------------------------------------------------

    #[CoversMethod(ServiceProvider::class, 'getProviderName')]
    public function testGetProviderNameReturnsConfiguredName(): void
    {
        $provider = new class($this->app) extends ServiceProvider
        {
            public function getProviderName(): string
            {
                return 'discord';
            }
        };

        $this->assertSame('discord', $provider->getProviderName());
    }

    // -------------------------------------------------------------------------
    // registerInteractionRoute()
    // -------------------------------------------------------------------------

    #[CoversMethod(ServiceProvider::class, 'registerInteractionRoute')]
    public function testRegisterInteractionRouteRegistersNamedPostRoute(): void
    {
        $provider = new class($this->app) extends ServiceProvider
        {
            public function getProviderName(): string
            {
                return 'test';
            }

            public function boot(): void
            {
                $this->registerInteractionRoute(
                    '/test/interact',
                    fn () => response()->json(['ok' => true]),
                    'test.interact'
                );
            }
        };

        $provider->boot();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $route = $this->app['router']->getRoutes()->getByName('test.interact');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    #[CoversMethod(ServiceProvider::class, 'registerInteractionRoute')]
    public function testRegisterInteractionRouteExcludesCsrfMiddleware(): void
    {
        $provider = new class($this->app) extends ServiceProvider
        {
            public function getProviderName(): string
            {
                return 'test';
            }

            public function boot(): void
            {
                $this->registerInteractionRoute(
                    '/test/interact',
                    fn () => response()->json(['ok' => true]),
                    'test.interact'
                );
            }
        };

        $provider->boot();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $route = $this->app['router']->getRoutes()->getByName('test.interact');

        $this->assertNotNull($route);
        $this->assertContains(VerifyCsrfToken::class, $route->excludedMiddleware());
    }

    #[CoversMethod(ServiceProvider::class, 'registerInteractionRoute')]
    public function testRegisterInteractionRouteRespondsToPost(): void
    {
        $provider = new class($this->app) extends ServiceProvider
        {
            public function getProviderName(): string
            {
                return 'test';
            }

            public function boot(): void
            {
                $this->registerInteractionRoute(
                    '/test/interact',
                    fn () => response()->json(['ok' => true]),
                    'test.interact'
                );
            }
        };

        $provider->boot();

        $this->postJson('/test/interact')->assertOk()->assertJson(['ok' => true]);
    }
}
