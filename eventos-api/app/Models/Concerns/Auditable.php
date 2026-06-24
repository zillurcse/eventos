<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes an immutable audit_logs row on create/update/delete with value diffs
 * (architecture §6.9). audit_logs is append-only and month-partitioned.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->writeAudit('created', [], $m->getAttributes()));
        static::updated(fn (Model $m) => $m->writeAudit('updated', $m->getOriginal(), $m->getChanges()));
        static::deleted(fn (Model $m) => $m->writeAudit('deleted', $m->getOriginal(), []));
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        $orgId = $this->organization_id ?? app(TenantContext::class)->id();

        // Audit is system-level (append-only, not tenant-RLS-constrained on
        // write) and must succeed even outside a request GUC (console/queue),
        // so it writes via the BYPASSRLS migrator connection.
        DB::connection('pgsql_admin')->table('audit_logs')->insert([
            'organization_id' => $orgId,
            'actor_type'      => optional(auth()->user())?->getMorphClass(),
            'actor_id'        => auth()->id(),
            'event'           => $event,
            'auditable_type'  => $this->getMorphClass(),
            'auditable_id'    => $this->getKey(),
            'old_values'      => empty($old) ? null : json_encode($this->scrub($old)),
            'new_values'      => empty($new) ? null : json_encode($this->scrub($new)),
            'ip_address'      => request()->ip(),
            'user_agent'      => substr((string) request()->userAgent(), 0, 1000),
            'created_at'      => now(),
        ]);
    }

    /** Never record secrets in the audit trail. */
    protected function scrub(array $values): array
    {
        foreach (['password', 'remember_token', 'mfa_secret', 'qr_token'] as $secret) {
            unset($values[$secret]);
        }

        return $values;
    }
}
