<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->after('source');
            $table->timestamp('source_listed_at')->nullable()->after('source_url');
            $table->timestamp('source_checked_at')->nullable()->after('source_listed_at');
            $table->string('whatsapp_status')->default('unknown')->after('status');
            $table->index(['user_id', 'source_listed_at']);
            $table->index(['user_id', 'whatsapp_status']);
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'source_listed_at']);
            $table->dropIndex(['user_id', 'whatsapp_status']);
            $table->dropColumn(['source_url', 'source_listed_at', 'source_checked_at', 'whatsapp_status']);
        });
    }
};
