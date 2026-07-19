<?php

// Auth is 100% Bearer (Sanctum PATs) — see AuthController::login / useApi.ts.
// Bearer tokens are NOT ambient: browsers never attach them cross-origin, so a
// wildcard origin cannot enable credential-riding (unlike cookies). This is why
// allowed_origins: ['*'] + supports_credentials: false is safe here AND covers
// every tenant subdomain + custom organizer domain with zero maintenance.
// ⚠️ This safety DEPENDS on auth staying Bearer-only. If any Sanctum STATEFUL
//    COOKIE flow is ever re-enabled, '*' becomes a CSRF hole — revisit this file
//    (switch to an explicit allowlist/pattern + supports_credentials: true).
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
