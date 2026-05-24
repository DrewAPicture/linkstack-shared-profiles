<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\SocialiteServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Services\TelegramNotificationService;

#[CoversClass(ServiceProvider::class)]
final class PendingLinkSubmittedListenerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('services.telegram.client_id', 'test-bot-id');
        $app['config']->set('services.telegram.client_secret', 'test-bot-token');
        $app['config']->set('services.telegram.redirect', 'https://example.com/callback');

        $app['config']->set('linkstack-shared-profiles.bot_token', 'test-token');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('users');
        });
    }

    public function testListenerCallsNotifyModeratorsWhenEventDispatched(): void
    {
        $mock = Mockery::mock(TelegramNotificationService::class);
        $mock->shouldReceive('notifyModerators')
            ->once()
            ->with(1, 42, 'https://example.com', 'My Link');
        $this->app->instance(TelegramNotificationService::class, $mock);

        event(new PendingLinkSubmitted(1, 42, 'https://example.com', 'My Link'));
    }

    public function testListenerIsNotCalledWhenEventIsNotDispatched(): void
    {
        $mock = Mockery::mock(TelegramNotificationService::class);
        $mock->shouldReceive('notifyModerators')->never();
        $this->app->instance(TelegramNotificationService::class, $mock);

        // No event fired — verify the mock expectation holds.
        $this->assertTrue(true);
    }
}
