<?php

namespace Tuna976\Social;


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

    public function redirect($user_id = null): \Illuminate\Http\RedirectResponse
    {
        SocialAuthUserToken::where('provider', $this->provider)
            ->where('auth_user_id', $user_id)
            ->delete();
        $encryptedState = 'enc:' . encrypt(json_encode(['user_id' => $user_id, 'source' => 'tiktok-auth']));

        SocialAuthUserToken::create([
            'provider' => $this->provider,
            'state' => $encryptedState,
            'auth_user_id' => $user_id,
        ]);

        if ($this->provider === 'tiktok') {
            $authUrl = $this->tiktokService->getAuthorizationUrl($encryptedState);
            return redirect($authUrl);
        }

        $errorMessage = "Provider [{$this->provider}] is not supported.";
        $this->logError($errorMessage);
        throw new \Exception($errorMessage);
    }

    public function handleCallback(string $code, string $state = null): array
    {
        try {
            $decodedState = json_decode(decrypt(substr($state, 4)), true);
        } catch (\Exception $e) {
            $this->logError('Failed to decrypt state: ' . $e->getMessage());
            throw new \Exception('Invalid or tampered state value');
        }

        $userId = $decodedState['user_id'] ?? null;

        $record = SocialAuthUserToken::where('provider', $this->provider)
            ->where('auth_user_id', $userId)
            ->firstOrFail();

        return match ($this->provider) {
            'tiktok' => $this->handleTikTok($record, $code, $state),
            default => throw new \Exception("Provider [{$this->provider}] is not supported."),
        };
    }

    protected function handleTikTok($record, string $code, string $state): array
    {
        $tokens = $this->tiktokService->getAccessToken($code, $state);

        $user = $this->tiktokService->getUserProfile($tokens['access_token']);

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
}
