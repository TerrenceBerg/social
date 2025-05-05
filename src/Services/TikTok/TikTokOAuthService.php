<?php
namespace Tuna976\Social\Services\TikTok;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tuna976\Social\Contracts\TokenStorageInterface;

class TikTokOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes;
    protected $storage;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->storage->setProvider('tiktok');

        $this->clientId = config('social.tiktok.client_id');
        $this->clientSecret = config('social.tiktok.client_secret');
        $this->redirectUri = config('social.tiktok.redirect');
        $this->scopes = config('social.tiktok.scopes');

    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
                'client_key' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'response_type' => 'code',
                'scope' => implode(',', $this->scopes),
                'state' => $state,
            ]);
    }

    public function getAccessToken(string $code): array
    {
        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get TikTok access token: ' . $response->body());
        }

        $tokenData = $response->json();

        // Get user info and store token
//        $user = $this->getUserProfile($tokenData['access_token']);
        $user =null;
        $this->storage->storeTokens($tokenData, ['data' => $user]);

        return $tokenData;
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://open.tiktokapis.com/v2/user/info/', [
            'fields' => 'open_id,username,avatar_url,display_name',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get TikTok user profile: ' . $response->body());
        }

        return $response->json()['data']['user'];
    }
}
