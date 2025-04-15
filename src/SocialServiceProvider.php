<?php

namespace Tuna976\Social;

use Illuminate\Support\ServiceProvider;
use Tuna976\Social\Services\TwitterOAuthService;
use Tuna976\Social\Services\TwitterService;

class SocialServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/social.php', 'social');

        $this->app->singleton(TwitterService::class, function ($app) {
            return new TwitterService();
        });

        $this->app->singleton(TwitterOAuthService::class, function ($app) {
            return new TwitterOAuthService();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/social.php' => config_path('social.php'),
        ], 'social-config');
    }
}
