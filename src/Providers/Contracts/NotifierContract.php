<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Contracts;

interface NotifierContract
{
    public function notifyModerators(int $profileId, int $linkId, string $link, string $title): void;
}
