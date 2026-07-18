<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformStaff;
use App\Http\Middleware\ResolveParticipant;
use App\Http\Middleware\ResolveExhibitorAdmin;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply `->middleware('tenant')` to routes that operate on tenant data.
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'perm' => EnsurePermission::class,
            'feature' => EnsureFeature::class,
            'exhibitor.admin' => ResolveExhibitorAdmin::class,
            'participant' => ResolveParticipant::class,
            'platform' => EnsurePlatformStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Give throttled API clients a machine-readable retry hint in the body,
        // not just the Retry-After header. The original rate-limit headers are
        // preserved on the response. retry_after stays null (never 0) when the
        // exception carries no Retry-After header.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

                return response()->json([
                    'message' => 'Too many attempts. Please try again later.',
                    'retry_after' => $retryAfter !== null ? (int) $retryAfter : null,
                ], 429)->withHeaders($e->getHeaders());
            }
        });
    })->create();
