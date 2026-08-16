<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | MODEL BAŞLATMA
    |--------------------------------------------------------------------------
    |
    | Yeni kullanıcı oluşturulurken is_admin değeri özellikle verilmediyse
    | otomatik olarak müşteri hesabı oluşturulur.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->is_admin === null) {
                $user->is_admin = false;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT PANEL ERİŞİMİ
    |--------------------------------------------------------------------------
    */

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN Mİ?
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AİT YAPAY ZEKA BOTLARI
    |--------------------------------------------------------------------------
    */

    public function aiBots(): HasMany
    {
        return $this->hasMany(
            AiBot::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAHİBİ OLDUĞU ORGANİZASYONLAR
    |--------------------------------------------------------------------------
    */

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(
            Organization::class,
            'owner_user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ÜYESİ OLDUĞU ORGANİZASYONLAR
    |--------------------------------------------------------------------------
    */

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'organization_user'
        )
            ->withPivot([
                'role',
                'status',
                'permissions',
                'joined_at',
                'last_active_at',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF ORGANİZASYONLAR
    |--------------------------------------------------------------------------
    */

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot(
                'status',
                'active'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | BİR ORGANİZASYONDAKİ ROL
    |--------------------------------------------------------------------------
    */

    public function roleInOrganization(
        int $organizationId
    ): ?string {
        $organization =
            $this->organizations()
                ->where(
                    'organizations.id',
                    $organizationId
                )
                ->first();

        return $organization?->pivot?->role;
    }

    /*
    |--------------------------------------------------------------------------
    | ORGANİZASYON YÖNETEBİLİR Mİ?
    |--------------------------------------------------------------------------
    */

    public function canManageOrganization(
        int $organizationId
    ): bool {
        $role =
            $this->roleInOrganization(
                $organizationId
            );

        return in_array(
            $role,
            [
                'owner',
                'manager',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}