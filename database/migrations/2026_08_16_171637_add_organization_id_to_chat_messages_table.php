<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table
                ->foreignId('organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index([
                'organization_id',
                'session_id',
            ]);

            $table->index([
                'organization_id',
                'sender_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex([
                'organization_id',
                'session_id',
            ]);

            $table->dropIndex([
                'organization_id',
                'sender_type',
            ]);

            $table->dropConstrainedForeignId(
                'organization_id'
            );
        });
    }
};