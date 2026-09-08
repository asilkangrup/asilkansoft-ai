<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InsuranceTenantContext
{
    public function organizationIds(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->activeOrganizations()
            ->pluck('organizations.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function scope(Builder $query, string $column = 'organization_id'): Builder
    {
        if ((bool) auth()->user()?->is_admin) {
            return $query;
        }

        $ids = $this->organizationIds();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids->all());
    }

    public function primaryOrganizationId(): ?int
    {
        return $this->organizationIds()->first();
    }

    public function canAccessOrganization(?int $organizationId): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }

        if (! $organizationId) {
            return false;
        }

        return $this->organizationIds()->contains((int) $organizationId);
    }
}
