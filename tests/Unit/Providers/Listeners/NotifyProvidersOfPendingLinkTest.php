<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
use WerdsWords\LinkStack\SharedProfiles\Providers\Contracts\NotifierContract;
use WerdsWords\LinkStack\SharedProfiles\Providers\Listeners\NotifyProvidersOfPendingLink;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

#[CoversClass(NotifyProvidersOfPendingLink::class)]
final class NotifyProvidersOfPendingLinkTest extends TestCase
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
    // handle()
    // -------------------------------------------------------------------------

    #[CoversMethod(NotifyProvidersOfPendingLink::class, 'handle')]
    public function testHandleDoesNothingWhenNoNotifiersRegistered(): void
    {
        $listener = new NotifyProvidersOfPendingLink;

        // No assertion needed — we verify no exception is thrown
        $listener->handle(new PendingLinkSubmitted(1, 2, 'https://example.com', 'Title'));

        $this->expectNotToPerformAssertions();
    }

    #[CoversMethod(NotifyProvidersOfPendingLink::class, 'handle')]
    public function testHandleCallsNotifyModeratorsOnEachRegisteredNotifier(): void
    {
        $calls = [];

        $stubA = new class($calls) implements NotifierContract
        {
            public function __construct(private array &$calls) {}

            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void
            {
                $this->calls[] = 'A';
            }
        };

        $stubB = new class($calls) implements NotifierContract
        {
            public function __construct(private array &$calls) {}

            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void
            {
                $this->calls[] = 'B';
            }
        };

        ServiceProvider::registerNotifier($stubA);
        ServiceProvider::registerNotifier($stubB);

        (new NotifyProvidersOfPendingLink)->handle(new PendingLinkSubmitted(1, 2, 'https://example.com', 'Title'));

        $this->assertSame(['A', 'B'], $calls);
    }

    #[CoversMethod(NotifyProvidersOfPendingLink::class, 'handle')]
    public function testHandlePassesEventDataToNotifier(): void
    {
        $received = [];

        $stub = new class($received) implements NotifierContract
        {
            public function __construct(private array &$received) {}

            public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void
            {
                $this->received = compact('profileId', 'linkId', 'link', 'title');
            }
        };

        ServiceProvider::registerNotifier($stub);

        (new NotifyProvidersOfPendingLink)->handle(
            new PendingLinkSubmitted(42, 7, 'https://example.com/foo', 'My Link')
        );

        $this->assertSame(42, $received['profileId']);
        $this->assertSame(7, $received['linkId']);
        $this->assertSame('https://example.com/foo', $received['link']);
        $this->assertSame('My Link', $received['title']);
    }
}
