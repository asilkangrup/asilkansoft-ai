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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Ürün hangi yapay zekâ botuna ait?
            $table->foreignId('ai_bot_id')
                ->constrained('ai_bots')
                ->cascadeOnDelete();

            // Ürün temel bilgileri
            $table->string('name');
            $table->string('category')->nullable();

            // Fiyat
            $table->decimal('price', 12, 2)->nullable();

            // Ürün açıklaması
            $table->text('description')->nullable();

            // Stok bilgisi
            $table->string('stock_status')
                ->default('in_stock');

            // Ürün aktif mi?
            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            // Ürün aramalarını hızlandırmak için
            $table->index(['ai_bot_id', 'is_active']);
            $table->index(['ai_bot_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};