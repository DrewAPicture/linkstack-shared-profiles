<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Concerns;

use Illuminate\Database\Eloquent\Builder;
use SensitiveParameter;

trait HasApiToken
{
    public function setApiToken(#[SensitiveParameter] string $rawToken): void
    {
        $this->api_token = hash('sha256', $rawToken);
    }

    /** @param Builder<static> $query */
    public function scopeForToken(Builder $query, #[SensitiveParameter] string $rawToken): Builder
    {
        return $query->where('api_token', hash('sha256', $rawToken));
    }
}
