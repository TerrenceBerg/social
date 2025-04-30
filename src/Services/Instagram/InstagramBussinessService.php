<?php

namespace Tuna976\Social\Services\Instagram;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Contracts\TokenStorageInterface;

class InstagramBussinessService
{
    public function __construct(
        protected string $instagramAccountId,
        protected string $pageAccessToken,
        protected TokenStorageInterface $storage,
        protected string $provider = 'instagram'
    ) {
        $this->storage->setProvider($this->provider);
    }

    public function postPhoto(string $imageUrl, ?string $caption = null): array
    {
        return $this->createAndPublishMedia([
            'image_url' => $imageUrl,
            'caption'   => $caption ?? '',
        ]);
    }

    public function postVideo(string $videoUrl, ?string $caption = null): array
    {
        return $this->createAndPublishMedia([
            'video_url' => $videoUrl,
            'caption'   => $caption ?? '',
        ]);
    }

    public function postCarousel(array $mediaUrls, ?string $caption = null): array
    {
        $accessToken = $this->getAccessToken();
        $children = [];

        // Step 1: Upload each media item
        foreach ($mediaUrls as $url) {
            $mediaType = $this->determineMediaType($url);

            $createChild = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", [
                $mediaType === 'VIDEO' ? 'video_url' : 'image_url' => $url,
                'is_carousel_item' => true,
                'access_token'     => $accessToken,
            ]);

            if (!$createChild->successful()) {
                throw new \Exception('Failed to create carousel child media: ' . $createChild->body());
            }

            $children[] = $createChild->json()['id'];
        }

        // Step 2: Create carousel container
        $createCarousel = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", [
            'children'      => implode(',', $children),
            'caption'       => $caption ?? '',
            'media_type'    => 'CAROUSEL',
            'access_token'  => $accessToken,
        ]);

        if (!$createCarousel->successful()) {
            throw new \Exception('Failed to create carousel container: ' . $createCarousel->body());
        }

        $creationId = $createCarousel->json()['id'];

        // Step 3: Publish
        return $this->publishMedia($creationId);
    }

    public function postMedia(string $url, ?string $caption = null): array
    {
        $mediaType = $this->determineMediaType($url);

        return $this->createAndPublishMedia([
            $mediaType === 'VIDEO' ? 'video_url' : 'image_url' => $url,
            'caption' => $caption ?? '',
        ]);
    }

    protected function createAndPublishMedia(array $payload): array
    {
        $accessToken = $this->getAccessToken();

        $createMedia = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$createMedia->successful()) {
            throw new \Exception('Failed to create Instagram media: ' . $createMedia->body());
        }

        $creationId = $createMedia->json()['id'];

        return $this->publishMedia($creationId);
    }

    protected function publishMedia(string $creationId): array
    {
        $accessToken = $this->getAccessToken();

        $publishMedia = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $accessToken,
        ]);

        if (!$publishMedia->successful()) {
            throw new \Exception('Failed to publish Instagram media: ' . $publishMedia->body());
        }

        return $publishMedia->json();
    }

    public function postReel(string $videoUrl, ?string $caption = null): array
    {
        $accessToken = $this->getAccessToken();

        $createReel = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", [
            'media_type'   => 'REELS',
            'video_url'    => $videoUrl,
            'caption'      => $caption ?? '',
            'access_token' => $accessToken,
        ]);

        if (!$createReel->successful()) {
            throw new \Exception('Failed to create Instagram Reel: ' . $createReel->body());
        }

        $creationId = $createReel->json()['id'];

        if (!$this->waitForMediaToBeReady($creationId, $accessToken)) {
            throw new \Exception('Media not ready for publishing after polling.');
        }

        return $this->publishMedia($creationId);
    }
    protected function determineMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, ['mp4', 'mov']) ? 'VIDEO' : 'IMAGE';
    }

    protected function getAccessToken(): string
    {
        return $this->pageAccessToken;
    }
    protected function waitForMediaToBeReady(string $creationId, string $accessToken, int $maxAttempts = 15, int $sleepSeconds = 4): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($sleepSeconds);

            $response = Http::get("https://graph.facebook.com/v22.0/{$creationId}?fields=status_code", [
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                $status = $response->json()['status_code'] ?? null;
                \Log::info("Media status for ID {$creationId}: " . $status);

                if ($status === 'FINISHED') {
                    return true;
                }

                if ($status === 'ERROR') {
                    throw new \Exception("Instagram media processing failed: ERROR");
                }
            } else {
                \Log::warning("Failed to fetch media status: " . $response->body());
            }
        }

        return false;
    }
}
