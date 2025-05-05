<?php

namespace Tuna976\Social\Services\TikTok;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Contracts\TokenStorageInterface;

class TikTokPostingService
{
    use LogsToChannel;

    protected int $maxFileSize = 75 * 1024 * 1024;
    protected array $allowedExtensions = ['mp4', 'mov', 'avi'];

    public function __construct(
        protected TokenStorageInterface $storage,
        protected string $provider = 'tiktok'
    ) {
        $this->storage->setProvider($this->provider);
    }

    public function postVideo(string $videoPath, string $caption): array
    {
        $this->validateVideo($videoPath);

        $accessToken = $this->getAccessToken();

        // Step 1: Init Upload
        $init = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
                'post_info' => [
                    'title' => Str::limit($caption, 150),
                    'privacy_level' => 'MUTUAL_FOLLOW_FRIENDS',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'video_cover_timestamp_ms' => 1000,
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => filesize($videoPath),

                ],
            ]);

        if (!$init->successful()) {
//            $this->logError('TikTok Init Upload Failed: ' . $init->body());
            throw new \Exception('TikTok Init Upload Failed: ' . $init->body());
        }

        $uploadUrl = $init['upload_url'];
        $videoId   = $init['video_id'];

        // Step 2: Upload video
        $videoBytes = file_get_contents($videoPath);
        $upload = Http::withHeaders([
            'Content-Type' => 'application/octet-stream',
        ])->put($uploadUrl, $videoBytes);

        if (!$upload->successful()) {
//            $this->logError('TikTok Video Upload Failed: ' . $upload->body());
            throw new \Exception('TikTok Video Upload Failed: ' . $upload->body());
        }

        // Step 3: Publish
        $publish = Http::withToken($accessToken)->post(
            'https://open.tiktokapis.com/v2/post/publish/video/',
            [
                'video_id' => $videoId,
                'caption'  => Str::limit($caption, 150),
            ]
        );

        if (!$publish->successful()) {
//            $this->logError('TikTok Publish Failed: ' . $publish->body());
            throw new \Exception('TikTok Publish Failed: ' . $publish->body());
        }

//        $this->logInfo('TikTok video posted successfully with ID: ' . $publish['video_id'] ?? 'unknown');
        return $publish->json();
    }


    public function postVideoFromUrl(string $videoUrl, string $caption): array
    {
        $accessToken = $this->getAccessToken();

        // Step 1: Init Upload via URL
        $init = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ]);

        if (!$init->successful()) {
//            $this->logError('TikTok Init Upload Failed: ' . $init->body());
            throw new \Exception('TikTok Init Upload Failed: ' . $init->body());
        }

        $videoId = $init['video_id'];

        // Step 2: Publish the video
        $publish = Http::withToken($accessToken)->post(
            'https://open.tiktokapis.com/v2/post/publish/video/',
            [
                'video_id' => $videoId,
                'caption'  => $caption,
            ]
        );

        if (!$publish->successful()) {
//            $this->logError('TikTok Publish Failed: ' . $publish->body());
            throw new \Exception('TikTok Publish Failed: ' . $publish->body());
        }

//        $this->logInfo('TikTok video posted successfully with ID: ' . $publish['video_id'] ?? 'unknown');
        return $publish->json();
    }

    protected function validateVideo(string $videoPath): void
    {
        if (!file_exists($videoPath)) {
            throw new \Exception("Video file not found at path: {$videoPath}");
        }

        $size = filesize($videoPath);
        if ($size > $this->maxFileSize) {
            throw new \Exception("Video file exceeds maximum size of 75MB.");
        }

        $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception("Invalid video format. Allowed: " . implode(', ', $this->allowedExtensions));
        }
    }

    protected function getAccessToken(): string
    {
        return $this->storage->getAccessToken();
    }
}
