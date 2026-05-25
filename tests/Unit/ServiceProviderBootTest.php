<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use RuntimeException;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(ServiceProvider::class)]
#[CoversMethod(ServiceProvider::class, 'boot')]
final class ServiceProviderBootTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function testBootDoesNotThrowWhenModelImplementsContract(): void
    {
        $provider = new ServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function testBootThrowsWhenModelDoesNotImplementContract(): void
    {
        $nonConforming = new class {};
        $this->app['config']->set('auth.providers.users.model', $nonConforming::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement ApiTokenableContract/');

        $provider = new ServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }
}
