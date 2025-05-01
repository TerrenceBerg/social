<?php

namespace Tuna976\Social;

use Illuminate\Support\ServiceProvider;
use Tuna976\Social\Commands\RefreshTwitterToken;
use Tuna976\Social\Contracts\TokenStorageInterface;
use Tuna976\Social\Services\DatabaseTokenStorage;
use Tuna976\Social\Services\TwitterOAuthService;
use Tuna976\Social\Services\TwitterPostService;
use Tuna976\Social\Services\TwitterTokenManager;

class SocialServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            RefreshTwitterToken::class,
        ]);
        $this->mergeConfigFrom(__DIR__ . '/../config/social.php', 'social');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/Database/migrations' => database_path('migrations'),
        ], 'social-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');
        // Bind interface to implementation
//        $this->app->singleton(TokenStorageInterface::class, CacheTokenStorage::class);

        // TokenManager depends on TokenStorageInterface
        $this->app->singleton(TwitterTokenManager::class, function ($app) {
            return new TwitterTokenManager($app->make(TokenStorageInterface::class));
        });

        $this->app->singleton(TokenStorageInterface::class, DatabaseTokenStorage::class);

        // OAuth service (if it needs dependencies, inject here)
        $this->app->singleton(TwitterOAuthService::class, function ($app) {
            return new TwitterOAuthService();
        });

        // Post service needs TwitterTokenManager
        $this->app->singleton(TwitterPostService::class, function ($app) {
            return new TwitterPostService(
                $app->make(TwitterTokenManager::class)
            );
        });
    }

    public function boot()
    {
//        if (!$this->app->runningInConsole()) {
//            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
//        }

        $this->publishes([
            __DIR__ . '/../config/social.php' => config_path('social.php'),
        ], 'social-config');
    }
}
