<?php

namespace App\Services\Gamification;

use App\Jobs\Gamification\AwardGamificationPointsJob;
use App\Models\Gamification;
use App\Models\GamificationPointEvent;
use App\Models\Participation;
use App\Support\GamificationActions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolves organizer-configured scores and awards points asynchronously via
 * {@see AwardGamificationPointsJob}. Controllers call {@see queue()} so the
 * request path stays light; the job does the ledger write + balance bump.
 */
class GamificationScorer
{
    /**
     * Queue a point award for a participation action. No-op when gamification
     * is off, the action is unknown, or the configured score is zero.
     *
     * @param  array<string, mixed>  $meta
     */
    public function queue(
        int $organizationId,
        int $eventId,
        int $participationId,
        string $actionKey,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): void {
        if (! GamificationActions::isValid($actionKey)) {
            return;
        }

        AwardGamificationPointsJob::dispatch(
            $organizationId,
            $eventId,
            $participationId,
            $actionKey,
            $subjectType,
            $subjectId,
            $meta,
        );
    }

    /**
     * Apply the award synchronously (used by the queued job, and by tests that
     * run QUEUE_CONNECTION=sync).
     *
     * @param  array<string, mixed>  $meta
     */
    public function awardNow(
        int $organizationId,
        int $eventId,
        int $participationId,
        string $actionKey,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): ?GamificationPointEvent {
        if (! GamificationActions::isValid($actionKey)) {
            return null;
        }

        $this->activateTenant($organizationId);

        $config = Gamification::query()
            ->withoutGlobalScope('organization')
            ->where('event_id', $eventId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $config || ! $config->enabled) {
            return null;
        }

        $scores = is_array($config->scores) ? $config->scores : [];
        $points = (int) ($scores[$actionKey] ?? 0);
        if ($points <= 0) {
            return null;
        }

        $idempotencyKey = $this->idempotencyKey($participationId, $actionKey, $subjectType, $subjectId);

        // Already credited (job retry / once-only action / subject already scored).
        $existing = GamificationPointEvent::query()
            ->withoutGlobalScope('organization')
            ->where('event_id', $eventId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $organizationId,
                $eventId,
                $participationId,
                $actionKey,
                $points,
                $subjectType,
                $subjectId,
                $idempotencyKey,
                $meta,
            ) {
                $event = GamificationPointEvent::query()->withoutGlobalScope('organization')->create([
                    'organization_id' => $organizationId,
                    'event_id' => $eventId,
                    'participation_id' => $participationId,
                    'action_key' => $actionKey,
                    'points' => $points,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => $meta ?: null,
                ]);

                Participation::query()
                    ->withoutGlobalScope('organization')
                    ->whereKey($participationId)
                    ->increment('points_total', $points);

                return $event;
            });
        } catch (Throwable $e) {
            // Unique violation from a concurrent worker — treat as already awarded.
            $existing = GamificationPointEvent::query()
                ->withoutGlobalScope('organization')
                ->where('event_id', $eventId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    private function idempotencyKey(
        int $participationId,
        string $actionKey,
        ?string $subjectType,
        ?int $subjectId,
    ): string {
        if (GamificationActions::isOnce($actionKey) || ($subjectType === null && $subjectId === null)) {
            return "{$participationId}:{$actionKey}";
        }

        return "{$participationId}:{$actionKey}:{$subjectType}:{$subjectId}";
    }

    private function activateTenant(int $organizationId): void
    {
        $tenant = app(TenantContext::class);
        $tenant->set($organizationId);
        DB::statement("set app.current_organization = '{$organizationId}'");
    }
}
