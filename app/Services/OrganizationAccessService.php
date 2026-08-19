<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class OrganizationAccessService
{
    /*
    |--------------------------------------------------------------------------
    | AKTİF ORGANİZASYON
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | KULLANICI ROLÜ
    |--------------------------------------------------------------------------
    */

    public function currentRole(
        ?User $user = null
    ): ?string {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | ANA YÖNETİCİ
        |--------------------------------------------------------------------------
        */

        if ((bool) $user->is_admin) {
            return 'admin';
        }

        /*
        |--------------------------------------------------------------------------
        | NORMAL KULLANICI
        |--------------------------------------------------------------------------
        */

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

    /*
    |--------------------------------------------------------------------------
    | MODÜL YETKİLERİ
    |--------------------------------------------------------------------------
    |
    | ADMIN:
    | Her şeye erişebilir.
    |
    | NORMAL KULLANICI:
    |
    | AÇIK:
    | - Kurulum Merkezi
    | - Test Sohbeti
    |
    | Yapay Zeka Ayarları AiBotResource içerisindeki owner / manager
    | yetkilendirmesiyle çalışmaya devam eder.
    |
    | Gelen Kutusu kendi resource erişimiyle çalışmaya devam eder.
    |
    | KAPALI:
    | - Dashboard
    | - Müşteriler / CRM
    | - Pipeline
    | - Görevler
    | - Alarmlar
    | - Raporlar
    | - Ekip
    | - Kanallar
    | - diğer CRM modülleri
    |
    */

    public function can(
        string $permission,
        ?User $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN HER ŞEYE ERİŞİR
        |--------------------------------------------------------------------------
        */

        if ((bool) $user->is_admin) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | NORMAL KULLANICIYA AÇIK MODÜLLER
        |--------------------------------------------------------------------------
        */

        return in_array(
            $permission,
            [
                'setup',
                'test_chat',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRM YAZMA YETKİSİ
    |--------------------------------------------------------------------------
    |
    | CRM sadece admin hesabında kullanılabilir.
    |
    */

    public function canWriteCrm(
        ?User $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin;
    }

    /*
    |--------------------------------------------------------------------------
    | CRM MÜŞTERİ ATAMA YETKİSİ
    |--------------------------------------------------------------------------
    |
    | CRM müşteri/personel ataması sadece admin hesabında kullanılabilir.
    |
    */

    public function canAssignCustomers(
        ?User $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin;
    }
}