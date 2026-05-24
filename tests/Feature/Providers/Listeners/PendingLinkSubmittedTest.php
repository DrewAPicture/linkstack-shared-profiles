<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature\Providers\Listeners;

use Orchestra\Testbench\TestCase;
use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
use WerdsWords\LinkStack\SharedProfiles\Providers\Contracts\NotifierContract;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

final class PendingLinkSubmittedTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function tearDown(): void
    {
        ServiceProvider::flushNotifiers();
        parent::tearDown();
    }

    public function testFiringEventInvokesRegisteredNotifiers(): void
    {
        $called = false;

        $stub = new class($called) implements NotifierContract
        {
            public function __construct(private bool &$called) {}

            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void
            {
                $this->called = true;
            }
        };

        ServiceProvider::registerNotifier($stub);

        event(new PendingLinkSubmitted(1, 2, 'https://example.com', 'Title'));

        $this->assertTrue($called);
    }

    public function testFiringEventWithNoNotifiersRegisteredDoesNotThrow(): void
    {
        event(new PendingLinkSubmitted(1, 2, 'https://example.com', 'Title'));

        $this->expectNotToPerformAssertions();
    }
}
