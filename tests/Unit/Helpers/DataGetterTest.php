<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Helpers;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WerdsWords\LinkStack\SharedProfiles\Helpers\DataGetter;

#[CoversClass(DataGetter::class)]
final class DataGetterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // stringFromArray()
    // -------------------------------------------------------------------------

    public function testStringFromArrayReturnsDefaultWhenDataIsNotAccessible(): void
    {
        $this->assertSame('', DataGetter::stringFromArray(null, 'key'));
        $this->assertSame('', DataGetter::stringFromArray('a string', 'key'));
        $this->assertSame('', DataGetter::stringFromArray(42, 'key'));
    }

    public function testStringFromArrayReturnsDefaultWhenKeyIsAbsent(): void
    {
        $this->assertSame('', DataGetter::stringFromArray(['foo' => 'bar'], 'missing'));
    }

    public function testStringFromArrayReturnsValueForFlatKey(): void
    {
        $this->assertSame('bar', DataGetter::stringFromArray(['foo' => 'bar'], 'foo'));
    }

    public function testStringFromArrayReturnsValueForDotNotationKey(): void
    {
        $this->assertSame('c', DataGetter::stringFromArray(['a' => ['b' => 'c']], 'a.b'));
    }

    public function testStringFromArrayCastsNonStringValueToString(): void
    {
        $this->assertSame('42', DataGetter::stringFromArray(['count' => 42], 'count'));
    }

    public function testStringFromArrayReturnsCustomDefault(): void
    {
        $this->assertSame('fallback', DataGetter::stringFromArray(null, 'key', 'fallback'));
    }

    // -------------------------------------------------------------------------
    // arrayFromArray()
    // -------------------------------------------------------------------------

    public function testArrayFromArrayReturnsDefaultWhenDataIsNotAccessible(): void
    {
        $this->assertSame([], DataGetter::arrayFromArray(null, 'key'));
        $this->assertSame([], DataGetter::arrayFromArray('a string', 'key'));
        $this->assertSame([], DataGetter::arrayFromArray(42, 'key'));
    }

    public function testArrayFromArrayReturnsDefaultWhenKeyIsAbsent(): void
    {
        $this->assertSame([], DataGetter::arrayFromArray(['foo' => 'bar'], 'missing'));
    }

    public function testArrayFromArrayReturnsDefaultWhenValueIsNotAnArray(): void
    {
        $this->assertSame([], DataGetter::arrayFromArray(['foo' => 'bar'], 'foo'));
    }

    public function testArrayFromArrayReturnsArrayForFlatKey(): void
    {
        $this->assertSame(['b' => 'c'], DataGetter::arrayFromArray(['a' => ['b' => 'c']], 'a'));
    }

    public function testArrayFromArrayReturnsNestedArrayForDotNotationKey(): void
    {
        $data = ['a' => ['b' => ['c' => 'd']]];

        $this->assertSame(['c' => 'd'], DataGetter::arrayFromArray($data, 'a.b'));
    }

    public function testArrayFromArrayReturnsCustomDefault(): void
    {
        $default = ['fallback' => true];

        $this->assertSame($default, DataGetter::arrayFromArray(null, 'key', $default));
    }
}
