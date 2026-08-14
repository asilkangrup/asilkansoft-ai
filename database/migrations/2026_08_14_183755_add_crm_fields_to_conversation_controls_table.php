<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | KANAL
            |--------------------------------------------------------------------------
            */

            $table
                ->string('channel', 30)
                ->default('whatsapp')
                ->after('whatsapp_number');

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ BİLGİLERİ
            |--------------------------------------------------------------------------
            */

            $table
                ->string('customer_email')
                ->nullable()
                ->after('customer_name');

            $table
                ->string('company_name')
                ->nullable()
                ->after('customer_email');

            /*
            |--------------------------------------------------------------------------
            | CRM / LEAD DURUMU
            |--------------------------------------------------------------------------
            */

            $table
                ->string('lead_status', 30)
                ->default('new')
                ->after('tags');

            $table
                ->unsignedTinyInteger('lead_score')
                ->default(0)
                ->after('lead_status');

            $table
                ->string('lead_temperature', 20)
                ->default('cold')
                ->after('lead_score');

            /*
            |--------------------------------------------------------------------------
            | SORUMLU PERSONEL
            |--------------------------------------------------------------------------
            */

            $table
                ->foreignId('assigned_user_id')
                ->nullable()
                ->after('lead_temperature')
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CRM NOTLARI
            |--------------------------------------------------------------------------
            */

            $table
                ->text('notes')
                ->nullable()
                ->after('assigned_user_id');

            /*
            |--------------------------------------------------------------------------
            | İLETİŞİM / TAKİP
            |--------------------------------------------------------------------------
            */

            $table
                ->timestamp('last_contact_at')
                ->nullable()
                ->after('notes');

            $table
                ->timestamp('next_follow_up_at')
                ->nullable()
                ->after('last_contact_at');

            /*
            |--------------------------------------------------------------------------
            | SATIŞ SONUCU
            |--------------------------------------------------------------------------
            */

            $table
                ->timestamp('won_at')
                ->nullable()
                ->after('next_follow_up_at');

            $table
                ->timestamp('lost_at')
                ->nullable()
                ->after('won_at');

            $table
                ->string('lost_reason')
                ->nullable()
                ->after('lost_at');

            /*
            |--------------------------------------------------------------------------
            | INDEXLER
            |--------------------------------------------------------------------------
            */

            $table->index('channel');
            $table->index('lead_status');
            $table->index('lead_temperature');
            $table->index('lead_score');
            $table->index('last_contact_at');
            $table->index('next_follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table->dropForeign([
                'assigned_user_id',
            ]);

            $table->dropIndex([
                'channel',
            ]);

            $table->dropIndex([
                'lead_status',
            ]);

            $table->dropIndex([
                'lead_temperature',
            ]);

            $table->dropIndex([
                'lead_score',
            ]);

            $table->dropIndex([
                'last_contact_at',
            ]);

            $table->dropIndex([
                'next_follow_up_at',
            ]);

            $table->dropColumn([
                'channel',
                'customer_email',
                'company_name',
                'lead_status',
                'lead_score',
                'lead_temperature',
                'assigned_user_id',
                'notes',
                'last_contact_at',
                'next_follow_up_at',
                'won_at',
                'lost_at',
                'lost_reason',
            ]);
        });
    }
};