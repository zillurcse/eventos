<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application-level tenant scoping (architecture §4.1). Adds:
 *   - a global scope constraining every query to the resolved organization,
 *   - auto-fill of organization_id on create,
 *   - the belongsTo(Organization) relation.
 *
 * This is the first line of defence; Postgres RLS (keyed on the
 * app.current_organization GUC) is the database-level backstop.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $tenant = app(TenantContext::class);

            if ($tenant->has()) {
                $builder->where(
                    $builder->getModel()->getTable().'.organization_id',
                    $tenant->id(),
                );
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->organization_id)) {
                $tenant = app(TenantContext::class);

                if ($tenant->has()) {
                    $model->organization_id = $tenant->id();
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Query a specific organization, ignoring the ambient tenant scope. */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query
            ->withoutGlobalScope('organization')
            ->where($this->getTable().'.organization_id', $organizationId);
    }
}
