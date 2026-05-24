<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $external_id
 * @property int $profile_id
 * @property string $role
 * @property string|null $added_by
 * @property Carbon $created_at
 *
 * @method static Builder<ProviderManager> forProvider(string $provider)
 */
class ProviderManager extends Model
{
    const UPDATED_AT = null;

    protected $table = 'provider_managers';

    protected $fillable = [
        'provider',
        'external_id',
        'profile_id',
        'role',
        'added_by',
    ];

    protected $casts = [
        'profile_id' => 'integer',
    ];

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * @param  Builder<ProviderManager>  $query
     * @return Builder<ProviderManager>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
