<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production already has this column from the isolated Emlak AI setup.
        // Formalize it in migrations so fresh/test environments match the live
        // schema without altering existing bot records or key values.
        if (! Schema::hasColumn('ai_bots', 'openai_api_key')) {
            Schema::table('ai_bots', function (Blueprint $table): void {
                $table->text('openai_api_key')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-op. Some existing installations had this column
        // before this compatibility migration, so dropping it on rollback could
        // destroy a pre-existing encrypted bot-level credential.
    }
};
