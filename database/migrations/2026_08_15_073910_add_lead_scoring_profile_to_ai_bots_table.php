<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table
                ->string('lead_scoring_profile', 50)
                ->default('general');
        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->dropColumn('lead_scoring_profile');
        });
    }
};