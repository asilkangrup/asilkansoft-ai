<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_sales_goals', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table
                ->foreignId('staff_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('month');

            $table
                ->decimal('target_amount', 14, 2)
                ->default(0);

            $table->timestamps();

            $table->unique(
                [
                    'owner_user_id',
                    'staff_user_id',
                    'month',
                ],
                'staff_sales_goals_owner_staff_month_unique'
            );

            $table->index(
                [
                    'owner_user_id',
                    'month',
                ]
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'staff_sales_goals'
        );
    }
};