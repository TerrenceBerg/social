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

    public function storeInitialTokens(array $tokens, $user = null, $verifier = null): bool
    {
        if (!is_array($tokens)) {
            return $this->throwWithNotification('Twitter storeInitialTokens expected array, got: ' . gettype($tokens));
        }

        if (!array_key_exists('expires_in', $tokens)) {
            return $this->throwWithNotification('Missing "expires_in" in Twitter storeInitialTokens. Tokens: ' . json_encode($tokens));
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

        return true;
    }

    public function getAccessToken(): string|false
    {
        try {
            $expiresAt = $this->storage->getExpiresAt();

            if (!$expiresAt || now()->timestamp >= ($expiresAt - 60)) {
                if (!$this->refreshToken()) {
                    return false;
                }
            }

            return $this->storage->getAccessToken();
        } catch (\Throwable $e) {
            $errorMessage = 'Failed to get Twitter access token: ' . $e->getMessage();
            return $this->throwWithNotification($errorMessage);
        }
    }

    protected function refreshToken(): bool
    {
        $refreshToken = $this->storage->getRefreshToken();

        if (!$refreshToken) {
            return $this->throwWithNotification("Missing Twitter refresh token.");
        }

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
            return $this->throwWithNotification("Failed to Twitter refresh access token: " . $response->body());
        }

        $this->storage->storeTokens($response->json());

        return true;
    }

    protected function throwWithNotification(string $message): bool
    {
        $this->notifyError($message);
        return false;
    }
}
