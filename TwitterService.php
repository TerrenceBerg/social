<?php

namespace Tuna976\Social\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;

use Illuminate\Support\Facades\Http;

class TwitterService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $accessToken;
    protected $accessTokenSecret;

    public function __construct($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret)
    {
        $this->consumerKey        = $consumerKey;
        $this->consumerSecret     = $consumerSecret;
        $this->accessToken        = $accessToken;
        $this->accessTokenSecret  = $accessTokenSecret;
    }


    public function postTweet($message)
    {
        $url = 'https://api.twitter.com/2/tweets';

        $params = ['text' => $message];

        $headers = [
            'Authorization' => $this->buildOauthHeader($url, [], 'POST'),
            'Content-Type'  => 'application/json',
        ];

        return Http::withHeaders($headers)->post($url, $params)->json();
    }

    public function postTweetWithMedia($message, $mediaPath)
    {
        $mediaId = $this->uploadMedia($mediaPath);

        if (!$mediaId) {
            return ['error' => 'Failed to upload media.'];
        }

        $url = 'https://api.twitter.com/2/tweets';

        $params = [
            'text' => $message,
            'media' => [
                'media_ids' => [$mediaId],
            ],
        ];

        $headers = [
            'Authorization' => $this->buildOauthHeader($url, [], 'POST'),
            'Content-Type'  => 'application/json',
        ];

        return Http::withHeaders($headers)->post($url, $params)->json();
    }

    protected function uploadMedia($filePath)
    {
        $url = 'https://upload.twitter.com/1.1/media/upload.json';

        $file = fopen($filePath, 'r');

        $headers = [
            'Authorization' => $this->buildOauthHeader($url, [], 'POST'),
        ];

        $response = Http::withHeaders($headers)
            ->attach('media', $file, basename($filePath))
            ->post($url);

        fclose($file);

        $result = $response->json();

        return isset($result['media_id_string']) ? $result['media_id_string'] : null;
    }
    /**
     * Builds OAuth 1.0a header for requests.
     */
    protected function buildOauthHeader(string $url, array $params = [], string $method = 'GET'): string
    {
        $oauthParams = [
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_nonce'            => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_token'            => $this->accessToken,
            'oauth_version'          => '1.0',
        ];

        $allParams = array_merge($oauthParams, $params);
        ksort($allParams);

        $baseParams = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($baseParams);
        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->accessTokenSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;

        return 'OAuth ' . collect($oauthParams)->map(fn($v, $k) => "$k=\"" . rawurlencode($v) . "\"")->implode(', ');
    }
    protected function generateNonce($length = 32)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }

        // Very last resort
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}
