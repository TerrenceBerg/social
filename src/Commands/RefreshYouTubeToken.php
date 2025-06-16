<?php

namespace Tuna976\Social\Commands;

use Illuminate\Console\Command;
use Tuna976\Social\Services\DatabaseTokenStorage;
use Tuna976\Social\Services\YouTube\YouTubeOAuthService;
use Exception;
use Carbon\Carbon;

class RefreshYouTubeToken extends Command
{
    protected $signature = 'youtube:refresh-token';
    protected $description = 'Refresh YouTube access token using refresh token';

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

                $service = new YouTubeOAuthService($storage);
                $tokens = $service->refreshAccessToken($refreshToken);

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
