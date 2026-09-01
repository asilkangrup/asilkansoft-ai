<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->longText('media_transcript')
                ->nullable()
                ->after('media_caption');

            $table->string('media_transcription_status', 40)
                ->nullable()
                ->after('media_transcript');

            $table->string('media_transcription_model', 100)
                ->nullable()
                ->after('media_transcription_status');

            $table->string('media_transcription_language', 20)
                ->nullable()
                ->after('media_transcription_model');

            $table->timestamp('media_transcribed_at')
                ->nullable()
                ->after('media_transcription_language');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'media_transcript',
                'media_transcription_status',
                'media_transcription_model',
                'media_transcription_language',
                'media_transcribed_at',
            ]);
        });
    }
};
