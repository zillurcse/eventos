<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every protected route must reject anonymous callers with 401. This is a
 * read-only guard sweep, so no DatabaseTransactions is needed.
 */
class AuthGuardsTest extends TestCase
{
    public static function protectedRoutes(): array
    {
        return [
            'me' => ['get', '/api/v1/auth/me'],
            'notifications' => ['get', '/api/v1/notifications'],
            'organization' => ['get', '/api/v1/organization'],
            'subscription' => ['get', '/api/v1/subscription'],
            'events index' => ['get', '/api/v1/events'],
            'create event' => ['post', '/api/v1/events'],
            'venues' => ['get', '/api/v1/venues'],
            'sessions' => ['get', '/api/v1/sessions'],
            'ticket types' => ['get', '/api/v1/ticket-types'],
            'members' => ['get', '/api/v1/members'],
            'forms' => ['get', '/api/v1/forms'],
            'email templates' => ['get', '/api/v1/email-templates'],
            'admin metrics' => ['get', '/api/v1/admin/metrics'],
            'admin users' => ['get', '/api/v1/admin/users'],
            'exhibitor space' => ['get', '/api/v1/exhibitor/space'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_protected_route_rejects_anonymous(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }
}
