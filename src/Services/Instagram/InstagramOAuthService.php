<?php

namespace Tuna976\Social\Services\Instagram;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Contracts\TokenStorageInterface;

class InstagramOAuthService
{
    use LogsToChannel;
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes;
    protected TokenStorageInterface $storage;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->clientId = config('services.instagram.client_id');
        $this->clientSecret = config('services.instagram.client_secret');
        $this->redirectUri = config('services.instagram.redirect');
        $this->scopes = config('social.instagram.scopes');
        $this->storage = $storage;

        $this->storage->setProvider('instagram');
    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://api.instagram.com/oauth/authorize?' . http_build_query([
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'scope' => implode(',', $this->scopes),
                'response_type' => 'code',
                'state' => $state,
            ]);
    }

    public function getAccessToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            $errorMessage = 'Failed to get Instagram access token: ' . $response->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);

        }

        $data = $response->json();

        $user = $this->getUserProfile($data['access_token']);
        $this->storage->storeTokens($data, ['data' => $user]);

        return $data;
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::get('https://graph.instagram.com/me', [
            'fields' => 'id,username,account_type,media_count',
            'access_token' => $accessToken,
        ]);

        return $response->json();
    }
}
