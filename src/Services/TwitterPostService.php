<?php
namespace Tuna976\Social\Services;
use Illuminate\Support\Facades\Http;

class TwitterPostService
{
    public function __construct(
        protected TwitterTokenManager $tokenManager
    ) {}

    public function post(string $text): array
    {
        $accessToken = $this->tokenManager->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text
            ]);

        if (!$response->successful()) {
            throw new \Exception("Tweet failed: " . $response->body());
        }

        return $response->json();
    }

    public function postWithMedia(string $text, string $mediaPath): array
    {
        $accessToken = $this->tokenManager->getAccessToken();

        // First, upload the media
        $mediaId = $this->uploadMedia($mediaPath);

        // Then create the tweet with the media
        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text,
                'media' => [
                    'media_ids' => [$mediaId]
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception("Tweet with media failed: " . $response->body());
        }

        return $response->json();
    }

    protected function uploadMedia(string $mediaPath): string
    {
        $accessToken = $this->tokenManager->getAccessToken();

        if (!file_exists($mediaPath)) {
            throw new \Exception("Media file not found: {$mediaPath}");
        }

        $mediaContent = file_get_contents($mediaPath);
        $mimeType = mime_content_type($mediaPath);

        // Upload media to Twitter's media endpoint
        $response = Http::withToken($accessToken)
            ->attach('media', $mediaContent, basename($mediaPath), ['Content-Type' => $mimeType])
            ->post('https://upload.twitter.com/1.1/media/upload.json');

        if (!$response->successful()) {
            throw new \Exception("Media upload failed: " . $response->body());
        }

        $responseData = $response->json();
        return $responseData['media_id_string'];
    }
}
