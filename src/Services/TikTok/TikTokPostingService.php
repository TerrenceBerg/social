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
        $videoSize = filesize($videoPath);

        $initResponse = $this->initUpload($accessToken, Str::limit($caption, 150), $videoSize,$videoPath);

        $uploadUrl = $initResponse['data']['upload_url'];
        $videoId = $initResponse['data']['publish_id'];

        $this->uploadFile($uploadUrl, $videoPath);

        return $this->publishVideo($accessToken, $videoId, $caption);
    }

    public function postVideoFromUrl(string $videoUrl, string $caption): array
    {

        $accessToken = $this->getAccessToken();

        $init = Http::withToken($accessToken)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
                'post_info' => [
                    'title' => $caption,
                    'privacy_level' => 'SELF_ONLY',
                ],
            ]);
        if ($init->failed()) {
            $json = $init->json();
            $errorMessage = $json['error']['message'] ?? $init->body(); // Fallback to body
            throw new \Exception("TikTok Init Upload Failed: $errorMessage");
        }
        return $init->json();
    }

    public function postImagesToTikTok(array $imageUrls, string $caption): array
    {
        if (empty($imageUrls)) {
            throw new \Exception("No image URLs provided.");
        }

        foreach ($imageUrls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception("Invalid image URL: $url");
            }
        }

        $accessToken = $this->getAccessToken();

        $payload = [
            'media_type' => 'PHOTO',
            'post_mode' => 'DIRECT_POST',
            'post_info' => [
                'title' => $caption,
                'privacy_level' => 'SELF_ONLY',
                'disable_comment' => false,
                'auto_add_music' => false,
                'brand_content_toggle' => false,
                'brand_organic_toggle' => false
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'photo_images' => $imageUrls,
                'photo_cover_index' => 0
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json; charset=UTF-8',
        ])->post('https://open.tiktokapis.com/v2/post/publish/content/init/', $payload);

        if (!$response->successful()) {
            $errorBody = $response->body();
            throw new \Exception("TikTok Photo Post Failed: {$errorBody}");
        }

        return $response->json();
    }
    protected function initUpload(string $token, string $caption, int $videoSize, string $videoPath): array
    {
        $caption = mb_convert_encoding($caption, 'UTF-8', 'UTF-8');

        $chunkSize = 10 * 1024 * 1024; // 5MB
        $totalChunkCount = (int) ceil($videoSize / $chunkSize);
        $videoType = mime_content_type($videoPath);
        $payload = [
            'post_info' => [
                'title' => $caption,
                'privacy_level' => 'SELF_ONLY',
                'disable_duet' => false,
                'disable_comment' => false,
                'disable_stitch' => false,
            ],
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $videoSize,
                'video_type' => $videoType,
                'upload_type' => 'VIDEO_UPLOAD',
                'chunk_size' => $videoSize,
                'total_chunk_count' => 1,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post('https://open.tiktokapis.com/v2/post/publish/video/init/', $payload);

        $this->ensureSuccess($response, 'TikTok Init Upload Failed');

        return $response->json();
    }

    protected function uploadFile(string $uploadUrl, string $videoPath): void
    {
        $fileSize = filesize($videoPath);

        $headers = [
            'Content-Range'   => 'bytes 0-' . ($fileSize - 1) . '/' . $fileSize,
            'Content-Length'  => $fileSize,
            'Content-Type'    => 'video/mp4',
        ];
        if (!file_exists($videoPath)) {
            throw new \Exception("Video file does not exist: $videoPath");
        }

        if (!is_readable($videoPath)) {
            throw new \Exception("Video file is not readable: $videoPath");
        }
        $data = file_get_contents($videoPath);
        if ($data === false) {
            throw new \Exception("Failed to read video file at: $videoPath");
        }
        $video_data= mb_convert_encoding($data, 'UTF-8', 'UTF-8');

        if (!mb_check_encoding($video_data, 'UTF-8')) {
            throw new \Exception("Video file contains invalid UTF-8 characters.");
        }

        $upload = Http::timeout(720)
            ->withHeaders($headers)
            ->put($uploadUrl, $video_data);

        $this->ensureSuccess($upload, 'TikTok Video Upload Failed');
    }

    protected function publishVideo(string $token, string $videoId, string $caption): array
    {
        $publish = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post('https://open.tiktokapis.com/v2/video/publish/', [
                'video_id' => $videoId,
                'caption'  => $caption,
//                'caption'  => Str::limit($caption, 150),
            ]);

        $this->ensureSuccess($publish, 'TikTok Publish Failed');

        return $publish->json();
    }

    protected function validateVideo(string $videoPath): void
    {
        if (!file_exists($videoPath)) {
            throw new \Exception("Video file not found at path: {$videoPath}");
        }

        if (filesize($videoPath) > $this->maxFileSize) {
            throw new \Exception("Video file exceeds maximum size of 75MB.");
        }

        $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception("Invalid video format. Allowed: " . implode(', ', $this->allowedExtensions));
        }
    }

    protected function ensureSuccess($response, string $errorMessage): void
    {
        if (!$response->successful()) {
            throw new \Exception("{$errorMessage}: " . $response->body());
        }
    }

    protected function getAccessToken(): string
    {
        return $this->storage->getAccessToken();
    }
}
