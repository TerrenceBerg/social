<?php
namespace Tuna976\Social\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TwitterPostService
{
    public function __construct(
        protected TwitterTokenManager $tokenManager
    ) {}

    /**
     * @throws RuntimeException
     */
    public function post(string $text): array
    {
        try {
            // Validate input
            if (empty(trim($text))) {
                throw new RuntimeException('Tweet text cannot be empty');
            }

            if (mb_strlen($text) > 280) {
                throw new RuntimeException('Tweet text exceeds maximum length of 280 characters');
            }

            // Get access token
            $accessToken = $this->tokenManager->getAccessToken();
            if (empty($accessToken)) {
                throw new RuntimeException('Failed to obtain access token');
            }

            // Make the API request
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.twitter.com/2/tweets', [
                    'text' => $text
                ]);

            // Handle API response
            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0]['message'] ?? $response->body();
                throw new RuntimeException("Tweet failed: {$errorMessage}");
            }

            $responseData = $response->json();

            // Validate response format
            if (!isset($responseData['data']) || !is_array($responseData['data'])) {
                throw new RuntimeException('Invalid response format from Twitter API');
            }

            return $responseData;

        } catch (RuntimeException $e) {
            // Log specific runtime exceptions
            Log::error('Twitter post failed', [
                'error' => $e->getMessage(),
                'text' => $text,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;

        } catch (Throwable $e) {
            // Log unexpected exceptions
            Log::error('Unexpected error during Twitter post', [
                'error' => $e->getMessage(),
                'text' => $text,
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException(
                'Failed to post tweet: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }


    public function postWithMedia(string $text, string $mediaPath): array
    {
        try {
            if (empty(trim($text))) {
                throw new RuntimeException('Tweet text cannot be empty');
            }

            if (mb_strlen($text) > 280) {
                throw new RuntimeException('Tweet text exceeds maximum length of 280 characters');
            }

            $accessToken = $this->tokenManager->getAccessToken();
            if (empty($accessToken)) {
                throw new RuntimeException('Failed to obtain access token');
            }

            // First, upload the media
            $mediaId = $this->uploadMedia($mediaPath);

            // Then create the tweet with the media
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.twitter.com/2/tweets', [
                    'text' => $text,
                    'media' => [
                        'media_ids' => [$mediaId]
                    ]
                ]);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0]['message'] ?? $response->body();
                throw new RuntimeException("Tweet with media failed: {$errorMessage}");
            }

            $responseData = $response->json();
            if (!isset($responseData['data'])) {
                throw new RuntimeException('Invalid response format from Twitter API');
            }

            return $responseData;

        } catch (RuntimeException $e) {
            Log::error('Twitter media post failed', [
                'error' => $e->getMessage(),
                'text' => $text,
                'mediaPath' => $mediaPath,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected error during Twitter media post', [
                'error' => $e->getMessage(),
                'text' => $text,
                'mediaPath' => $mediaPath,
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to post tweet with media: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function uploadMedia(string $mediaPath): string
    {
        try {
            $accessToken = $this->tokenManager->getAccessToken();
            if (empty($accessToken)) {
                throw new RuntimeException('Failed to obtain access token for media upload');
            }

            if (!file_exists($mediaPath)) {
                throw new RuntimeException("Media file not found: {$mediaPath}");
            }

            if (!is_readable($mediaPath)) {
                throw new RuntimeException("Media file is not readable: {$mediaPath}");
            }

            $mediaContent = @file_get_contents($mediaPath);
            if ($mediaContent === false) {
                throw new RuntimeException("Failed to read media file: {$mediaPath}");
            }

            $mimeType = mime_content_type($mediaPath);
            if (!$mimeType) {
                throw new RuntimeException("Could not determine mime type for file: {$mediaPath}");
            }

            // Upload media to Twitter's media endpoint
            $response = Http::withToken($accessToken)
                ->attach('media', $mediaContent, basename($mediaPath), ['Content-Type' => $mimeType])
                ->post('https://upload.twitter.com/1.1/media/upload.json');

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0]['message'] ?? $response->body();
                throw new RuntimeException("Media upload failed: {$errorMessage}");
            }

            $responseData = $response->json();
            if (!isset($responseData['media_id_string'])) {
                throw new RuntimeException('Invalid media upload response: missing media_id_string');
            }

            return $responseData['media_id_string'];

        } catch (RuntimeException $e) {
            Log::error('Media upload failed', [
                'error' => $e->getMessage(),
                'mediaPath' => $mediaPath,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected error during media upload', [
                'error' => $e->getMessage(),
                'mediaPath' => $mediaPath,
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to upload media: ' . $e->getMessage(), 0, $e);
        }
    }
}
