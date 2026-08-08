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
        Schema::table('ai_bots', function (Blueprint $table) {

            $table->text('company_description')->nullable();

            $table->text('working_hours')->nullable();

            $table->text('cargo_information')->nullable();

            $table->text('payment_information')->nullable();

            $table->text('return_policy')->nullable();

            $table->text('company_rules')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {

            $table->dropColumn([
                'company_description',
                'working_hours',
                'cargo_information',
                'payment_information',
                'return_policy',
                'company_rules',
            ]);

        });
    }
};