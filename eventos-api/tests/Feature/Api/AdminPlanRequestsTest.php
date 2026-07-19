<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Super-admin review of organizer plan-change requests. The request row and the
 * subscription live on the committed (BYPASSRLS) admin connection — like the
 * other cross-tenant fixtures — so the test seeds and cleans them up directly.
 */
class AdminPlanRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private function seedPendingRequest(int $fromPlanId, int $toPlanId): PlanChangeRequest
    {
        $org = $this->tenantOrg;

        // Clear any row a previously-aborted run may have left (one-pending-per-org).
        PlanChangeRequest::on(self::ADMIN_CONN)->where('organization_id', $org->id)->delete();

        $req = (new PlanChangeRequest)->setConnection(self::ADMIN_CONN);
        $req->forceFill([
            'organization_id' => $org->id,
            'current_plan_id' => $fromPlanId,
            'requested_plan_id' => $toPlanId,
            'status' => 'pending',
        ])->save();

        return $req;
    }

    public function test_plan_requests_require_platform_staff(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/admin/plan-change-requests')->assertForbidden();
    }

    public function test_platform_can_list_pending_requests(): void
    {
        $this->organizerUser(); // provision the committed org + Free subscription
        $free = Plan::on(self::ADMIN_CONN)->where('slug', 'free')->firstOrFail();
        $pro = Plan::on(self::ADMIN_CONN)->where('slug', 'pro')->firstOrFail();
        $req = $this->seedPendingRequest($free->id, $pro->id);

        $this->actingAsPlatform();

        $this->getJson('/api/v1/admin/plan-change-requests?status=pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $req->uuid, 'status' => 'pending']);

        PlanChangeRequest::on(self::ADMIN_CONN)->whereKey($req->id)->delete();
    }

    public function test_approving_a_request_activates_the_plan(): void
    {
        $this->organizerUser();
        $free = Plan::on(self::ADMIN_CONN)->where('slug', 'free')->firstOrFail();
        $pro = Plan::on(self::ADMIN_CONN)->where('slug', 'pro')->firstOrFail();
        $req = $this->seedPendingRequest($free->id, $pro->id);

        $this->actingAsPlatform();

        $this->postJson("/api/v1/admin/plan-change-requests/{$req->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $sub = Subscription::on(self::ADMIN_CONN)
            ->where('organization_id', $this->tenantOrg->id)->latest('id')->first();
        $this->assertSame($pro->id, (int) $sub->plan_id);

        PlanChangeRequest::on(self::ADMIN_CONN)->whereKey($req->id)->delete();
    }

    public function test_rejecting_a_request_leaves_the_plan_untouched(): void
    {
        $this->organizerUser();
        $free = Plan::on(self::ADMIN_CONN)->where('slug', 'free')->firstOrFail();
        $pro = Plan::on(self::ADMIN_CONN)->where('slug', 'pro')->firstOrFail();
        $req = $this->seedPendingRequest($free->id, $pro->id);

        $this->actingAsPlatform();

        $this->postJson("/api/v1/admin/plan-change-requests/{$req->uuid}/reject", ['review_note' => 'Not now'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $sub = Subscription::on(self::ADMIN_CONN)
            ->where('organization_id', $this->tenantOrg->id)->latest('id')->first();
        $this->assertSame($free->id, (int) $sub->plan_id);

        PlanChangeRequest::on(self::ADMIN_CONN)->whereKey($req->id)->delete();
    }
}
