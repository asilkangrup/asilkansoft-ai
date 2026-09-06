<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\OutreachLead;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaiSalesHotLeadNotifier
{
    public const INSTANCE = 'wai-sales-48-clean';
    public const GROUP_JID = '120363412694979850@g.us';

    public function notify(OutreachLead $lead, string $sector, string $sessionId): void
    {
        $this->send(
            lead: $lead,
            sector: $sector,
            sessionId: $sessionId,
            headline: '🔥 BUNU ARA',
        );
    }

    public function notifyCallRequest(
        OutreachLead $lead,
        string $sector,
        string $sessionId,
        string $requestText,
    ): void {
        $this->send(
            lead: $lead,
            sector: $sector,
            sessionId: $sessionId,
            headline: '🔥 HEMEN ARA — MÜŞTERİ ARAMA İSTEDİ',
            extraLines: [
                'Arama talebi: '.trim($requestText),
            ],
        );
    }

    private function send(
        OutreachLead $lead,
        string $sector,
        string $sessionId,
        string $headline,
        array $extraLines = [],
    ): void {
        try {
            $recent = ChatMessage::query()
                ->where('session_id', $sessionId)
                ->latest('id')
                ->limit(10)
                ->get(['role', 'message'])
                ->reverse()
                ->map(function (ChatMessage $message): string {
                    $prefix = $message->role === 'user' ? 'Müşteri' : 'WAI';

                    return $prefix.': '.trim((string) $message->message);
                })
                ->filter()
                ->implode("\n");

            $summary = $recent !== ''
                ? $recent
                : 'Müşteri WAI demosu / fiyatı / ekip görüşmesiyle ilgileniyor.';

            $groupText = implode("\n", array_filter([
                $headline,
                '',
                'İşletme: '.$lead->company_name,
                'Sektör: '.$sector,
                'Telefon: '.$lead->phone_e164,
                ...$extraLines,
                '',
                'Konuşma özeti:',
                $summary,
            ], fn ($line): bool => $line !== null));

            app(WhatsAppService::class)->sendGroupText(
                self::INSTANCE,
                self::GROUP_JID,
                $groupText,
            );
        } catch (Throwable $exception) {
            Log::warning('WAI HOT LEAD GROUP NOTIFICATION FAILED', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
