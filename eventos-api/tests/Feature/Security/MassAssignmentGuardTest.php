<?php

namespace Tests\Feature\Security;

use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Participation;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guard test for the mass-assignment hardening (audit finding M-1).
 *
 * Two ratchets per sensitive model:
 *   (a) $fillable is non-empty (no silent revert to $guarded = []).
 *   (b) THE NEGATIVE ASSERTION — privilege / tenancy / money columns are ABSENT
 *       from $fillable; they may only be written via forceFill() at their
 *       trusted sites. A non-empty check alone would still pass if someone
 *       re-added 'role' next month — this absence check is the real lock.
 *
 * Membership.status and Participation.status are the deliberate exceptions
 * (validated lifecycle state, intentionally fillable) and are allow-listed, so
 * the 'status' ratchet still applies to Organization / Order / Exhibitor.
 *
 * Pure reflection over getFillable() — no database required.
 */
class MassAssignmentGuardTest extends TestCase
{
    /**
     * Dataset: (model, columns-that-must-NOT-be-fillable). Adding a model is one
     * line. The forbidden list per model = the privileged columns it actually
     * carries, minus the per-model fillable allowlist.
     *
     * @return array<string, array{class-string, list<string>}>
     */
    public static function sensitiveModels(): array
    {
        // Privileged columns each model actually has (derived from its schema).
        // A column absent here is simply not asserted for that model.
        $privilegedColumns = [
            Membership::class      => ['status'],
            Participation::class   => ['role', 'status'],
            Role::class            => [],
            Organization::class    => ['status', 'owner_user_id'],
            Payment::class         => ['status', 'amount_cents'],
            Order::class           => ['status', 'subtotal_cents', 'discount_cents', 'tax_cents', 'total_cents'],
            Subscription::class    => ['status'],
            Exhibitor::class       => ['status', 'created_by', 'updated_by'],
            ExhibitorMember::class => ['role', 'permissions'],
        ];

        // Privileged columns that ARE legitimately fillable — do NOT forbid.
        $fillableAllowlist = [
            Membership::class    => ['status'],   // Option 1: validated enum
            Participation::class => ['status'],   // attendee lifecycle state
        ];

        $cases = [];

        foreach ($privilegedColumns as $model => $columns) {
            $forbidden = array_values(array_diff($columns, $fillableAllowlist[$model] ?? []));
            $cases[class_basename($model)] = [$model, $forbidden];
        }

        return $cases;
    }

    #[DataProvider('sensitiveModels')]
    public function test_sensitive_model_locks_out_privileged_mass_assignment(string $model, array $forbidden): void
    {
        $fillable = (new $model)->getFillable();

        // (a) Must never revert to $guarded = [] (getFillable() would be empty).
        $this->assertNotEmpty(
            $fillable,
            "{$model} has an empty \$fillable — did it revert to \$guarded = []?"
        );

        // (b) The ratchet: privileged columns must stay out of $fillable.
        foreach ($forbidden as $column) {
            $this->assertNotContains(
                $column,
                $fillable,
                "{$model} must not mass-assign privileged column '{$column}' — set it via forceFill() at the trusted write site."
            );
        }
    }
}
