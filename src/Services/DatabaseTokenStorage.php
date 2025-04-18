<?php


namespace Tuna976\Social\Services;

use Tuna976\Social\Contracts\TokenStorageInterface;
use Tuna976\Social\Models\SocialAuthToken;


class DatabaseTokenStorage implements TokenStorageInterface
{
    protected string $provider = 'twitter';

    public function getAccessToken(): ?string
    {
        return $this->getTokenRecord()?->access_token;
    }

    public function getRefreshToken(): ?string
    {
        return $this->getTokenRecord()?->refresh_token;
    }

    public function getExpiresAt(): ?int
    {
        return optional($this->getTokenRecord()?->expires_at)->timestamp;
    }

    public function storeTokens(array $tokenData): void
    {
        SocialAuthToken::updateOrCreate(
            ['provider' => $this->provider, 'user_id' => null],
            [
                'access_token' => $tokenData['access_token'] ?? null,
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
            ]
        );
    }

    protected function getTokenRecord(): ?SocialAuthToken
    {
        return SocialAuthToken::where('provider', $this->provider)
            ->whereNull('user_id') // Or replace with auth-based logic
            ->latest()
            ->first();
    }
}
