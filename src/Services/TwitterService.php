<?php

namespace Tuna976\Services;

use Smolblog\OAuth2\Twitter\Provider;
use Tuna976\Contracts\TwitterServiceInterface;

class TwitterService implements TwitterServiceInterface
{
    protected $provider;

    public function __construct()
    {
        parent::__construct();

        $this->provider = new Provider([
            'clientId'     => env('TWITTER_CLIENT_ID'),
            'clientSecret' => env('TWITTER_CLIENT_SECRET'),
            'redirectUri'  => env('TWITTER_REDIRECT_URI'),
        ]);
    }

    public function authenticate(): void
    {
        // Implementation for Twitter OAuth 2.0 authentication
        // Handle the OAuth flow here and store the access token
    }

    public function postMessage(string $message, array $options = []): array
    {
        // Use Twitter's API to post a tweet
        $response = $this->client->post('https://api.twitter.com/2/tweets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'json' => ['status' => $message],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function postMedia(string $message, string $mediaPath): array
    {
        // Upload media and then post tweet
        $mediaResponse = $this->client->post('https://upload.twitter.com/1.1/media/upload.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'multipart' => [
                [
                    'name'     => 'media',
                    'contents' => fopen($mediaPath, 'r'),
                ],
            ],
        ]);

        $media = json_decode($mediaResponse->getBody(), true);

        $tweetResponse = $this->client->post('https://api.twitter.com/2/tweets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'json' => [
                'status' => $message,
                'media_ids' => [$media['media_id']],
            ],
        ]);

        return json_decode($tweetResponse->getBody(), true);
    }

    public function fetchTimeline(): array
    {
        // Fetch user timeline
        $response = $this->client->get('https://api.twitter.com/2/tweets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
