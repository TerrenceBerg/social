<?php

namespace Tuna976\Social\Services\Facebook;

use Illuminate\Support\Facades\Http;
use Exception;
use Tuna976\Social\Concerns\LogsToChannel;

class FacebookPageService
{
    use LogsToChannel;
    protected string $graphApiVersion = 'v22.0';

    public function getInstagramBusinessAccountId(string $pageId, string $pageAccessToken): string
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphApiVersion}/{$pageId}", [
            'fields' => 'instagram_business_account',
            'access_token' => $pageAccessToken,
        ]);
        if (!$response->successful()) {
            $errorMessage = "Failed to fetch Instagram business account: " . $response->body();
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }
        $data = $response->json();
        if (!isset($data['instagram_business_account']['id'])) {
            $errorMessage = "Instagram business account not linked to page ID {$pageId}.";
            $this->logError($errorMessage);
            throw new \Exception($errorMessage);
        }
        return $data['instagram_business_account']['id'];
    }
}
