<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('real_estate_valuation_research_events', function (Blueprint $table): void {
            $table->decimal('realistic_sale_min', 16, 2)->nullable()->after('market_max');
            $table->decimal('realistic_sale_max', 16, 2)->nullable()->after('realistic_sale_min');
        });
    }

    public function down(): void
    {
        Schema::table('real_estate_valuation_research_events', function (Blueprint $table): void {
            $table->dropColumn(['realistic_sale_min', 'realistic_sale_max']);
        });
    }
};
