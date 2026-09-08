<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_renewal_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('plate', 32)->nullable()->index();
            $table->string('motor_number', 128)->nullable()->index();
            $table->string('policy_number', 128)->nullable()->index();
            $table->string('insurer')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->timestamp('external_policy_detected_at')->nullable()->index();
            $table->string('status', 40)->default('monitoring')->index();
            $table->string('source', 40)->default('manual');
            $table->string('source_reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_renewal_opportunities');
    }
};
