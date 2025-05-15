<?php
namespace Tuna976\Social\Services\Youtube;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class YoutubePostingService
{
    public function uploadVideo(string $accessToken, string $videoPath, string $title, string $description = '', array $tags = [], string $privacy = 'private'): array
    {
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
}
