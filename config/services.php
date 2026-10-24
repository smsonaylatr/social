<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'deploy' => [
        'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Official Social Media Partner OAuth 2.0 & API Integrations
    |--------------------------------------------------------------------------
    */

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI', '/oauth/callback/tiktok'),
        'webhook_secret' => env('TIKTOK_WEBHOOK_SECRET', 'socialflow_tiktok_verify'),
        'api_base' => 'https://open.tiktokapis.com/v2',
    ],

    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID', env('META_APP_ID')),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET', env('META_APP_SECRET')),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', '/oauth/callback/instagram'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'socialflow_meta_verify'),
        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'api_base' => 'https://graph.facebook.com/v19.0',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID', env('META_APP_ID')),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET', env('META_APP_SECRET')),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/oauth/callback/facebook'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'socialflow_meta_verify'),
        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'api_base' => 'https://graph.facebook.com/v19.0',
    ],

    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'redirect' => env('YOUTUBE_REDIRECT_URI', '/oauth/callback/youtube'),
        'api_base' => 'https://www.googleapis.com/youtube/v3',
    ],

    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI', '/oauth/callback/x'),
        'webhook_secret' => env('X_WEBHOOK_SECRET', 'socialflow_x_verify'),
        'api_base' => 'https://api.twitter.com/2',
    ],

    'pinterest' => [
        'client_id' => env('PINTEREST_CLIENT_ID'),
        'client_secret' => env('PINTEREST_CLIENT_SECRET'),
        'redirect' => env('PINTEREST_REDIRECT_URI', '/oauth/callback/pinterest'),
        'api_base' => 'https://api.pinterest.com/v5',
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI', '/oauth/callback/linkedin'),
        'api_version' => env('LINKEDIN_API_VERSION', '202401'),
        'api_base' => 'https://api.linkedin.com/rest',
    ],

    'whatsapp' => [
        'client_id' => env('WHATSAPP_CLIENT_ID', env('META_APP_ID')),
        'client_secret' => env('WHATSAPP_CLIENT_SECRET', env('META_APP_SECRET')),
        'redirect' => env('WHATSAPP_REDIRECT_URI', '/oauth/callback/whatsapp'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'socialflow_meta_verify'),
        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'api_base' => 'https://graph.facebook.com/v19.0',
    ],

    'telegram' => [
        'client_id' => env('TELEGRAM_BOT_ID'),
        'client_secret' => env('TELEGRAM_BOT_TOKEN'),
        'redirect' => env('TELEGRAM_REDIRECT_URI', '/oauth/callback/telegram'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'socialflow_tg_verify'),
        'api_base' => 'https://api.telegram.org',
    ],

];
