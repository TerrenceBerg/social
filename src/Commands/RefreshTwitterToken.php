<?php

namespace Tuna976\Social\Commands;

use Illuminate\Console\Command;
use Tuna976\Social\Services\TwitterOAuthService;
use Tuna976\Social\Services\DatabaseTokenStorage;
use Exception;

class RefreshTwitterToken extends Command
{
    protected $signature = 'twitter:refresh-token';
    protected $description = 'Refresh Twitter access token using refresh token';

    public function handle(): int
    {
        try {
            $provider = 'twitter';
            $storage = new DatabaseTokenStorage($provider);
            $expiresAt = $storage->getExpiresAt();

            if (!$expiresAt || now()->addMinutes(10)->gte($this->parseToCarbon($expiresAt))) {
                $refreshToken = $storage->getRefreshToken();

                if (!$refreshToken) {
                    $this->error("No refresh token available.");
                    return self::FAILURE;
                }

                $service = new TwitterOAuthService();
                $tokens = $service->refreshToken($refreshToken);
                $user = $service->getUserProfile($tokens['access_token']);
                $storage->storeTokens($tokens, $user);

                $this->info('Twitter token refreshed successfully.');
            } else {
                $this->info('Token still valid. No refresh needed.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error refreshing token: " . $e->getMessage());
            return self::FAILURE;
        }
    }
    protected function parseToCarbon($value): ?\Carbon\Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value;
        }

        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$value}");
            return null;
        }
    }
}
