<?php

namespace Tuna976\Social;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tuna976\Social\Commands\RefreshTikTokToken;
use Tuna976\Social\Commands\RefreshTwitterToken;
use Tuna976\Social\Commands\RefreshYouTubeToken;
use Tuna976\Social\Contracts\TokenStorageInterface;
use Tuna976\Social\Http\Livewire\TikTok\UserPostedVideos;
use Tuna976\Social\Services\DatabaseTokenStorage;
use Tuna976\Social\Services\TwitterOAuthService;
use Tuna976\Social\Services\TwitterPostService;
use Tuna976\Social\Services\TwitterTokenManager;

class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/social.php', 'social');

        // Register commands
        $this->commands([
            RefreshTwitterToken::class,
            RefreshTikTokToken::class,
            RefreshYouTubeToken::class,
        ]);

        // Bind interfaces and services
        $this->app->singleton(TokenStorageInterface::class, DatabaseTokenStorage::class);

        $this->app->singleton(TwitterTokenManager::class, function ($app) {
            return new TwitterTokenManager($app->make(TokenStorageInterface::class));
        });

        $this->app->singleton(TwitterOAuthService::class, fn() => new TwitterOAuthService());

        $this->app->singleton(TwitterPostService::class, function ($app) {
            return new TwitterPostService($app->make(TwitterTokenManager::class));
        });
    }

    public function boot(): void
    {
        // Load views from package
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'social');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');

        // Register Livewire components
        Livewire::component('tiktok.user-posted-videos', UserPostedVideos::class);

        // Optional publishes
        $this->publishes([
            __DIR__ . '/../config/social.php' => config_path('social.php'),
        ], 'social-config');

        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/social'),
        ], 'social-views');

        $this->publishes([
            __DIR__ . '/Database/migrations' => database_path('migrations'),
        ], 'social-migrations');
    }
}
