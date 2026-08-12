<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('message_type', 30)
                ->default('text')
                ->after('message');

            $table->text('media_url')
                ->nullable()
                ->after('message_type');

            $table->string('media_mime_type', 150)
                ->nullable()
                ->after('media_url');

            $table->string('media_filename', 255)
                ->nullable()
                ->after('media_mime_type');

            $table->text('media_caption')
                ->nullable()
                ->after('media_filename');

            $table->unsignedInteger('media_duration')
                ->nullable()
                ->after('media_caption');

            $table->unsignedBigInteger('media_size')
                ->nullable()
                ->after('media_duration');

            $table->string('whatsapp_message_id', 255)
                ->nullable()
                ->index()
                ->after('media_size');

            $table->string('status', 30)
                ->default('sent')
                ->after('whatsapp_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'message_type',
                'media_url',
                'media_mime_type',
                'media_filename',
                'media_caption',
                'media_duration',
                'media_size',
                'whatsapp_message_id',
                'status',
            ]);
        });
    }
};