<?php

namespace Tuna976\Social\Services\Instagram;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Contracts\TokenStorageInterface;

class InstagramService
{
    public function __construct(
        protected TokenStorageInterface $storage,
        protected string $provider = 'instagram'
    ) {
        $this->storage->setProvider($this->provider);
    }

    public function postPhoto(string $instagramAccountId, string $imageUrl, ?string $caption = null): array
    {
        return $this->createAndPublishMedia($instagramAccountId, [
            'image_url' => $imageUrl,
            'caption' => $caption ?? '',
        ]);
    }

    public function postVideo(string $instagramAccountId, string $videoUrl, ?string $caption = null): array
    {
        return $this->createAndPublishMedia($instagramAccountId, [
            'video_url' => $videoUrl,
            'caption' => $caption ?? '',
            'media_type' => 'VIDEO',
        ]);
    }

    public function postCarousel(string $instagramAccountId, array $mediaUrls, ?string $caption = null): array
    {
        $accessToken = $this->getAccessToken();

        $children = [];

        // Step 1: Upload each media
        foreach ($mediaUrls as $url) {
            $mediaType = $this->determineMediaType($url);

            $createChild = Http::post("https://graph.facebook.com/v22.0/{$instagramAccountId}/media", [
                $mediaType === 'VIDEO' ? 'video_url' : 'image_url' => $url,
                'is_carousel_item' => true,
                'access_token' => $accessToken,
            ]);

            if (!$createChild->successful()) {
                throw new \Exception('Failed to create carousel child media: ' . $createChild->body());
            }

            $children[] = $createChild->json()['id'];
        }

        // Step 2: Create parent carousel container
        $createCarousel = Http::post("https://graph.facebook.com/v22.0/{$instagramAccountId}/media", [
            'children' => implode(',', $children),
            'caption' => $caption ?? '',
            'media_type' => 'CAROUSEL',
            'access_token' => $accessToken,
        ]);

        if (!$createCarousel->successful()) {
            throw new \Exception('Failed to create carousel container: ' . $createCarousel->body());
        }

        $creationId = $createCarousel->json()['id'];

        // Step 3: Publish the carousel
        return $this->publishMedia($instagramAccountId, $creationId);
    }

    public function postMedia(string $instagramAccountId, string $url, ?string $caption = null): array
    {
        $mediaType = $this->determineMediaType($url);

        return $this->createAndPublishMedia($instagramAccountId, [
            $mediaType === 'VIDEO' ? 'video_url' : 'image_url' => $url,
            'caption' => $caption ?? '',
        ]);
    }

    protected function createAndPublishMedia(string $instagramAccountId, array $payload): array
    {
        $accessToken = $this->getAccessToken();

        $createMedia = Http::post("https://graph.facebook.com/v22.0/{$instagramAccountId}/media", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$createMedia->successful()) {
            throw new \Exception('Failed to create Instagram media: ' . $createMedia->body());
        }

        $creationId = $createMedia->json()['id'];

        return $this->publishMedia($instagramAccountId, $creationId);
    }

    protected function publishMedia(string $instagramAccountId, string $creationId): array
    {
        $accessToken = $this->getAccessToken();

        $publishMedia = Http::post("https://graph.facebook.com/v22.0/{$instagramAccountId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $accessToken,
        ]);

        if (!$publishMedia->successful()) {
            throw new \Exception('Failed to publish Instagram media: ' . $publishMedia->body());
        }

        return $publishMedia->json();
    }

    protected function determineMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov']) ? 'VIDEO' : 'IMAGE';
    }

    protected function getAccessToken(): string
    {
        $accessToken = $this->storage->getAccessToken();

        if (!$accessToken) {
            throw new \Exception('Access token not found.');
        }

        return $accessToken;
    }
}
