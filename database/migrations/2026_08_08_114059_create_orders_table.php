<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | HANGİ YAPAY ZEKA BOTUNA AİT?
            |--------------------------------------------------------------------------
            */

            $table->foreignId('ai_bot_id')
                ->constrained('ai_bots')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | WHATSAPP / MÜŞTERİ
            |--------------------------------------------------------------------------
            */

            $table->string('session_id')->nullable()->index();

            $table->string('whatsapp_number')
                ->nullable()
                ->index();

            $table->string('customer_name')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | SİPARİŞ BİLGİLERİ
            |--------------------------------------------------------------------------
            */

            $table->text('products')
                ->nullable();

            $table->string('quantity')
                ->nullable();

            $table->decimal('total_amount', 12, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TESLİMAT
            |--------------------------------------------------------------------------
            */

            $table->text('address')
                ->nullable();

            $table->string('city')
                ->nullable();

            $table->string('district')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ÖDEME
            |--------------------------------------------------------------------------
            */

            $table->string('payment_method')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | SİPARİŞ DURUMU
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('draft')
                ->index();

            /*
             * draft      = Bilgiler henüz toplanıyor
             * pending    = Sipariş alındı / onay bekliyor
             * confirmed  = Sipariş onaylandı
             * preparing  = Hazırlanıyor
             * shipped    = Kargoya verildi
             * delivered  = Teslim edildi
             * cancelled  = İptal edildi
             */

            /*
            |--------------------------------------------------------------------------
            | NOTLAR
            |--------------------------------------------------------------------------
            */

            $table->text('customer_note')
                ->nullable();

            $table->text('internal_note')
                ->nullable();

            $table->timestamps();

            $table->index([
                'ai_bot_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};