<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmed_notification_sent_at')
                ->nullable()
                ->after('shipping_notification_sent_at');

            $table->timestamp('preparing_notification_sent_at')
                ->nullable()
                ->after('confirmed_notification_sent_at');

            $table->timestamp('delivered_notification_sent_at')
                ->nullable()
                ->after('preparing_notification_sent_at');

            $table->timestamp('cancelled_notification_sent_at')
                ->nullable()
                ->after('delivered_notification_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_notification_sent_at',
                'preparing_notification_sent_at',
                'delivered_notification_sent_at',
                'cancelled_notification_sent_at',
            ]);
        });
    }
};