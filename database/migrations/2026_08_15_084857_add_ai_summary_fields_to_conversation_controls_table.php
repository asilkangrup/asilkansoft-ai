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
                ->text('ai_summary')
                ->nullable();

            $table
                ->text('next_best_action')
                ->nullable();

            $table
                ->timestamp('ai_summary_updated_at')
                ->nullable();

            $table
                ->unsignedInteger('ai_summary_message_count')
                ->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary',
                'next_best_action',
                'ai_summary_updated_at',
                'ai_summary_message_count',
            ]);
        });
    }
};