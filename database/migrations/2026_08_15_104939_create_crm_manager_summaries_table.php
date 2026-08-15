<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_manager_summaries', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('summary_date');

            $table->text('summary');

            $table->json('metrics')
                ->nullable();

            $table->timestamp('generated_at')
                ->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'user_id',
                    'summary_date',
                ],
                'crm_manager_summaries_user_date_unique'
            );

            $table->index(
                [
                    'summary_date',
                    'generated_at',
                ]
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'crm_manager_summaries'
        );
    }
};