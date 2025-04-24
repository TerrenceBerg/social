<?php

namespace Tuna976\Social\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookEntity extends Model
{
    protected $fillable = [
        'user_id',
        'entity_id',
        'entity_type',
        'name',
        'access_token',
    ];

    protected $casts = [
        'entity_id'       => 'encrypted',
        'name'          => 'encrypted',
        'access_token'  => 'encrypted',
    ];

}
