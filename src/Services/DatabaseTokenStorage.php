<?php


namespace Tuna976\Social\Services;

use Illuminate\Support\Facades\Auth;
use Tuna976\Social\Contracts\TokenStorageInterface;
use Tuna976\Social\Models\SocialAuthToken;


class DatabaseTokenStorage implements TokenStorageInterface
{

    protected string $provider;

    public function __construct(string $provider = 'twitter')
    {
        $this->provider = $provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

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

    public function storeTokens(array $tokenData,$user=null,$verifier=null): void
    {
        $user_id = $user?$user['data']['id']:null;
        $token = SocialAuthToken::firstOrNew([
            'provider' => $this->provider,
            'verifier' => $verifier ?? null,
        ]);

        $token->access_token = $tokenData['access_token'] ?? $token->access_token;
        $token->refresh_token = $tokenData['refresh_token'] ?? $token->refresh_token;
        $token->expires_at = now()->addSeconds($tokenData['expires_in'] ?? 3600);
        $token->extra_data =$user_id ? json_encode($user) : null;
        $token->user_id =$user_id ?? null;

        $token->save();
    }

    protected function getTokenRecord(): ?SocialAuthToken
    {
        return SocialAuthToken::where('provider', $this->provider)
            ->whereNull('user_id')
            ->latest()
            ->first();
    }
}
