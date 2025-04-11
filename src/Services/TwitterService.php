<?php

namespace Tuna976\Social\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Tuna976\Social\Contracts\TwitterServiceInterface;

class TwitterService
{
    protected $provider;
    protected $authToken;
    protected $credentials;
    protected $accessToken;
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.twitter.com/',
        ]);

        $this->authToken = config('social.twitter.auth_token');

        $this->credentials = [
            'client_id'     => config('social.twitter.consumer_key'),
            'client_secret' => config('social.twitter.consumer_secret'),
            'redirect_uri'  => config('social.twitter.redirect_uri'),
        ];
    }

    public function authenticate(): void
    {
        $authUrl = $this->provider->getAuthorizationUrl();
        session()->put('oauth2state', $this->provider->getState());

        header('Location: ' . $authUrl);
        exit;
    }

    public function postMessage(string $message, array $options = []): array
    {
        return $this->authorizedRequest('POST', '2/tweets', [
            'json' => [
                'text' => $message
            ],
        ]);
    }

    public function postMedia(string $message, string $mediaPath): array
    {
        // Step 1: Upload to Twitter API v1.1
        $uploadResponse = Http::withToken(config('social.twitter.auth_token'))
            ->attach('media', file_get_contents($mediaPath), basename($mediaPath))
            ->post('https://upload.twitter.com/1.1/media/upload.json');

        $mediaId = $uploadResponse->json()['media_id_string'] ?? null;

        if (!$mediaId) {
            throw new \Exception('Media upload failed');
        }

        // Step 2: Post tweet with media using v2
        return $this->authorizedRequest('POST', 'tweets', [], [
            'text' => $message,
            'media' => [
                'media_ids' => [$mediaId],
            ],
        ]);
    }


    protected function authorizedRequest(
        string $method,
        string $url,
        array $options = []
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->authToken,
            'Accept'        => 'application/json',
        ];
        $options['headers'] = array_merge($headers, $options['headers'] ?? []);
        $response = $this->client->request(strtoupper($method), $url, $options);

        return json_decode($response->getBody()->getContents(), true);
    }
}
