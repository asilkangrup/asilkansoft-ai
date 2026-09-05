<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('outreach_leads', 'sector')) {
                $table->string('sector')->nullable()->after('company_name');
                $table->index(['user_id', 'sector']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table): void {
            if (Schema::hasColumn('outreach_leads', 'sector')) {
                $table->dropIndex(['user_id', 'sector']);
                $table->dropColumn('sector');
            }
        });
    }
};
