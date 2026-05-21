<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $fillable = [
        'link',
        'title',
        'button_id',
        'type',
        'type_params',
        'status',
        'order',
        'up_link',
    ];
}
