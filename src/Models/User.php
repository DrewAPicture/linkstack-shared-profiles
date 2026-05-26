<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Models;

use App\Models\User as BaseUser;
use WerdsWords\LinkStack\SharedProfiles\Concerns\HasApiToken;
use WerdsWords\LinkStack\SharedProfiles\Contracts\HasApiTokenContract;

final class User extends BaseUser implements HasApiTokenContract
{
    use HasApiToken;
}
