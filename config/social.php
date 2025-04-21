<?php

return [
    'twitter' => [
        'client_id' => env('TWITTER_CONSUMER_KEY'),
        'client_secret' => env('TWITTER_CONSUMER_SECRET'),
        'access_token' => env('TWITTER_ACCESS_TOKEN'),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
        'auth_token' => env('TWITTER_ACCESS_TOKEN'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
        'scopes' => [
            'tweet.read',
            'users.read',
            'tweet.write',
            'offline.access',
        ],
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
        'scopes' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_read_user_content',
            'instagram_basic',
            'instagram_content_publish'
        ],
    ]
];
