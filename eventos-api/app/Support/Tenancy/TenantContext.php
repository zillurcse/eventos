<?php

namespace App\Support\Tenancy;

/**
 * Holds the organization resolved for the current request.
 *
 * Bound as a singleton (see AppServiceProvider). The ResolveTenant middleware
 * populates it from the authenticated membership; the BelongsToOrganization
 * global scope and the Postgres RLS GUC both read from it.
 */
class TenantContext
{
    protected ?int $organizationId = null;

    public function set(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function id(): ?int
    {
        return $this->organizationId;
    }

    public function has(): bool
    {
        return $this->organizationId !== null;
    }

    public function forget(): void
    {
        $this->organizationId = null;
    }

    /**
     * Run a callback as if a specific tenant were active, restoring the
     * previous tenant afterwards. Useful for queue jobs and platform tooling.
     */
    public function impersonate(?int $organizationId, callable $callback): mixed
    {
        $previous = $this->organizationId;
        $this->organizationId = $organizationId;

        try {
            return $callback();
        } finally {
            $this->organizationId = $previous;
        }
    }
}
