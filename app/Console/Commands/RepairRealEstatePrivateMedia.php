<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\RealEstatePrivateMedia;
use App\Services\EvolutionMediaService;
use App\Services\RealEstateIsolationService;
use Illuminate\Console\Command;
use Throwable;

class RepairRealEstatePrivateMedia extends Command
{
    protected $signature = 'real-estate:repair-private-media {--limit=200}';
    protected $description = 'Re-download missing isolated Emlak AI media into durable private storage.';

    public function handle(EvolutionMediaService $mediaService): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $repaired = 0;
        $failed = 0;

        ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->whereIn('message_type', ['image', 'document'])
            ->whereNotNull('whatsapp_message_id')
            ->where('media_url', 'like', 'private:real-estate-inbound/%')
            ->whereNotIn('id', RealEstatePrivateMedia::query()->isolatedProduction()->whereNotNull('chat_message_id')->select('chat_message_id'))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (ChatMessage $message) use ($mediaService, &$repaired, &$failed): void {
                try {
                    $saved = $mediaService->persistPrivateInboundMedia(
                        message: $message,
                        instanceName: RealEstateIsolationService::INSTANCE,
                        mediaContext: [
                            'type' => $message->message_type,
                            'mime_type' => $message->media_mime_type,
                            'message_envelope' => ['key' => ['id' => $message->whatsapp_message_id]],
                        ],
                    );
                    $saved ? $repaired++ : $failed++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn('Medya '.$message->id.' kurtarılamadı: '.$exception->getMessage());
                }
            });

        $this->info("Kurtarılan: {$repaired}; kurtarılamayan: {$failed}");

        return self::SUCCESS;
    }
}
