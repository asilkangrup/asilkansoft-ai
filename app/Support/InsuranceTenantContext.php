<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InsuranceTenantContext
{
    public function organizationIds(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user || (bool) $user->is_admin) {
            return collect();
        }

        return $this->insuranceOrganizations($user)
            ->pluck('id')
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

    public function canUseInsurance(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) $user->is_admin) {
            return true;
        }

        return $this->insuranceOrganizations($user)->isNotEmpty();
    }

    public function isInsuranceOnly(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || (bool) $user->is_admin) {
            return false;
        }

        $organizations = $user->activeOrganizations()->get();

        return $organizations->isNotEmpty()
            && $organizations->every(
                fn (Organization $organization): bool => (bool) data_get($organization->settings, 'insurance_only', false)
            );
    }

    private function insuranceOrganizations(User $user): Collection
    {
        return $user->activeOrganizations()
            ->get()
            ->filter(fn (Organization $organization): bool => $this->organizationEnablesInsurance($organization))
            ->values();
    }

    private function organizationEnablesInsurance(Organization $organization): bool
    {
        if (
            (bool) data_get($organization->settings, 'insurance_only', false)
            || data_get($organization->settings, 'product') === 'tpc_insurance_os'
        ) {
            return true;
        }

        $permissions = $organization->pivot?->permissions;

        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true);
        }

        return is_array($permissions) && (bool) ($permissions['insurance'] ?? false);
    }
}
