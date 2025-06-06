<?php

namespace Tuna976\Social\Services\TikTok;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tuna976\Social\Concerns\HandlesErrorNotifications;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Models\SocialAuthUserToken;

class TikTokUserService
{
    use LogsToChannel;
    use HandlesErrorNotifications;

    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes;
    protected string $provider = 'tiktok';

    public function __construct()
    {
        $this->clientId = config('social.tiktok.client_id');
        $this->clientSecret = config('social.tiktok.client_secret');
        $this->redirectUri = config('social.tiktok.redirect');
        $this->scopes = config('social.tiktok.scopes');
    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
                'client_key' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'response_type' => 'code',
                'scope' => implode(',', $this->scopes),
                'state' => $state,
            ]);
    }

    public function getAccessToken(string $code, string $state): array
    {
        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ]);

        if (!$response->successful()) {
            $this->throwWithNotification('Failed to get TikTok access token: ' . $response->body());
        }

        $tokenData = $response->json();

        $user = $this->getUserProfile($tokenData['access_token']);
        $this->storeTokens($tokenData, $user);

        return $tokenData;
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://open.tiktokapis.com/v2/user/info/', [
            'fields' => 'open_id,username,avatar_url,display_name',
        ]);

        if (!$response->successful()) {
            $this->throwWithNotification('Failed to get TikTok user profile: ' . $response->body());
        }

        return $response->json()['data']['user'];
    }

    public function refreshToken(string $refreshToken): array
    {
        try {
            $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $user = $this->getUserProfile($tokenData['access_token']);
                $this->storeTokens($tokenData, $user);

                return $tokenData;
            }

            $this->throwWithNotification("Error refreshing TikTok token: " . $response->body());
        } catch (\Exception $e) {
            $this->throwWithNotification("Error refreshing TikTok token: " . $e->getMessage());
        }
    }

    protected function throwWithNotification(string $message): bool
    {
        $this->notifyError($message);
        throw new \Exception($message);
    }

    public function storeTokens(array $tokenData, ?array $user = null, $verifier = null): void
    {
        try {
            $token = SocialAuthUserToken::firstOrNew([
                'provider' => $this->provider,
                'auth_user_id' => auth()->id(),
            ]);

            $token->access_token = $tokenData['access_token'] ?? $token->access_token;
            $token->refresh_token = $tokenData['refresh_token'] ?? $token->refresh_token;

            if (isset($tokenData['expires_in'])) {
                $token->expires_at = Carbon::now()->addSeconds((int) $tokenData['expires_in']);
            } elseif (isset($tokenData['expires_at'])) {
                $timestamp = $tokenData['expires_at'];

                if (is_numeric($timestamp)) {
                    $token->expires_at = Carbon::createFromTimestamp((int)$timestamp);
                } else {
                    $token->expires_at = Carbon::parse($timestamp);
                }
            }

            $token->verifier = $verifier ?? $token->verifier;
            $token->extra_data = $user ? json_encode($user) : null;
            $token->user_id = $user['open_id'] ?? null;

            $token->save();
        } catch (\Exception $e) {
            $this->throwWithNotification('Failed to store tokens: ' . $e->getMessage() . ' [' . $this->provider . ']');
        }
    }

    public function getTokenRecord(): ?SocialAuthUserToken
    {
        return SocialAuthUserToken::where('provider', $this->provider)
            ->where('auth_user_id', auth()->id())
            ->latest()
            ->first();
    }

    public function postVideoFromUrl(string $videoUrl, string $caption): array
    {

        $accessToken = $this->getTokenRecord()?$this->getTokenRecord()->access_token:null;
        if ($accessToken) {
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
                $errorMessage = $json['error']['message'] ?? $init->body();
                $this->throwWithNotification($errorMessage);
            }
            return $init->json();
        }
        else{
            $errorMessage = 'No access token present for this user';
            $this->throwWithNotification($errorMessage);
        }

    }

    public function getUserPostedVideos(): array
    {
        $accessToken = $this->getTokenRecord()?->access_token;

        if (!$accessToken) {
            $this->throwWithNotification('No access token present for this user');
        }

        $response = Http::withToken($accessToken)
            ->get('https://open.tiktokapis.com/v2/post/list/', [
                'max_count' => 10, // You can increase this up to 50 if needed
            ]);

        if ($response->failed()) {
            $json = $response->json();
            $errorMessage = $json['error']['message'] ?? $response->body();
            $this->throwWithNotification("Failed to fetch TikTok posts: $errorMessage");
        }

        return $response->json();
    }

}
