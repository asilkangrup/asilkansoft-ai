<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_control_id')->nullable()->constrained('conversation_controls')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source_channel', 32)->default('manual');
            $table->string('source_reference')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('phone', 32)->nullable()->index();

            $table->string('status', 40)->default('new')->index();
            $table->unsignedSmallInteger('priority')->default(50)->index();
            $table->string('policy_type', 20)->default('TRAFIK')->index();

            $table->string('plate', 32)->nullable()->index();
            $table->string('license_number', 64)->nullable();
            $table->string('motor_number', 128)->nullable()->index();
            $table->string('chassis_number', 128)->nullable()->index();
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->unsignedSmallInteger('vehicle_year')->nullable();

            $table->unsignedBigInteger('open_teklif_id')->nullable()->index();
            $table->string('integration_status', 40)->default('not_connected')->index();
            $table->text('integration_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('insurance_quote_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_case_id')->constrained('insurance_cases')->cascadeOnDelete();
            $table->string('provider', 50)->default('open_hizli_teklif');
            $table->string('company_name')->nullable();
            $table->decimal('premium', 14, 2)->nullable();
            $table->string('currency', 8)->default('TRY');
            $table->string('status', 32)->default('received')->index();
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['insurance_case_id', 'provider']);
        });

        Schema::create('insurance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_case_id')->constrained('insurance_cases')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['insurance_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_events');
        Schema::dropIfExists('insurance_quote_results');
        Schema::dropIfExists('insurance_cases');
    }
};
