<?php

namespace Tuna976\Social\Services\Youtube;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class YoutubeOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('social.youtube.client_id');
        $this->clientSecret = config('social.youtube.client_secret');
        $this->redirectUri = config('social.youtube.redirect');
    }



    public function getAuthorizationUrl(): string
    {
//        Readonly
        $scopes = implode(' ', [
            'https://www.googleapis.com/auth/youtube.upload',
            // 'https://www.googleapis.com/auth/youtube.readonly',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'response_type' => 'code',
                'access_type' => 'offline',
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'state' => null,
                'scope' => $scopes,
                'approval_prompt' => 'force',
                'service' => 'lso'
            ]);
    }

    public function getAccessToken(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        return $response->json();
    }
}
