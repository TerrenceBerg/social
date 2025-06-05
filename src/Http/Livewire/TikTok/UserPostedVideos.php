<?php

namespace Tuna976\Social\Http\Livewire\TikTok;

use Livewire\Component;
use Tuna976\Social\Services\TikTok\TikTokUserService;

class UserPostedVideos extends Component
{
    public array $videos = [];
    public ?string $cursor = null;
    public bool $hasMore = false;

    protected TikTokUserService $service;

    public function mount(TikTokUserService $service)
    {
        $this->service = $service;
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
