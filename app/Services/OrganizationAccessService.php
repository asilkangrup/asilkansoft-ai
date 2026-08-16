<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class OrganizationAccessService
{
    public function currentOrganization(
        ?User $user = null
    ): ?Organization {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        return $user
            ->organizations()
            ->wherePivot(
                'status',
                'active'
            )
            ->first();
    }

    public function currentRole(
        ?User $user = null
    ): ?string {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->is_admin) {
            return 'admin';
        }

        $organization =
            $this->currentOrganization(
                $user
            );

        if (! $organization) {
            return null;
        }

        return $user
            ->roleInOrganization(
                $organization->id
            );
    }

    public function can(
        string $permission,
        ?User $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $role =
            $this->currentRole(
                $user
            );

        $permissions = [
            'owner' => [
                'dashboard',
                'customers',
                'pipeline',
                'tasks',
                'alarms',
                'reports',
                'team',
                'channels',
                'setup',
                'whatsapp_connect',
                'test_chat',
            ],

            'manager' => [
                'dashboard',
                'customers',
                'pipeline',
                'tasks',
                'alarms',
                'reports',
                'team',
                'channels',
                'test_chat',
            ],

            'sales' => [
                'dashboard',
                'customers',
                'pipeline',
                'tasks',
                'alarms',
                'test_chat',
            ],

            'support' => [
                'dashboard',
                'customers',
                'tasks',
                'alarms',
                'test_chat',
            ],

            'viewer' => [
                'dashboard',
                'customers',
                'pipeline',
                'tasks',
                'alarms',
                'reports',
            ],
        ];

        return in_array(
            $permission,
            $permissions[$role] ?? [],
            true
        );
    }

    public function canWriteCrm(
        ?User $user = null
    ): bool {
        $role =
            $this->currentRole(
                $user
            );

        return in_array(
            $role,
            [
                'admin',
                'owner',
                'manager',
                'sales',
                'support',
            ],
            true
        );
    }

    public function canAssignCustomers(
        ?User $user = null
    ): bool {
        $role =
            $this->currentRole(
                $user
            );

        return in_array(
            $role,
            [
                'admin',
                'owner',
                'manager',
            ],
            true
        );
    }
}