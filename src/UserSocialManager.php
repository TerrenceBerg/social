<?php
namespace Tuna976\Social;

use Illuminate\Support\Str;
use Tuna976\Social\Concerns\LogsToChannel;
use Tuna976\Social\Models\SocialAuthUserToken;
use Tuna976\Social\Services\TikTok\TikTokUserService;


class UserSocialManager
{
    use LogsToChannel;
    protected string $provider;

    public function __construct(
        protected TikTokUserService $tiktokService,
    ) {}

    public function withProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        SocialAuthUserToken::where('provider', $this->provider)->delete();
        $state = Str::random(40);

        SocialAuthUserToken::create([
            'provider' => $this->provider,
            'state' => $state,
            'token_user_id'=>auth()->id(),
        ]);
        if ($this->provider === 'tiktok') {
            $authUrl = $this->tiktokService->getAuthorizationUrl($state);
            return redirect($authUrl);
        }
        $errorMessage = "Provider [{$this->provider}] is not supported.";
        $this->logError($errorMessage);
        throw new \Exception($errorMessage);
    }

    public function handleCallback(string $code, string $state=null): array
    {
        $record = SocialAuthUserToken::where('provider', $this->provider)
            ->firstOrFail();

        return match ($this->provider) {
            'tiktok' => $this->handleTikTok($record, $code),
            default => throw new \Exception("Provider [{$this->provider}] is not supported."),
        };
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
