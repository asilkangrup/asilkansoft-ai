<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table
                ->decimal('actual_value', 14, 2)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table->dropColumn('actual_value');
        });
    }
};