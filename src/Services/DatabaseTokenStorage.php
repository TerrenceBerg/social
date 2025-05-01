<?php


namespace Tuna976\Social\Services;

use Carbon\Carbon;
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


    public function storeTokens(array $tokenData, $user = null, $verifier = null): void
    {
        $token = SocialAuthToken::firstOrNew([
            'provider' => $this->provider,
        ]);

        $token->access_token = $tokenData['access_token'] ?? $token->access_token;
        $token->refresh_token = $tokenData['refresh_token'] ?? $token->refresh_token;

        if (isset($tokenData['expires_in'])) {
            $token->expires_at = Carbon::now()->addSeconds($tokenData['expires_in']);
        } elseif (isset($tokenData['expires_at'])) {
            $token->expires_at = Carbon::createFromTimestamp($tokenData['expires_at']);
        }

        $token->verifier = $verifier ?? $token->verifier;
        $token->extra_data = $user ? json_encode($user) : null;
        $token->user_id = $user['data']['id'] ?? null;

        $token->save();
    }

    protected function getTokenRecord(): ?SocialAuthToken
    {
        return SocialAuthToken::where('provider', $this->provider)
            ->latest()
            ->first();
    }
}
