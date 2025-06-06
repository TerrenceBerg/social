<div>
    <div class="container py-4">
        {{-- User Profile --}}
        @if($user)
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body d-flex align-items-center">
                    <img src="{{ $user['avatar_url'] ?? 'https://via.placeholder.com/80' }}"
                         alt="Avatar"
                         class="rounded-circle me-3"
                         style="width: 80px; height: 80px; object-fit: cover;">
                    <div>
                        <h5 class="mb-1">{{ $user['display_name'] ?? 'Unknown User' }}</h5>
                        <small class="text-muted">
                            @ {{ $user['username'] ?? 'unknown' }}
                        </small>
                    </div>
                </div>
            </div>
        @endif
        {{-- Upload New Video --}}
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form wire:submit.prevent="uploadVideo">
                    <div class="mb-3">
                        <label for="video" class="form-label">Select Video</label>
                        <input type="file" wire:model="videoFile" accept="video/mp4" class="form-control" id="video">
                        @error('videoFile') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Video Title</label>
                        <input type="text" wire:model="title" class="form-control" id="title">
                        @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                        <span wire:loading.remove>Post to TikTok</span>
                        <span wire:loading>Posting...</span>
                    </button>
                </form>
            </div>
        </div>
        {{-- Videos Grid --}}
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
            @forelse ($videos as $video)
                <div class="col">
                    <div class="card shadow-sm h-100 border-0">
                        <video controls class="card-img-top" style="max-height: 250px; object-fit: cover;">
                            <source src="{{ $video['video_url'] }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-truncate mb-2" title="{{ $video['title'] ?? 'No title' }}">
                                {{ $video['title'] ?? 'No title' }}
                            </p>
                            <small class="text-muted mt-auto">
                                Posted: {{ \Carbon\Carbon::parse($video['create_time'] ?? now())->toDayDateTimeString() }}
                            </small>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center">
                    <p class="text-muted">No videos found.</p>
                </div>
            @endforelse
        </div>

        {{-- Load More Button --}}
        @if ($hasMore)
            <div class="text-center mt-4">
                <button wire:click="fetchMoreVideos" wire:loading.attr="disabled" class="btn btn-primary px-4">
                    <span wire:loading.remove>Load More</span>
                    <span wire:loading>Loading...</span>
                </button>
            </div>
        @endif
    </div>
</div>
