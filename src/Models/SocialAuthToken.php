<?php
namespace Tuna976\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SocialAuthToken extends Model
{
    protected $table = 'social_auth_tokens';

    protected $fillable = [
        'user_id',
        'provider',
        'state',
        'verifier',
        'access_token',
        'refresh_token',
        'expires_at',
        'extra_data',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'extra_data' => 'array',
    ];

    // Encrypt/decrypt logic
    public function getAccessTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getVerifierAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setVerifierAttribute($value)
    {
        $this->attributes['verifier'] = $value ? Crypt::encryptString($value) : null;
    }
}
