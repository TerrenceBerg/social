<?php

namespace Tuna976\Social\Services\Youtube;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

//    public function uploadVideo(string $videoPath, string $title, string $description = '', array $tags = [], string $privacy = 'private'): array
//    {
//        $accessToken = $this->getAccessToken();
//
//        $metadata = [
//            'snippet' => [
//                'title' => $title,
//                'description' => $description,
//                'tags' => ['video','976-Tuna'],
//                'categoryId' => '22',
//            ],
//            'status' => [
//                'privacyStatus' => $privacy,
//            ],
//        ];
//
//        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE);
//        if ($encodedMetadata === false) {
//            throw new Exception("Metadata encoding failed: " . json_last_error_msg());
//        }
//
//        $initResponse = Http::withToken($accessToken)
//            ->withHeaders([
//                'X-Upload-Content-Type' => 'video/mp4',
//                'Content-Type' => 'application/json; charset=UTF-8',
//            ])
//            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $metadata);
//
//        if (!$initResponse->successful()) {
//            throw new \Exception("YouTube init upload failed: " . $initResponse->body());
//        }
//
//        $uploadUrl = $initResponse->header('Location');
//        if (!$uploadUrl) {
//            throw new \Exception("YouTube did not return an upload URL.");
//        }
//
//        if (!file_exists($videoPath)) {
//            throw new \Exception("Video file does not exist at path: $videoPath");
//        }
//
//        $videoSize = filesize($videoPath);
//        $videoStream = fopen($videoPath, 'r');
//
//        $uploadResponse = Http::withToken($accessToken)
//            ->withHeaders([
//                'Content-Type' => 'video/mp4',
//                'Content-Length' => $videoSize,
//            ])
//            ->withBody($videoStream, 'video/mp4')
//            ->put($uploadUrl);
//
//        fclose($videoStream);
//
//        if (!$uploadResponse->successful()) {
//            throw new \Exception("YouTube video upload failed: " . $uploadResponse->body());
//        }
//
//        return $uploadResponse->json();
//    }

    /**
     * Upload a video to YouTube
     */
    public function uploadVideo($videoPath, $title, $description='', $tags = [], $privacyStatus = 'private')
    {
        try {
            // Check if file exists and is readable
            if (!file_exists($videoPath) || !is_readable($videoPath)) {
                throw new Exception("Video file doesn't exist or isn't readable: " . $videoPath);
            }

            // Get file size for headers
            $fileSize = filesize($videoPath);
            if ($fileSize <= 0) {
                throw new Exception("Invalid file size for video: " . $fileSize);
            }

            // Step 1: Get upload URL with resumable session URI
            $sessionUri = $this->initiateUploadSession($videoPath, $title, $description, $tags, $privacyStatus);

            // Step 2: Upload the video file using the session URI
            return $this->uploadVideoContent($sessionUri, $videoPath);
        } catch (Exception $e) {
            Log::error('YouTube upload error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initiate a resumable upload session
     */
    private function initiateUploadSession($videoPath, $title, $description, $tags, $privacyStatus)
    {
        $accessToken=$this->getAccessToken();
        // Convert tags to array if it's a string
        if (is_string($tags) && !empty($tags)) {
            $tags = [$tags];
        } elseif (!is_array($tags)) {
            $tags = [];
        }

        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'tags' => $tags,
                'categoryId' => '22', // People & Blogs category
            ],
            'status' => [
                'privacyStatus' => $privacyStatus, // This should be a string, not an array
                'embeddable' => true,
                'license' => 'youtube',
                'selfDeclaredMadeForKids' => true,
            ],
        ];

        // Log the request for debugging
        Log::debug('Initiating YouTube upload session', [
            'metadata' => $metadata,
            'file_size' => filesize($videoPath),
            'access_token' => substr($accessToken, 0, 10) . '...' // Partial token for security
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Upload-Content-Type: video/*',
            'X-Upload-Content-Length: ' . filesize($videoPath)
        ]);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        // Log the response for debugging
        Log::debug('YouTube upload session response', [
            'http_code' => $httpCode,
            'headers' => $headers,
            'body' => $body,
        ]);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('Failed to initiate upload session. HTTP Code: ' . $httpCode . '. Response: ' . $body);
        }

        // Extract the Location header, which contains the session URI
        preg_match('/Location: (.+)/i', $headers, $matches);
        if (!isset($matches[1])) {
            // Try an alternative method for extracting the location
            $headerLines = explode("\r\n", $headers);
            foreach ($headerLines as $headerLine) {
                if (stripos($headerLine, 'Location:') === 0) {
                    $matches[1] = trim(substr($headerLine, 9));
                    break;
                }
            }

            // If we still don't have a location, throw an error
            if (!isset($matches[1])) {
                throw new Exception('No upload URL received from YouTube. Headers: ' . $headers);
            }
        }

        return trim($matches[1]);
    }

    /**
     * Upload the video content to the session URI
     */
    private function uploadVideoContent($sessionUri, $videoPath)
    {
        $fileSize = filesize($videoPath);
        $chunkSize = 1 * 1024 * 1024; // 1MB chunks
        $fileHandle = fopen($videoPath, 'r');
        $bytesUploaded = 0;

        while ($bytesUploaded < $fileSize) {
            // Read the chunk from the file
            fseek($fileHandle, $bytesUploaded);
            $chunk = fread($fileHandle, min($chunkSize, $fileSize - $bytesUploaded));
            $chunkSize = strlen($chunk);

            // Set up the cURL request for this chunk
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sessionUri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Length: ' . $chunkSize,
                'Content-Range: bytes ' . $bytesUploaded . '-' .
                ($bytesUploaded + $chunkSize - 1) . '/' . $fileSize,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Handle response based on HTTP status code
            if ($httpCode == 308) {
                // Resume marker, continue uploading
                $bytesUploaded += $chunkSize;
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                // Success, video upload complete
                fclose($fileHandle);
                return json_decode($response, true);
            } else {
                // Error occurred
                fclose($fileHandle);
                throw new Exception('Error during video upload: ' . $response . ' (Code: ' . $httpCode . ')');
            }
        }

        fclose($fileHandle);
        throw new Exception('Upload completed but no successful response was received');
    }
    protected function getAccessToken(): string
    {
        return $this->storage->getAccessToken();
    }
}
