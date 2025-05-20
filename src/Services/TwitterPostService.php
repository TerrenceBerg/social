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
    ) {}

    public function post(string $text): array|bool
    {
        $accessToken = $this->tokenManager->getAccessToken();

        if (!$accessToken) {
            return $this->throwWithNotification('Failed to get Twitter access token.');
        }

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text
            ]);

        if (!$response->successful()) {
            return $this->throwWithNotification("Tweet failed: " . $response->body());
        }

        return $response->json();
    }

    public function postWithMedia(string $text, string $mediaPath): array|bool
    {
        $accessToken = $this->tokenManager->getAccessToken();

        if (!$accessToken) {
            return $this->throwWithNotification('Failed to get Twitter access token.');
        }

        $mediaId = $this->uploadMedia($mediaPath);
        if (!$mediaId) {
            return false; // uploadMedia already called throwWithNotification()
        }

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text,
                'media' => [
                    'media_ids' => [$mediaId]
                ]
            ]);

        if (!$response->successful()) {
            return $this->throwWithNotification("Tweet with media failed: " . $response->body());
        }

        return $response->json();
    }

    protected function uploadMedia(string $mediaPath): string|bool
    {
        $accessToken = $this->tokenManager->getAccessToken();

        if (!$accessToken) {
            return $this->throwWithNotification('Failed to get Twitter access token.');
        }

        if (!file_exists($mediaPath)) {
            return $this->throwWithNotification("Media file not found Twitter: {$mediaPath}");
        }

        $mediaContent = file_get_contents($mediaPath);
        $mimeType = mime_content_type($mediaPath);

        $response = Http::withToken($accessToken)
            ->attach('media', $mediaContent, basename($mediaPath), ['Content-Type' => $mimeType])
            ->post('https://upload.twitter.com/1.1/media/upload.json');

        if (!$response->successful()) {
            return $this->throwWithNotification("Media upload failed for Twitter: " . $response->body());
        }

        $responseData = $response->json();
        return $responseData['media_id_string'] ?? false;
    }

    protected function throwWithNotification(string $message): bool
    {
        $this->notifyError($message);
        return false;
    }
}
