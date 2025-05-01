<?php

namespace Tuna976\Social\Services\Facebook;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Contracts\TokenStorageInterface;

class FacebookService
{
    use LogsToChannel;
    public function __construct(
        protected TokenStorageInterface $storage,
        protected string $provider = 'facebook'
    ) {}

    public function getAuthorizationUrl(string $state, string $verifier): string
    {
        $clientId = config('social.facebook.client_id');
        $redirectUri = config('social.facebook.redirect');

        return 'https://www.facebook.com/v22.0/dialog/oauth?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'response_type' => 'code',
                'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list,publish_to_groups',
            ]);
    }

    public function getAccessToken(string $code): array
    {
        $clientId = config('social.facebook.client_id');
        $clientSecret = config('social.facebook.client_secret');
        $redirectUri = config('social.facebook.redirect');

        $response = Http::get('https://graph.facebook.com/v22.0/oauth/access_token', [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'client_secret' => $clientSecret,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            $errorMessage = 'Failed to get Facebook access token: ' . $response->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        $data = $response->json();

        // Optional: Get long-lived token
        $longLivedTokenResponse = Http::get('https://graph.facebook.com/v22.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $data['access_token'],
        ]);

        if ($longLivedTokenResponse->successful()) {
            $data = array_merge($data, $longLivedTokenResponse->json());
        }

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 60 * 60 * 24 * 60, // default 60 days
        ];
    }

//    public function postToPage(string $pageId, string $message): array
//    {
//        $accessToken = $this->storage->getAccessToken();
//
//        // Get page access token
//        $response = Http::get("https://graph.facebook.com/{$pageId}", [
//            'fields' => 'access_token',
//            'access_token' => $accessToken,
//        ]);
//
//        if (!$response->successful()) {
//            throw new \Exception('Failed to get page access token: ' . $response->body());
//        }
//
//        $pageToken = $response->json()['access_token'];
//
//        // Post the message
//        $postResponse = Http::post("https://graph.facebook.com/{$pageId}/feed", [
//            'message' => $message,
//            'access_token' => $pageToken,
//        ]);
//
//        if (!$postResponse->successful()) {
//            throw new \Exception('Failed to post to Facebook page: ' . $postResponse->body());
//        }
//
//        return $postResponse->json();
//    }
    public function postToPage(string $pageId, string $pageAccessToken, string $message,string $link=null): array
    {

        if ($link) {
            // Post the message
            $postResponse = Http::post("https://graph.facebook.com/{$pageId}/feed", [
                'message' => $message,
                'link' => $link,
                'access_token' => $pageAccessToken,
            ]);
        }
        else{
            // Post the message
            $postResponse = Http::post("https://graph.facebook.com/{$pageId}/feed", [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ]);
        }

        if (!$postResponse->successful()) {
            $errorMessage = 'Failed to post to Facebook page: ' . $postResponse->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        return $postResponse->json();
    }
}
