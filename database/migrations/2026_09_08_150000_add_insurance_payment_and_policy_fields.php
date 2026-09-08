<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_cases', function (Blueprint $table): void {
            $table->foreignId('selected_quote_id')
                ->nullable()
                ->after('open_teklif_id')
                ->constrained('insurance_quote_results')
                ->nullOnDelete();

            $table->string('payment_status', 32)
                ->default('not_started')
                ->after('integration_status')
                ->index();

            $table->string('payment_method', 64)->nullable()->after('payment_status');
            $table->string('payment_reference', 128)->nullable()->after('payment_method')->index();
            $table->timestamp('payment_ready_at')->nullable()->after('payment_reference');
            $table->string('policy_number', 128)->nullable()->after('payment_ready_at')->index();
            $table->timestamp('issued_at')->nullable()->after('policy_number')->index();
            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->after('issued_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_quote_id');
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'payment_reference',
                'payment_ready_at',
                'policy_number',
                'issued_at',
            ]);
        });
    }
};
