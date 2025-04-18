<?php
namespace Tuna976\Social;

use Illuminate\Support\Str;
use Tuna976\Social\Models\SocialAuthToken;
use Tuna976\Social\Services\TwitterOAuthService;

class SocialManager
{
    protected string $provider;

    public function __construct(
        protected TwitterOAuthService $twitterOAuth
    ) {}

    public function withProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        $state = Str::random(40);

        if ($this->provider === 'twitter') {
            $verifier = $this->twitterOAuth->generatePkceVerifier();

            SocialAuthToken::create([
                'provider' => $this->provider,
                'state' => $state,
                'verifier' => $verifier,
            ]);

            $authUrl = $this->twitterOAuth->getAuthorizationUrl($state, $verifier);

            return redirect($authUrl);
        }

        throw new \Exception("Provider [{$this->provider}] is not supported.");
    }

    public function handleCallback(string $code, string $state): array
    {
        $tokenRecord = SocialAuthToken::where('provider', $this->provider)
            ->where('state', $state)
            ->firstOrFail();

        if ($this->provider === 'twitter') {
            $verifier = $tokenRecord->verifier;

            $tokenData = $this->twitterOAuth->getAccessToken($code, $verifier);

            $tokenRecord->update([
                'access_token' => $tokenData['tokens']['access_token'] ?? null,
                'refresh_token' => $tokenData['tokens']['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokenData['tokens']['expires_in'] ?? 3600),
                'user_id' => $tokenData['user']['id'] ?? null,
            ]);

            return [
                'user' => $tokenData['user'],
                'tokens' => $tokenData['tokens'],
            ];
        }

        throw new \Exception("Provider [{$this->provider}] is not supported.");
    }
}
