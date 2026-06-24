<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformStaff;
use App\Http\Middleware\ResolveParticipant;
use App\Http\Middleware\ResolvePartnerAdmin;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
            'partner.admin' => ResolvePartnerAdmin::class,
            'participant' => ResolveParticipant::class,
            'platform' => EnsurePlatformStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
