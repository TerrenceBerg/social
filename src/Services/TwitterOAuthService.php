<?php

namespace Tuna976\Social\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TwitterOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected string $baseUrl = 'https://api.twitter.com';
    protected string $authorizeUrl = 'https://twitter.com/i/oauth2/authorize';
    protected string $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
    protected array $scopes;

    public function __construct()
    {
        $this->clientId = config('social.twitter.client_id');
        $this->clientSecret = config('social.twitter.client_secret');
        $this->redirectUri = config('social.twitter.redirect');
        $this->scopes = config('social.twitter.scopes');
    }

    public function generatePkceVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function generatePkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function getAuthorizationUrl(string $state, string $verifier): string
    {
        $challenge = $this->generatePkceChallenge($verifier);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'grant_type' => 'authorization_code',
        ]);

        return "{$this->authorizeUrl}?{$params}";
    }


    public function getAccessToken(string $code, string $verifier): array
    {
        $clientId = config('social.twitter.client_id');
        $clientSecret = config('social.twitter.client_secret');
        $redirectUri = config('social.twitter.redirect');

        $encodedCredentials = base64_encode($clientId . ':' . $clientSecret);

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Basic ' . $encodedCredentials
            ])
            ->post('https://api.twitter.com/2/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $verifier,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to retrieve access token: ' . $response->body());
        }
        $tokens = $response->json();
        app(\Tuna976\Social\Services\TwitterTokenManager::class)->storeInitialTokens($tokens);

        return [
            'success' => true,
            'message' => 'User authenticated and tokens stored.',
            'tokens' => $tokens,
            'user' => $this->getUserProfile($tokens['access_token']),
        ];
//        return $response->json();
    }
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to refresh access token: ' . $response->body());
        }

        return $response->json();
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/2/users/me");

        if ($response->failed()) {
            throw new \Exception('Failed to fetch user profile: ' . $response->body());
        }

        return $response->json();
    }

}
