<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Liveness/readiness probe for the API.
     * Verifies the three external dependencies the app needs to function.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::connection()->getPdo()),
            'redis'    => $this->probe(fn () => Redis::connection()->ping()),
            'storage'  => $this->probe(fn () => Storage::disk('s3')->exists('__health__')),
        ];

        $ok = ! collect($checks)->contains(fn ($c) => $c['status'] === 'error');

        return response()->json([
            'app'    => config('app.name'),
            'status' => $ok ? 'ok' : 'degraded',
            'time'   => now()->toIso8601String(),
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    /** Run a probe, reporting ok/error without leaking a stack trace. */
    private function probe(callable $fn): array
    {
        try {
            $fn();

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
