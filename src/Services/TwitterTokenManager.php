<?php

namespace Tuna976\Social\Services;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Concerns\HandlesErrorNotifications;
use Tuna976\Social\Contracts\TokenStorageInterface;

class TwitterTokenManager
{
    use LogsToChannel;
    use HandlesErrorNotifications;

    public function __construct(
        protected TokenStorageInterface $storage
    ) {}

    public function storeInitialTokens(array $tokens, $user = null, $verifier = null): void
    {
        if (!is_array($tokens)) {
            $this->throwWithNotification('Twitter storeInitialTokens expected array, got: ' . gettype($tokens));
        }

        if (!array_key_exists('expires_in', $tokens)) {
            $this->throwWithNotification('Missing "expires_in" in Twitter storeInitialTokens. Tokens: ' . json_encode($tokens));
        }

        $accessToken = $tokens['access_token'] ?? null;
        $refreshToken = $tokens['refresh_token'] ?? null;
        $expiresIn = $tokens['expires_in'];
        $expiresAt = now()->addSeconds($expiresIn);

        $this->storage->storeTokens([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ], $user, $verifier);
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

        $clientId = config('social.twitter.client_id');
        $clientSecret = config('social.twitter.client_secret');

        $credentials = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Basic ' . $credentials,
            ])
            ->post('https://api.twitter.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            $this->throwWithNotification("Failed to twitter refresh access token: " . $response->body());
        }

        $this->storage->storeTokens($response->json());
    }

    protected function throwWithNotification(string $message): void
    {
        $this->notifyError($message);
        throw new \Exception($message);
    }
}
