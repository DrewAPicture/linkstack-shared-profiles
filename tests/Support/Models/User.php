<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use WerdsWords\LinkStack\SharedProfiles\Concerns\HasApiToken;
use WerdsWords\LinkStack\SharedProfiles\Contracts\HasApiTokenContract;

class User extends Authenticatable implements HasApiTokenContract
{
    use HasApiToken;

    protected $fillable = ['name', 'email', 'api_token', 'telegram_bot_token', 'telegram_group_chat_id', 'auto_approve'];

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
