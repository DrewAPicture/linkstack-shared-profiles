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
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

#[CoversClass(ServiceProvider::class)]
#[CoversMethod(ServiceProvider::class, 'register')]
final class ServiceProviderTest extends TestCase
{
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
}
