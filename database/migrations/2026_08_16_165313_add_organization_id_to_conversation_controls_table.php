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
                ->foreignId('organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index([
                'organization_id',
                'assigned_user_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table->dropIndex([
                'organization_id',
                'assigned_user_id',
            ]);

            $table->dropConstrainedForeignId(
                'organization_id'
            );
        });
    }
};