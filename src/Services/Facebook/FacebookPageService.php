<?php
namespace Tuna976\Social\Services\Facebook;

use Illuminate\Support\Facades\Http;
use Exception;

class FacebookPageService
{
    protected string $graphApiVersion = 'v22.0';

    public function getInstagramBusinessAccountId(string $pageId,string $pageAccessToken): string
    {
        $response = Http::get("https://graph.facebook.com/{$this->graphApiVersion}/{$pageId}", [
            'fields' => 'instagram_business_account',
            'access_token' => $pageAccessToken,
        ]);
        if (!$response->successful()) {
            throw new Exception("Failed to fetch Instagram business account: " . $response->body());
        }
        $data = $response->json();
        if (!isset($data['instagram_business_account']['id'])) {
            throw new Exception("Instagram business account not linked to page ID {$pageId}.");
        }
        return $data['instagram_business_account']['id'];
    }
}
