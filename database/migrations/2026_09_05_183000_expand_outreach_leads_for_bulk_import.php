<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            $table->string('source_url', 2048)->nullable()->after('source');
            $table->timestamp('source_published_at')->nullable()->after('source_url');
            $table->timestamp('source_checked_at')->nullable()->after('source_published_at');
            $table->string('whatsapp_status')->default('unknown')->after('status');
            $table->unsignedSmallInteger('priority_score')->default(0)->after('whatsapp_status');
            $table->index(['user_id', 'source_published_at']);
            $table->index(['user_id', 'whatsapp_status']);
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'source_published_at']);
            $table->dropIndex(['user_id', 'whatsapp_status']);
            $table->dropColumn([
                'source_url',
                'source_published_at',
                'source_checked_at',
                'whatsapp_status',
                'priority_score',
            ]);
        });
    }
};
