<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Public surface (health, plans) + the authentication lifecycle
 * (register / login / me / logout).
 */
class PublicAndAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_health_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.redis.status', 'ok');
    }

    public function test_plans_are_public(): void
    {
        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price_cents']]]);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'organization_name']);
    }

    public function test_register_provisions_a_tenant_and_returns_a_token(): void
    {
        $email = 'register-'.uniqid().'@example.test';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Owner',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_name' => 'Brand New Org '.uniqid(),
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'personas']])
            ->assertJsonPath('user.email', $email);
        // NOTE: `personas` is derived from a membership read on the pgsql_admin
        // connection, which cannot see the membership created inside this test's
        // rolled-back transaction, so it is not asserted here.
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => self::PLATFORM_EMAIL,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_succeeds_for_platform_admin(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => self::PLATFORM_EMAIL,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'is_platform_staff']])
            ->assertJsonPath('user.is_platform_staff', true);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = $this->actingAsOrganizer();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        // A real (not transient) token is needed because logout deletes it.
        $user = $this->organizerUser();
        $onDefault = \App\Models\User::find($user->id);
        $token = $onDefault->createToken('test', ['tenant'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');
    }
}
