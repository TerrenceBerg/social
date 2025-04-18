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
        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        if ($accessToken) {
            Cache::put('twitter_access_token', $accessToken, now()->addSeconds($expiresIn));
        }

        if ($refreshToken) {
            Cache::put('twitter_refresh_token', $refreshToken, now()->addDays(180));
        }

        Cache::put('twitter_token_expires_at', now()->addSeconds($expiresIn)->timestamp);
    }
}
