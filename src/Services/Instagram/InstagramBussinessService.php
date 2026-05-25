<?php

namespace Tuna976\Social\Services\Instagram;

use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Contracts\TokenStorageInterface;

class InstagramBussinessService
{
    use LogsToChannel;
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
            'media_type' => 'REELS',
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
                $errorMessage = 'Failed to create carousel child media: ' . $createChild->body();
                $this->logError($errorMessage);
                throw new \Exception($errorMessage);
            }
            $childId = $createChild->json()['id'];
//            $this->waitForMediaToBeReady($childId, $accessToken);

            $children[] = $childId;

        }

        // Step 2: Create carousel container
        $createCarousel = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", [
            'children'      => implode(',', $children),
            'caption'       => $caption ?? '',
            'media_type'    => 'CAROUSEL',
            'access_token'  => $accessToken,
        ]);

        if (!$createCarousel->successful()) {
            $errorMessage = 'Failed to create carousel container: ' . $createCarousel->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);

        }

        $creationId = $createCarousel->json()['id'];
//        $this->waitForMediaToBeReady($creationId, $accessToken);


        // Step 3: Publish
        return $this->publishMedia($creationId);
    }

    public function postMedia(string $url, ?string $caption = null): array
    {
        $mediaType = $this->determineMediaType($url);

        $payload = [
            'caption' => $caption ?? '',
        ];

        if ($mediaType === 'VIDEO') {

            $payload['media_type'] = 'REELS';
            $payload['video_url'] = $url;

        } else {

            $payload['image_url'] = $url;
        }

        return $this->createAndPublishMedia($payload);
    }

    protected function
    createAndPublishMedia(array $payload): array
    {
        $accessToken = $this->getAccessToken();

        $createMedia = Http::post("https://graph.facebook.com/v22.0/{$this->instagramAccountId}/media", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$createMedia->successful()) {
            $errorMessage = 'Failed to create Instagram media: ' . $createMedia->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        $creationId = $createMedia->json()['id'];
//        $this->waitForMediaToBeReady($creationId, $accessToken);

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
            $errorMessage = 'Failed to publish Instagram media: ' . $publishMedia->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
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
            $errorMessage = 'Failed to create Instagram Reel: ' . $createReel->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        $creationId = $createReel->json()['id'];

//        $this->waitForMediaToBeReady($creationId, $accessToken, 30, 5);

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
    protected function waitForMediaToBeReady(
        string $creationId,
        string $accessToken,
        int $maxAttempts = 20,
        int $sleepSeconds = 5
    ): bool {

        for ($i = 0; $i < $maxAttempts; $i++) {

            sleep($sleepSeconds);

            $response = Http::timeout(30)->get(
                "https://graph.facebook.com/v22.0/{$creationId}",
                [
                    'fields'       => 'status_code,status',
                    'access_token' => $accessToken,
                ]
            );

            if (!$response->successful()) {

                $errorMessage = "Failed to fetch media status: " . $response->body();

                $this->logError($errorMessage);

                throw new \Exception($errorMessage);
            }

            $data = $response->json();

            $status = $data['status_code'] ?? null;

            $this->logInfo(
                "Instagram media processing status for {$creationId}: {$status}"
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */
            if (in_array($status, ['FINISHED', 'PUBLISHED'])) {

                $this->logInfo(
                    "Instagram media is ready for publishing: {$creationId}"
                );

                return true;
            }

            /*
            |--------------------------------------------------------------------------
            | FAILURE
            |--------------------------------------------------------------------------
            */
            if (in_array($status, ['ERROR', 'EXPIRED'])) {

                $errorMessage =
                    "Instagram media processing failed for {$creationId}. Status: {$status}";

                $this->logError($errorMessage);

                throw new \Exception($errorMessage);
            }

            /*
            |--------------------------------------------------------------------------
            | STILL PROCESSING
            |--------------------------------------------------------------------------
            */
            $this->logInfo(
                "Instagram media still processing for {$creationId}. Attempt "
                . ($i + 1)
                . "/{$maxAttempts}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TIMEOUT
        |--------------------------------------------------------------------------
        */
        $errorMessage =
            "Instagram media processing timeout for media ID {$creationId}";

        $this->logError($errorMessage);

        throw new \Exception($errorMessage);
    }
}
