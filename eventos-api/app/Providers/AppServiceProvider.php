<?php

namespace App\Providers;

use App\Models\Organization;
use App\Services\Billing\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Per-tenant API rate limiting, keyed on org and sized by the plan's
        // api_rate quota (architecture §4.4). Falls back to per-user/IP.
        RateLimiter::for('api', function (Request $request) {
            $orgId = app(TenantContext::class)->id();

            if ($orgId) {
                $org = Organization::find($orgId);
                $rate = $org ? (app(FeatureGate::class)->quota($org, 'api_rate') ?? 300) : 300;

                return Limit::perMinute(max(30, (int) $rate))->by('org:'.$orgId);
            }

            $user = $request->user();

            return Limit::perMinute(60)->by($user ? 'user:'.$user->id : (string) $request->ip());
        });

        // Login brute-force brake. Two limits, Laravel enforces the stricter:
        //   - per account+IP: a shared-NAT office won't lock out one user
        //     because a colleague fat-fingered their password (6/min);
        //   - per IP: one host spraying many distinct emails is still bounded
        //     (20/min), which the account key alone would miss.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(6)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
