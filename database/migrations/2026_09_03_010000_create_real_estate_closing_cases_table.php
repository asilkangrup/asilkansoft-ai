<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_closing_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('ai_bot_id')->index();
            $table->unsignedBigInteger('seller_profile_id')->index();
            $table->unsignedBigInteger('investor_profile_id')->index();
            $table->string('status', 40)->default('document_review')->index();
            $table->unsignedBigInteger('agreed_price')->nullable();
            $table->boolean('title_deed_verified')->default(false);
            $table->boolean('identity_authority_verified')->default(false);
            $table->boolean('encumbrance_checked')->default(false);
            $table->boolean('tax_fee_checked')->default(false);
            $table->boolean('payment_method_confirmed')->default(false);
            $table->timestamp('appointment_at')->nullable();
            $table->string('appointment_location', 300)->nullable();
            $table->unsignedBigInteger('deposit_amount')->nullable();
            $table->boolean('deposit_received')->default(false);
            $table->boolean('final_payment_verified')->default(false);
            $table->boolean('deed_transfer_completed')->default(false);
            $table->text('operator_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['user_id', 'organization_id', 'ai_bot_id', 'seller_profile_id', 'investor_profile_id'],
                're_closing_case_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_closing_cases');
    }
};
