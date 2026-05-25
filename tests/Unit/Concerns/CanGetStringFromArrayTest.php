<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Concerns;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WerdsWords\LinkStack\SharedProfiles\Concerns\CanGetStringFromArray;

#[CoversClass(CanGetStringFromArray::class)]
final class CanGetStringFromArrayTest extends TestCase
{
    private function caller(): object
    {
        return new class
        {
            use CanGetStringFromArray;

            public function call(mixed $data, string $key, string $default = ''): string
            {
                return self::get($data, $key, $default);
            }
        };
    }

    public function testReturnsDefaultWhenDataIsNotAccessible(): void
    {
        $caller = $this->caller();

        $this->assertSame('', $caller->call(null, 'key'));
        $this->assertSame('', $caller->call('a string', 'key'));
        $this->assertSame('', $caller->call(42, 'key'));
    }

    public function testReturnsDefaultWhenKeyIsAbsent(): void
    {
        $this->assertSame('', $this->caller()->call(['foo' => 'bar'], 'missing'));
    }

    public function testReturnsValueForFlatKey(): void
    {
        $this->assertSame('bar', $this->caller()->call(['foo' => 'bar'], 'foo'));
    }

    public function testReturnsValueForDotNotationKey(): void
    {
        $this->assertSame('c', $this->caller()->call(['a' => ['b' => 'c']], 'a.b'));
    }

    public function testCastsNonStringValueToString(): void
    {
        $this->assertSame('42', $this->caller()->call(['count' => 42], 'count'));
    }

    public function testReturnsCustomDefault(): void
    {
        $this->assertSame('fallback', $this->caller()->call(null, 'key', 'fallback'));
    }
}
