<?php
namespace Tuna976\Social;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Models\SocialAuthToken;
use Tuna976\Social\Services\Facebook\FacebookOAuthService;
use Tuna976\Social\Services\Instagram\InstagramOAuthService;
use Tuna976\Social\Services\TikTok\TikTokOAuthService;
use Tuna976\Social\Services\TwitterOAuthService;


class SocialManager
{
    use LogsToChannel;
    protected string $provider;

    public function __construct(
        protected TwitterOAuthService $twitterOAuth,
        protected FacebookOAuthService $facebookOAuth,
        protected InstagramOAuthService $instagramService,
        protected TikTokOAuthService $tiktokService,
    ) {}

    public function withProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        SocialAuthToken::where('provider', $this->provider)->delete();
        $state = Str::random(40);

        SocialAuthToken::create([
            'provider' => $this->provider,
            'state' => $state,
            'verifier' => $this->provider === 'twitter' ? $this->twitterOAuth->generatePkceVerifier() : null,
        ]);

        if ($this->provider === 'twitter') {
            $verifier = SocialAuthToken::where('provider', 'twitter')
                ->where('state', $state)
                ->value('verifier');

            $authUrl = $this->twitterOAuth->getAuthorizationUrl($state, $verifier);
            return redirect($authUrl);
        }

        if ($this->provider === 'facebook') {
            $authUrl = $this->facebookOAuth->getAuthorizationUrl($state);
            return redirect($authUrl);
        }
        if ($this->provider === 'instagram') {
            $authUrl = $this->instagramService->getAuthorizationUrl($state, '');
            return redirect($authUrl);
        }
        if ($this->provider === 'tiktok') {
            $authUrl = $this->tiktokService->getAuthorizationUrl($state);
            return redirect($authUrl);
        }
        $errorMessage = "Provider [{$this->provider}] is not supported.";
        $this->logError($errorMessage);
        throw new \Exception($errorMessage);
    }

    public function handleCallback(string $code, string $state): array
    {
        $record = SocialAuthToken::where('provider', $this->provider)
            ->firstOrFail();

        return match ($this->provider) {
            'twitter' => $this->handleTwitter($record, $code),
            'facebook' => $this->handleFacebook($record, $code),
            'instagram' => $this->handleInstagram($record, $code),
            'tiktok' => $this->handleTikTok($record, $code),
            default => throw new \Exception("Provider [{$this->provider}] is not supported."),
        };
    }

    protected function handleTwitter($record, string $code): array
    {
        // Get the access token data
        $tokenData = $this->twitterOAuth->getAccessToken($code, $record->verifier);

        // Update the record with the new access token, refresh token, and expiration time
        $record->update([
            'access_token' => $tokenData['tokens']['access_token'] ?? null,
            'refresh_token' => $tokenData['tokens']['refresh_token'] ?? null,
            'expires_at' => Carbon::now()->addSeconds($tokenData['tokens']['expires_in'] ?? 3600),
            'user_id' => $tokenData['user']['id'] ?? null,
        ]);

        // Return the user and tokens information
        return [
            'user' => $tokenData['user'],
            'tokens' => $tokenData['tokens'],
        ];
    }

    protected function handleFacebook($record, string $code): array
    {
        $tokens = $this->facebookOAuth->getAccessToken($code);
        $user = $this->facebookOAuth->getUserProfile($tokens['access_token']);
        $pages = $this->facebookOAuth->getUserPages($tokens['access_token']);

        $record->update([
            'access_token' => $tokens['access_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            'user_id' => $user['id'] ?? null,
        ]);

        return [
            'user' => $user,
            'tokens' => $tokens,
            'pages' => $pages,
        ];
    }
    protected function handleInstagram($record, string $code): array
    {
        $tokens = $this->instagramService->getAccessToken($code);
        $tokens = $this->instagramService->getUserProfile($tokens['access_token']);

        $record->update([
            'access_token' => $tokens['access_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            'user_id' => $user['id'] ?? null,
        ]);

        return [
            'user' => $user,
            'tokens' => $tokens,
        ];
    }
    protected function handleTikTok($record, string $code): array
    {
        $tokens = $this->tiktokService->getAccessToken($code);
//        $user = $this->tiktokService->getUserProfile($tokens['access_token']);
        $user =['id'=>null];

        $record->update([
            'access_token' => $tokens['access_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            'user_id' => $user['id'] ?? null,
        ]);

        return [
            'user' => $user,
            'tokens' => $tokens
        ];
    }

}
