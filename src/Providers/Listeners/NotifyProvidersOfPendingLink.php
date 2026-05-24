<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Listeners;

use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

class NotifyProvidersOfPendingLink
{
    public function handle(PendingLinkSubmitted $event): void
    {
        foreach (ServiceProvider::registeredNotifiers() as $notifier) {
            $notifier->notifyModerators($event->profileId, $event->linkId, $event->link, $event->title);
        }
    }
}
