<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Support;

final class ReplayGuard
{
    public static function isStale(int $timestamp, int $ttlSeconds): bool
    {
        return (time() - $timestamp) > $ttlSeconds;
    }
}
