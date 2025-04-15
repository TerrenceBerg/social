<?php

return [
    'twitter' => [
        'client_id' => env('TWITTER_CONSUMER_KEY'),
        'client_secret' => env('TWITTER_CONSUMER_SECRET'),
        'access_token' => env('TWITTER_ACCESS_TOKEN'),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
        'auth_token' => env('TWITTER_ACCESS_TOKEN'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],
];
