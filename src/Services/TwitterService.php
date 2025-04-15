<?php

namespace Tuna976\Social\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Smolblog\OAuth2\Client\Provider\Twitter;
use Tuna976\Social\Contracts\TwitterServiceInterface;

class TwitterService implements TwitterServiceInterface
{
    protected $provider;
    protected $authToken;
    protected $credentials;
    protected $accessToken;
    protected Client $client;

    public function __construct()
    {

        $this->oauth = new Twitter([
            'client_id' => env('TWITTER_CLIENT_ID'),
            'client_secret' => env('TWITTER_CLIENT_SECRET'),
            'redirect_uri' => env('TWITTER_REDIRECT_URI')
        ]);

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
      dd($message, $options);
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
    public function fetchTimeline(): array
    {
        $response = $this->client->get('https://api.twitter.com/2/users/me/tweets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);

        return json_decode($response->getBody(), true);
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


    protected $oauth;


    public function redirectToTwitter()
    {
        $state = bin2hex(random_bytes(16));
        $codeVerifier = bin2hex(random_bytes(64));
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        session()->put('twitter_oauth_state', $state);
        session()->put('twitter_code_verifier', $codeVerifier);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('social.twitter.consumer_key'),
            'redirect_uri' => config('social.twitter.redirect_uri'),
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect("https://twitter.com/i/oauth2/authorize?$query");
    }

    public function handleTwitterCallback(Request $request)
    {
        $state = $request->input('state');
        $code = $request->input('code');

        if ($state !== session('twitter_oauth_state')) {
            abort(403, 'Invalid state');
        }

        $codeVerifier = session('twitter_code_verifier');

        $response = Http::asForm()->post('https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('social.twitter.redirect_uri'),
            'client_id' => config('social.twitter.client_id'),
            'code_verifier' => $codeVerifier,
        ]);

        $tokenData = $response->json();

        // You now have access token, refresh token, etc.
        return response()->json($tokenData);
    }
}
