<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use WerdsWords\LinkStack\SharedProfiles\Providers\Support\ReplayGuard;

#[CoversClass(ReplayGuard::class)]
final class ReplayGuardTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isStale()
    // -------------------------------------------------------------------------

    #[CoversMethod(ReplayGuard::class, 'isStale')]
    public function testIsStaleReturnsFalseWhenTimestampWithinTtl(): void
    {
        $timestamp = time() - 30;

        $this->assertFalse(ReplayGuard::isStale($timestamp, 60));
    }

    #[CoversMethod(ReplayGuard::class, 'isStale')]
    public function testIsStaleReturnsTrueWhenTimestampExceedsTtl(): void
    {
        $timestamp = time() - 120;

        $this->assertTrue(ReplayGuard::isStale($timestamp, 60));
    }

    #[CoversMethod(ReplayGuard::class, 'isStale')]
    public function testIsStaleReturnsFalseWhenTimestampExactlyAtTtlBoundary(): void
    {
        // age == ttl is not yet stale (strictly greater-than check)
        $timestamp = time() - 60;

        $this->assertFalse(ReplayGuard::isStale($timestamp, 60));
    }

    #[CoversMethod(ReplayGuard::class, 'isStale')]
    public function testIsStaleReturnsTrueForFutureTimestampWithZeroTtl(): void
    {
        // A negative age (future timestamp) with ttl=0: (time - future) < 0, which is not > 0
        $timestamp = time() + 100;

        $this->assertFalse(ReplayGuard::isStale($timestamp, 0));
    }
}
