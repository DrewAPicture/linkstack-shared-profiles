<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Helpers;

use Illuminate\Support\Arr;

final class DataGetter
{
    public static function stringFromArray(mixed $data, string $key, string $default = ''): string
    {
        if (! Arr::accessible($data)) {
            return $default;
        }

        /** @var array<string, mixed>|\ArrayAccess<string, mixed> $data */
        $value = Arr::get($data, $key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public static function arrayFromArray(mixed $data, string $key, array $default = []): array
    {
        if (! Arr::accessible($data)) {
            return $default;
        }

        /** @var array<string, mixed>|\ArrayAccess<string, mixed> $data */
        $output = Arr::get($data, $key, $default);

        if (! Arr::accessible($output)) {
            return $default;
        }

        /** @var array<string, mixed> $output */
        return $output;
    }
}
