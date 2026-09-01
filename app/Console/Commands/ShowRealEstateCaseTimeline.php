<?php

namespace App\Console\Commands;

use App\Models\RealEstateCaseEvent;
use App\Services\RealEstateIsolationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ShowRealEstateCaseTimeline extends Command
{
    protected $signature = 'real-estate:case-timeline
        {conversation_id? : İzole Emlak AI conversation_controls ID}
        {--limit=30 : Gösterilecek en fazla olay}';

    protected $description = 'İzole Emlak AI için PII içermeyen CRM vaka yaşam döngüsü olaylarını gösterir.';

    public function handle(): int
    {
        if (! Schema::hasTable('real_estate_case_events')) {
            $this->error('real_estate_case_events tablosu hazır değil.');

            return self::FAILURE;
        }

        $limit = max(1, min(200, (int) $this->option('limit')));
        $conversationId = $this->argument('conversation_id');

        $query = RealEstateCaseEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->latest('id');

        if ($conversationId !== null) {
            if (! ctype_digit((string) $conversationId) || (int) $conversationId <= 0) {
                $this->error('conversation_id pozitif bir tam sayı olmalıdır.');

                return self::INVALID;
            }

            $query->where('conversation_control_id', (int) $conversationId);
        }

        $events = $query->limit($limit)->get()->reverse()->values();

        if ($events->isEmpty()) {
            $this->info('İzole Emlak AI için vaka yaşam döngüsü olayı yok.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'ID',
                'Zaman',
                'Conversation',
                'Profil',
                'Olay',
                'Aşama',
                'Skor',
                'Isı',
                'Değerleme',
                'Eşleşmeye Hazır',
                'Eşleşme',
                'En Güçlü',
            ],
            $events->map(fn (RealEstateCaseEvent $event): array => [
                $event->id,
                $event->occurred_at?->format('Y-m-d H:i:s'),
                $event->conversation_control_id,
                $event->profile_type,
                $event->event_type,
                $event->stage ?? '-',
                $event->lead_score ?? '-',
                $event->lead_temperature ?? '-',
                $event->valuation_present ? 'evet' : 'hayır',
                $event->ready_for_match ? 'evet' : 'hayır',
                $event->match_count,
                $event->strongest_match_grade ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
