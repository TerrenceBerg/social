<?php

namespace Tuna976\Social\Http\Livewire\TikTok;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Tuna976\Social\Models\SocialAuthUserToken;
use Tuna976\Social\Services\TikTok\TikTokUserService;

class UserPostedVideos extends Component
{
    public array $videos = [];
    public ?string $cursor = null;
    public bool $hasMore = false;
    public ?array $user = null;

    protected TikTokUserService $service;

    public function mount(TikTokUserService $service)
    {
        $this->service = $service;

        $token = SocialAuthUserToken::where('provider', 'tiktok')
            ->where('auth_user_id',Auth::user()->id)
            ->whereNotNull('extra_data')
            ->latest()
            ->first();

        if ($token && $token->extra_data) {
            $decoded = json_decode($token->extra_data, true);
            $this->user = is_array($decoded) ? $decoded : null;
        }

        $this->fetchVideos();
    }

    public function fetchMoreVideos()
    {
        $this->fetchVideos($this->cursor);
    }

    protected function fetchVideos(?string $cursor = null): void
    {
        try {
            $result = $this->service->getUserPostedVideos($cursor);
            $this->videos = array_merge($this->videos, $result['data']['videos'] ?? []);
            $this->cursor = $result['data']['cursor'] ?? null;
            $this->hasMore = !empty($this->cursor);
        } catch (\Exception $e) {
            $this->addError('tiktok', $e->getMessage());
        }
    }

    public function render()
    {
        return view('social::livewire.tiktok.user-posted-videos');
    }
}
