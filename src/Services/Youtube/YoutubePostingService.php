<?php

namespace Tuna976\Social\Services\Youtube;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Contracts\TokenStorageInterface;

class YoutubePostingService
{
    use LogsToChannel;

    public function __construct(
        protected TokenStorageInterface $storage,
        protected string                $provider = 'youtube'
    )
    {
        $this->storage->setProvider($this->provider);
    }

    public function uploadVideo(string $videoPath, string $title, string $description = '', array $tags = [], string $privacy = 'private'): array
    {
        $accessToken = $this->getAccessToken();

        // Step 1: Initialize resumable session
        $initResponse = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => 'video/*',
                'Content-Type' => 'application/json',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                    'tags' => $tags,
                    'categoryId' => '22',
                ],
                'status' => [
                    'privacyStatus' => $privacy,
                ],
            ]);

        if (!$initResponse->successful()) {
            throw new Exception("YouTube init upload failed: " . $initResponse->body());
        }

        $uploadUrl = $initResponse->header('Location');
        if (!$uploadUrl) {
            throw new Exception("YouTube did not return an upload URL.");
        }

        // Step 2: Upload video binary
        $videoContent = file_get_contents($videoPath);
        $uploadResponse = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'video/*',
                'Content-Length' => strlen($videoContent),
            ])
            ->put($uploadUrl, $videoContent);

        if (!$uploadResponse->successful()) {
            throw new Exception("YouTube video upload failed: " . $uploadResponse->body());
        }

        return $uploadResponse->json();
    }

    protected function getAccessToken(): string
    {
        return $this->storage->getAccessToken();
    }
}
