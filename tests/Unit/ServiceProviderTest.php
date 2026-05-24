<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit;

use Generator;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WerdsWords\LinkStack\SharedProfiles\Providers\Contracts\NotifierContract;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

#[CoversClass(ServiceProvider::class)]
#[CoversMethod(ServiceProvider::class, 'register')]
final class ServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceProvider::flushNotifiers();
    }

    protected function tearDown(): void
    {
        ServiceProvider::flushNotifiers();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loadedConfig(): Repository
    {
        $app = new Container;
        $config = new Repository;
        $app->instance('config', $config);

        (new ServiceProvider($app))->register();

        return $config;
    }

    // -------------------------------------------------------------------------
    // Class structure
    // -------------------------------------------------------------------------

    public function testExtendsBaseServiceProvider(): void
    {
        $this->assertTrue(is_subclass_of(ServiceProvider::class, BaseServiceProvider::class));
    }

    // -------------------------------------------------------------------------
    // Config file structure
    // -------------------------------------------------------------------------

    #[DataProvider('provideConfigKeys')]
    public function testConfigFileDefinesKey(string $key): void
    {
        $config = require __DIR__.'/../../config/linkstack-shared-profiles.php';

        $this->assertArrayHasKey($key, $config);
    }

    public static function provideConfigKeys(): Generator
    {
        yield 'auto_approve' => ['auto_approve'];
    }

    // -------------------------------------------------------------------------
    // Config defaults
    // -------------------------------------------------------------------------

    #[DataProvider('provideConfigDefaults')]
    public function testConfigFileDefault(string $key, mixed $expected): void
    {
        $config = require __DIR__.'/../../config/linkstack-shared-profiles.php';

        $this->assertSame($expected, $config[$key]);
    }

    public static function provideConfigDefaults(): Generator
    {
        yield 'auto_approve defaults to false' => ['auto_approve', false];
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function testRegisterMergesConfigUnderExpectedKey(): void
    {
        $config = $this->loadedConfig();

        $this->assertTrue($config->has('linkstack-shared-profiles'));
    }

    #[DataProvider('provideConfigKeys')]
    public function testRegisterMakesAllConfigKeysAvailable(string $key): void
    {
        $config = $this->loadedConfig();

        $this->assertTrue($config->has("linkstack-shared-profiles.{$key}"));
    }

    #[DataProvider('provideConfigDefaults')]
    public function testRegisterPreservesConfigDefaults(string $key, mixed $expected): void
    {
        $config = $this->loadedConfig();

        $this->assertSame($expected, $config->get("linkstack-shared-profiles.{$key}"));
    }

    // -------------------------------------------------------------------------
    // registerNotifier() / registeredNotifiers() / flushNotifiers()
    // -------------------------------------------------------------------------

    #[CoversMethod(ServiceProvider::class, 'registeredNotifiers')]
    public function testRegisteredNotifiersIsEmptyByDefault(): void
    {
        $this->assertSame([], ServiceProvider::registeredNotifiers());
    }

    #[CoversMethod(ServiceProvider::class, 'registerNotifier')]
    #[CoversMethod(ServiceProvider::class, 'registeredNotifiers')]
    public function testRegisterNotifierAddsToRegistry(): void
    {
        $stub = new class implements NotifierContract
        {
            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void {}
        };

        ServiceProvider::registerNotifier($stub);

        $this->assertCount(1, ServiceProvider::registeredNotifiers());
        $this->assertSame($stub, ServiceProvider::registeredNotifiers()[0]);
    }

    #[CoversMethod(ServiceProvider::class, 'registerNotifier')]
    #[CoversMethod(ServiceProvider::class, 'registeredNotifiers')]
    public function testMultipleNotifiersCanBeRegistered(): void
    {
        $stubA = new class implements NotifierContract
        {
            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void {}
        };
        $stubB = new class implements NotifierContract
        {
            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void {}
        };

        ServiceProvider::registerNotifier($stubA);
        ServiceProvider::registerNotifier($stubB);

        $this->assertCount(2, ServiceProvider::registeredNotifiers());
    }

    #[CoversMethod(ServiceProvider::class, 'flushNotifiers')]
    public function testFlushNotifiersClearsRegistry(): void
    {
        $stub = new class implements NotifierContract
        {
            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void {}
        };

        ServiceProvider::registerNotifier($stub);
        ServiceProvider::flushNotifiers();

        $this->assertSame([], ServiceProvider::registeredNotifiers());
    }
}
