<?php
namespace Tuna976\Social\Services;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Contracts\TokenStorageInterface;

class TwitterTokenManager
{
    public function __construct(
        protected TokenStorageInterface $storage
    ) {}

    public function storeInitialTokens(array $tokens): void
    {
        $this->storage->storeTokens([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'])->timestamp,
        ]);
    }
    public function getAccessToken(): string
    {
        $expiresAt = $this->storage->getExpiresAt();

        if (!$expiresAt || now()->timestamp >= ($expiresAt - 60)) {
            $this->refreshToken();
        }

        return $this->storage->getAccessToken();
    }

    protected function refreshToken(): void
    {
        $refreshToken = $this->storage->getRefreshToken();

        $response = Http::asForm()->post('https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('social.twitter.client_id'),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to refresh access token: " . $response->body());
        }

        $this->storage->storeTokens($response->json());
    }
}
