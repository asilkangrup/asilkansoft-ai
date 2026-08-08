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
    | FILAMENT PANEL ERİŞİMİ
    |--------------------------------------------------------------------------
    |
    | Hem admin hem müşteri paneli kullanabilir.
    | Hangi verileri görebileceklerini Resource tarafında ayıracağız.
    |
    */

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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