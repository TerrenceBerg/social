<?php
namespace Tuna976\Social\Services;
use Illuminate\Support\Facades\Http;

class TwitterPostService
{
    public function __construct(
        protected TwitterTokenManager $tokenManager
    ) {}

    public function post(string $text): array
    {
        $accessToken = $this->tokenManager->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text
            ]);

        if (!$response->successful()) {
            throw new \Exception("Tweet failed: " . $response->body());
        }

        return $response->json();
    }
}
