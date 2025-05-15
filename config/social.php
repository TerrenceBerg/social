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
            'public_profile',
            'email',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_read_user_content',
            'pages_manage_engagement',
            'pages_manage_metadata',
//            'groups_access_member_info',
//            'publish_to_groups',
            'instagram_basic',
            'instagram_content_publish',
        ],
    ],
    'instagram' => [
        'client_id' => env('INSTAGRAM_APP_ID'),
        'client_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI'),
        'scopes' => [
            'user_profile',
            'ads_management',
            'business_management',
            'user_media',
            'instagram_basic',
            'instagram_content_publish',
            'pages_read_engagement',
        ],
    ],
    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI'),
        'scopes' => [
            'user.info.basic',
            'user.info.email',
            'video.list',
            'video.upload',
            'video.publish',
        ],
    ],
    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect' => env('YOUTUBE_REDIRECT_URI'),
    ],
    'log_channel' => env('SOCIAL_MEDIA_LOG_CHANNEL', 'default'),
];
