<?php

namespace Tuna976\Social\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Tuna976\Social\Services\DatabaseTokenStorage;
use Tuna976\Social\Services\YouTube\YouTubeOAuthService;
use Exception;
use Carbon\Carbon;

class RefreshYouTubeToken extends Command
{
    protected $signature = 'youtube:refresh-token';
    protected $description = 'Refresh YouTube access token using refresh token';

    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;


    public function handle(): int
    {
        try {
            $provider = 'youtube';
            $storage = new DatabaseTokenStorage($provider);
            $expiresAt = $storage->getExpiresAt();

            if (!$expiresAt || now()->addMinutes(10)->gte($this->parseToCarbon($expiresAt))) {
                $refreshToken = $storage->getRefreshToken();

                if (!$refreshToken) {
                    $this->error("No refresh token available.");
                    return self::FAILURE;
                }

//                $service = new YouTubeOAuthService();
                $this->clientId = config('social.youtube.client_id');
                $this->clientSecret = config('social.youtube.client_secret');
                $this->redirectUri = config('social.youtube.redirect');

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

                if ($response->failed()) {
                    throw new \Exception('Failed to refresh YouTube token: ' . $response->body());
                }

                $tokens = $response->json();

//                    return $data;

//                $tokens = $service->refreshAccessToken($refreshToken);

                $storage->storeTokens($tokens);

                $this->info('YouTube token refreshed successfully.');
            } else {
                $this->info('Token still valid. No refresh needed.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error refreshing YouTube token: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function parseToCarbon($value): ?Carbon
    {
        if (!$value) return null;

        if ($value instanceof Carbon) return $value;

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int)$value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$value}");
            return null;
        }
    }
}
