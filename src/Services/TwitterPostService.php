<?php

namespace Tuna976\Social\Services;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\HandlesErrorNotifications;
use Tuna976\Social\Concerns\LogsToChannel;

class TwitterPostService
{
    use LogsToChannel;
    use HandlesErrorNotifications;

    public function __construct(
        protected TwitterTokenManager $tokenManager
    )
    {
    }

    public function post(string $text): array
    {
        $accessToken = $this->tokenManager->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text
            ]);

        if (!$response->successful()) {
            $errorMessage = "Tweet failed: " . $response->body();
            $this->throwWithNotification($errorMessage);
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
            $errorMessage = "Tweet with media failed: " . $response->body();
            $this->throwWithNotification($errorMessage);
        }

        return $response->json();
    }

    protected function uploadMedia(string $mediaPath): string
    {
        $accessToken = $this->tokenManager->getAccessToken();

        if (!file_exists($mediaPath)) {
            $errorMessage = "Media file not found Twitter: {$mediaPath}";
            $this->throwWithNotification($errorMessage);
        }

        $mediaContent = file_get_contents($mediaPath);
        $mimeType = mime_content_type($mediaPath);

        // Upload media to Twitter's media endpoint
        $response = Http::withToken($accessToken)
            ->attach('media', $mediaContent, basename($mediaPath), ['Content-Type' => $mimeType])
            ->post('https://upload.twitter.com/1.1/media/upload.json');

        if (!$response->successful()) {
            $errorMessage = "Media upload failed for Twitter: " . $response->body();
            $this->throwWithNotification($errorMessage);
        }

        $responseData = $response->json();
        return $responseData['media_id_string'];
    }

    protected function throwWithNotification(string $message): void
    {
        $this->notifyError($message);
        throw new \Exception($message);
    }
}
