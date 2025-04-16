<?php


namespace Tuna976\Social\Services;

use Tuna976\Social\Contracts\TokenStorageInterface;
use Illuminate\Support\Facades\Cache;

class CacheTokenStorage implements TokenStorageInterface
{
    public function getAccessToken(): ?string
    {
        return Cache::get('twitter_access_token');
    }

    public function getRefreshToken(): ?string
    {
        return Cache::get('twitter_refresh_token');
    }

    public function getExpiresAt(): ?int
    {
        return Cache::get('twitter_token_expires_at');
    }

    public function storeTokens(array $tokenData): void
    {
        Cache::put('twitter_access_token', $tokenData['access_token'], now()->addSeconds($tokenData['expires_in']));
        Cache::put('twitter_refresh_token', $tokenData['refresh_token'], now()->addDays(180));
        Cache::put('twitter_token_expires_at', now()->addSeconds($tokenData['expires_in'])->timestamp);
    }
}
