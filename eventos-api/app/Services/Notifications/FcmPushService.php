<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 sender. Uses a Google service-account JSON file referenced by
 * FIREBASE_CREDENTIALS (absolute path or storage-relative). When credentials
 * are missing, sends are skipped so local/dev stays quiet.
 */
class FcmPushService
{
    protected ?array $credentials = null;

    protected ?string $accessToken = null;

    protected ?int $tokenExpiresAt = null;

    public function configured(): bool
    {
        return $this->loadCredentials() !== null;
    }

    /**
     * @param  array<string, mixed>  $data  Stringable data payload for deep links
     * @return bool true when at least one token accepted the message
     */
    public function sendToUser(int $userId, string $title, ?string $body, array $data = []): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $tokens = DeviceToken::where('user_id', $userId)->pluck('token');
        if ($tokens->isEmpty()) {
            return false;
        }

        $sent = false;
        foreach ($tokens as $token) {
            if ($this->sendToToken((string) $token, $title, $body, $data)) {
                $sent = true;
                DeviceToken::where('token', $token)->update(['last_used_at' => now()]);
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToToken(string $token, string $title, ?string $body, array $data = []): bool
    {
        $creds = $this->loadCredentials();
        if ($creds === null) {
            return false;
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return false;
        }

        $projectId = $creds['project_id'] ?? null;
        if (! is_string($projectId) || $projectId === '') {
            return false;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => array_filter([
                    'title' => $title,
                    'body' => $body,
                ], fn ($v) => $v !== null && $v !== ''),
                'data' => $stringData,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    $payload,
                );

            if ($response->successful()) {
                return true;
            }

            $errorCode = data_get($response->json(), 'error.details.0.errorCode')
                ?? data_get($response->json(), 'error.status');

            // Prune dead tokens so we don't keep failing forever.
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)
                || $response->status() === 404) {
                DeviceToken::where('token', $token)->delete();
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FCM send exception: '.$e->getMessage());
        }

        return false;
    }

    protected function loadCredentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = config('services.fcm.credentials');
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = storage_path($path);
        }

        if (! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return $this->credentials = $json;
    }

    protected function accessToken(): ?string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $creds = $this->loadCredentials();
        if ($creds === null) {
            return null;
        }

        $now = time();
        $jwtHeader = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->b64(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $jwtHeader.'.'.$jwtClaim;
        $key = openssl_pkey_get_private($creds['private_key']);
        if ($key === false) {
            Log::warning('FCM: invalid private key in service account JSON');

            return null;
        }

        $signature = '';
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $unsigned.'.'.$this->b64($signature);

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('FCM OAuth failed', ['body' => $response->json()]);

                return null;
            }

            $this->accessToken = $response->json('access_token');
            $this->tokenExpiresAt = $now + (int) ($response->json('expires_in') ?? 3600);

            return $this->accessToken;
        } catch (\Throwable $e) {
            Log::warning('FCM OAuth exception: '.$e->getMessage());

            return null;
        }
    }

    protected function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
