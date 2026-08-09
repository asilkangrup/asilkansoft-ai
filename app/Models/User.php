<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    | Böylece Filament kayıt ekranından kendi hesabını açan kullanıcılar
    | hiçbir zaman varsayılan olarak admin olmaz.
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
    |
    | Hem admin hem müşteri aynı Filament paneline giriş yapabilir.
    | Veri izolasyonu Resource / Query tarafında uygulanır.
    |
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
    | KULLANICIYA AİT YAPAY ZEKÂ BOTLARI
    |--------------------------------------------------------------------------
    */

    public function aiBots(): HasMany
    {
        return $this->hasMany(AiBot::class);
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