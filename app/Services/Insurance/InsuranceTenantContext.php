<?php

namespace App\Services\Insurance;

use App\Models\InsuranceCase;
use App\Models\InsuranceRenewalOpportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InsuranceTenantContext
{
    public function organizationIds(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->activeOrganizations()
            ->pluck('organizations.id')
            ->filter()
            ->values();
    }

    public function scopeCases(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if ($user?->is_admin) {
            return $query;
        }

        $organizationIds = $this->organizationIds($user);

        if ($organizationIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('organization_id', $organizationIds);
    }

    public function scopeRenewals(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if ($user?->is_admin) {
            return $query;
        }

        $organizationIds = $this->organizationIds($user);

        if ($organizationIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('organization_id', $organizationIds);
    }

    public function case(int $caseId, ?User $user = null): InsuranceCase
    {
        return $this->scopeCases(InsuranceCase::query(), $user)->findOrFail($caseId);
    }

    public function renewal(int $renewalId, ?User $user = null): InsuranceRenewalOpportunity
    {
        return $this->scopeRenewals(InsuranceRenewalOpportunity::query(), $user)->findOrFail($renewalId);
    }

    public function primaryOrganizationId(?User $user = null): ?int
    {
        return $this->organizationIds($user)->first();
    }
}
