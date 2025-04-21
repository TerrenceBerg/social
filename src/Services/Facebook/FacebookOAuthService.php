<?php

namespace Tuna976\Social\Services\Facebook;

use Illuminate\Support\Facades\Http;

class FacebookOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes;

    public function __construct()
    {
        $this->clientId = config('services.facebook.client_id');
        $this->clientSecret = config('services.facebook.client_secret');
        $this->redirectUri = config('services.facebook.redirect');
        $this->scopes = config('social.facebook.scopes');
    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://www.facebook.com/v22.0/dialog/oauth?' . http_build_query([
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'state' => $state,
                'response_type' => 'code',
                'scope' => implode(',', $this->scopes),
            ]);
    }

    public function getAccessToken(string $code): array
    {
        $response = Http::get('https://graph.facebook.com/v22.0/oauth/access_token', [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ]);
        return $response->json();
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::get('https://graph.facebook.com/v22.0/me', [
            'fields' => 'id,name,email',
            'access_token' => $accessToken,
        ]);

        return $response->json();
    }
}
