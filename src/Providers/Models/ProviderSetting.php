<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $provider
 * @property array<string, mixed> $settings
 *
 * @method static Builder<ProviderSetting> forProvider(string $provider)
 */
class ProviderSetting extends Model
{
    public $timestamps = false;

    protected $table = 'provider_settings';

    protected $fillable = [
        'profile_id',
        'provider',
        'settings',
    ];

    protected $casts = [
        'profile_id' => 'integer',
        'settings' => 'encrypted:array',
    ];

    public function setAttribute($key, $value): mixed
    {
        if ($key === 'settings') {
            return $this->setSettingsValue($value);
        }

        return parent::setAttribute($key, $value);
    }

    private function setSettingsValue(#[\SensitiveParameter] mixed $value): mixed
    {
        return parent::setAttribute('settings', $value);
    }

    /**
     * @param  Builder<ProviderSetting>  $query
     * @return Builder<ProviderSetting>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
