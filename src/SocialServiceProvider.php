<?php

namespace Tuna976\Social;

use Illuminate\Support\ServiceProvider;
use Tuna976\Social\Services\TwitterService;

class SocialServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(TwitterService::class, function ($app) {
            return new TwitterService();
        });

        // You can register other social services like Facebook and Instagram here
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/social.php' => config_path('social.php'),
        ]);
    }
}
