<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Concerns;

use Illuminate\Support\Arr;

trait CanGetStringFromArray
{
    protected static function get(mixed $data, string $key, string $default = ''): string
    {
        if (! Arr::accessible($data)) {
            return $default;
        }

        return (string) Arr::get($data, $key, $default);
    }
}
