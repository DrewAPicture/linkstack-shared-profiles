<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-require-extends Model
 *
 * @method static Builder<static> forToken(string $rawToken)
 */
interface ApiTokenableContract
{
    public function setApiToken(string $rawToken): void;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeForToken(Builder $query, string $rawToken): Builder;
}
