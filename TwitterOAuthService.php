<?php

namespace YourNamespace\Social\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class TwitterOAuthService
{
    protected $baseUrl = 'https://twitter.com/i/oauth2/authorize';
    protected $tokenUrl = 'https://api.twitter.com/2/oauth2/token';

    public function getAuthUrl()
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Session::put('twitter_code_verifier', $codeVerifier);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('social.twitter.client_id'),
            'redirect_uri' => config('social.twitter.redirect_uri'),
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'state' => csrf_token(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return "{$this->baseUrl}?{$query}";
    }

    public function handleCallback($code)
    {
        $codeVerifier = Session::pull('twitter_code_verifier');

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('social.twitter.redirect_uri'),
            'client_id' => config('social.twitter.client_id'),
            'code_verifier' => $codeVerifier,
        ]);

        return $response->json();
    }

    public function postTweet($accessToken, $text)
    {
        $response = Http::withToken($accessToken)->post('https://api.twitter.com/2/tweets', [
            'text' => $text,
        ]);

        return $response->json();
    }
    public function generateCodeVerifier($length = 64)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }

        // Fallback for older PHP versions
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
}
