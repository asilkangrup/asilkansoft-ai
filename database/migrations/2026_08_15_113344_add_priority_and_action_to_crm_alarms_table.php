<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_alarms', function (Blueprint $table) {
            $table
                ->unsignedTinyInteger('priority_score')
                ->default(0)
                ->after('severity');

            $table
                ->text('recommended_action')
                ->nullable()
                ->after('message');

            $table->index([
                'user_id',
                'is_resolved',
                'priority_score',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('crm_alarms', function (Blueprint $table) {
            $table->dropIndex([
                'user_id',
                'is_resolved',
                'priority_score',
            ]);

            $table->dropColumn([
                'priority_score',
                'recommended_action',
            ]);
        });
    }
};