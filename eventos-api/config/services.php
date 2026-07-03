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

    /*
    | LiveKit (self-hosted) — native WebRTC SFU for Breakout Rooms.
    | `url` is the ws:// endpoint the browser connects to; `host` is the
    | server-side HTTP endpoint for RoomService RPC (differs inside Docker).
    */
    'livekit' => [
        'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),
        'host' => env('LIVEKIT_HOST', 'http://livekit:7880'),
        'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
        'api_secret' => env('LIVEKIT_API_SECRET', 'devsecret_change_me_min_32_chars_'),
        'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
    ],

];
