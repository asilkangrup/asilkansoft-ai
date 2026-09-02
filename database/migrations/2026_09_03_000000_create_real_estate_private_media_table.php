<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_private_media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('ai_bot_id');
            $table->unsignedBigInteger('chat_message_id')->nullable()->unique();
            $table->unsignedBigInteger('real_estate_profile_id')->nullable();
            $table->uuid('media_key')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->longText('content_base64');
            $table->timestamps();
            $table->unique(['real_estate_profile_id', 'media_key'], 'real_estate_private_media_profile_key_unique');
            $table->index(['user_id', 'organization_id', 'ai_bot_id'], 'real_estate_private_media_tenant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_private_media');
    }
};
