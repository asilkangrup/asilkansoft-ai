<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table
                ->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ROL
            |--------------------------------------------------------------------------
            |
            | owner   = işletme sahibi
            | manager = yönetici
            | sales   = satış temsilcisi
            | support = destek personeli
            | viewer  = sadece görüntüleme
            |
            */

            $table
                ->string('role', 30)
                ->default('sales');

            /*
            |--------------------------------------------------------------------------
            | DURUM
            |--------------------------------------------------------------------------
            */

            $table
                ->string('status', 30)
                ->default('active');

            /*
            |--------------------------------------------------------------------------
            | ÖZEL YETKİLER
            |--------------------------------------------------------------------------
            |
            | İleride rol dışında kullanıcıya özel yetki vermek istersek burada tutacağız.
            |
            */

            $table
                ->json('permissions')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | DAVET / KATILIM
            |--------------------------------------------------------------------------
            */

            $table
                ->timestamp('joined_at')
                ->nullable();

            $table
                ->timestamp('last_active_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | UNIQUE / INDEXLER
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'organization_id',
                'user_id',
            ]);

            $table->index([
                'organization_id',
                'role',
            ]);

            $table->index([
                'organization_id',
                'status',
            ]);

            $table->index([
                'user_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};