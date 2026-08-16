<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table
                ->string('name');

            $table
                ->string('slug')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | PAKET
            |--------------------------------------------------------------------------
            */

            $table
                ->string('plan', 50)
                ->default('start');

            $table
                ->unsignedInteger('seat_limit')
                ->default(1);

            $table
                ->unsignedInteger('monthly_message_limit')
                ->default(1000);

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
            | ABONELİK TARİHLERİ
            |--------------------------------------------------------------------------
            */

            $table
                ->timestamp('trial_ends_at')
                ->nullable();

            $table
                ->timestamp('subscription_ends_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | EK AYARLAR
            |--------------------------------------------------------------------------
            */

            $table
                ->json('settings')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXLER
            |--------------------------------------------------------------------------
            */

            $table->index([
                'owner_user_id',
                'status',
            ]);

            $table->index([
                'plan',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};