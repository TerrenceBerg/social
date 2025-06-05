<div>
    <div class="row">
        @forelse ($videos as $video)
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <video controls class="card-img-top" style="max-height: 300px;">
                        <source src="{{ $video['video_url'] }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <div class="card-body">
                        <p class="card-text text-truncate" title="{{ $video['title'] ?? 'No title' }}">
                            {{ $video['title'] ?? 'No title' }}
                        </p>
                        <small class="text-muted">Posted at: {{ \Carbon\Carbon::parse($video['create_time'] ?? now())->toDayDateTimeString() }}</small>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center">
                <p>No videos found.</p>
            </div>
        @endforelse
    </div>

    @if ($hasMore)
        <div class="text-center mt-4">
            <button wire:click="fetchMoreVideos" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove>Load More</span>
                <span wire:loading>Loading...</span>
            </button>
        </div>
    @endif
</div>
