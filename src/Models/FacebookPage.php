<?php

namespace Tuna976\Social\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookPage extends Model
{
    protected $fillable = [
        'user_id',
        'page_id',
        'name',
        'access_token',
    ];

    protected $casts = [
        'page_id'       => 'encrypted',
        'name'          => 'encrypted',
        'access_token'  => 'encrypted',
    ];

}
