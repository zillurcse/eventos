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

    /*
    | Zoom Meeting SDK — lets a "zoom"-hosted session embed inside the event
    | page via the Web SDK (Zoom pages refuse to be iframed). Create a "Meeting
    | SDK" app at marketplace.zoom.us to get the Client ID (SDK Key) + secret.
    */
    'zoom' => [
        'sdk_key' => env('ZOOM_SDK_KEY'),
        'sdk_secret' => env('ZOOM_SDK_SECRET'),
    ],

    /*
    | Jitsi — embedded in-page video for a "jitsi"-hosted session.
    |
    | The public meet.jit.si will NOT work for a real event: it requires an
    | authenticated moderator to start a room, so attendees are parked on
    | "waiting for a moderator" forever. Point this at your own Jitsi and give
    | it a signing key; the API then issues each viewer a JWT that names the
    | session host as moderator and everyone else as a guest.
    |
    | Two flavours, picked automatically:
    |   JaaS (8x8.vc)  — app_id = "vpaas-magic-cookie-…", kid + private_key (RS256)
    |   Self-hosted    — app_id = prosody app id, app_secret (HS256)
    | With neither set we fall back to anonymous meet.jit.si (dev only).
    */
    /*
    | Agora — embedded broadcast video for an "agora"-hosted session. Better
    | than Jitsi for large one-to-many rooms: the host publishes, everyone else
    | subscribes, so an audience of thousands costs one upstream.
    |
    | The App Certificate must stay server-side: the API signs a short-lived
    | AccessToken2 per viewer that encodes their role (host may publish,
    | attendee may only subscribe). Organizers normally set this per event in
    | Settings › Video; this is the platform-wide fallback.
    */
    'agora' => [
        'app_id' => env('AGORA_APP_ID'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE'),
        'token_ttl' => (int) env('AGORA_TOKEN_TTL', 7200),
    ],

    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),
        'app_id' => env('JITSI_APP_ID'),
        'app_secret' => env('JITSI_APP_SECRET'),   // self-hosted (HS256)
        'kid' => env('JITSI_KID'),                 // JaaS (RS256)
        'private_key' => env('JITSI_PRIVATE_KEY'), // JaaS (RS256), PEM
        'token_ttl' => (int) env('JITSI_TOKEN_TTL', 7200),
    ],

];
