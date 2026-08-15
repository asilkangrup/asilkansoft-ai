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
                ->timestamp('snoozed_until')
                ->nullable()
                ->after('notified_at');

            $table->index([
                'user_id',
                'is_resolved',
                'snoozed_until',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('crm_alarms', function (Blueprint $table) {
            $table->dropIndex([
                'user_id',
                'is_resolved',
                'snoozed_until',
            ]);

            $table->dropColumn(
                'snoozed_until'
            );
        });
    }
};