<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationFollowUp;
use App\Models\FinanceLead;
use App\Models\Order;
use App\Models\RealEstateOutboundDelivery;
use App\Models\RealEstateProfile;
use App\Models\RealEstateWebhookReceipt;
use Illuminate\Support\Facades\Schema;

class RealEstateOperationalAuditService
{
    private const MAX_INCIDENTS = 100;

    public function snapshot(int $hours = 24): array
    {
        $hours = max(1, min(168, $hours));
        $cutoff = now()->subHours($hours);
        $incidents = [];

        $readiness = app(RealEstateReadinessService::class)->snapshot();

        $this->auditIsolationLeakage($incidents);
        $this->auditOutbound($incidents, $cutoff);
        $this->auditWebhookReceipts($incidents, $cutoff);
        $this->auditAudio($incidents, $cutoff);
        $this->auditProfileInvariants($incidents);

        $incidents = collect($incidents)
            ->sortByDesc(fn (array $incident): int => $this->severityWeight($incident['severity']))
            ->take(self::MAX_INCIDENTS)
            ->values()
            ->all();

        $counts = [
            'critical' => collect($incidents)->where('severity', 'critical')->count(),
            'warning' => collect($incidents)->where('severity', 'warning')->count(),
            'info' => collect($incidents)->where('severity', 'info')->count(),
        ];

        $operationalSafe = $counts['critical'] === 0;

        return [
            'service' => 'real-estate-ai',
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'window_hours' => $hours,
            'audited_at' => now()->toIso8601String(),
            'operational_safe' => $operationalSafe,
            'ready_for_live_traffic' => $operationalSafe
                && (bool) ($readiness['ready_for_live_traffic'] ?? false),
            'readiness_blockers' => array_values($readiness['blocking_checks'] ?? []),
            'incident_counts' => $counts,
            'incidents' => $incidents,
        ];
    }

    private function auditIsolationLeakage(array &$incidents): void
    {
        $activeFollowUps = ConversationFollowUp::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('is_active', true)
            ->count();

        if ($activeFollowUps > 0) {
            $this->addIncident(
                $incidents,
                'critical',
                'active_follow_up_leak',
                'conversation_follow_up',
                null,
                null,
                "İzole Emlak AI kapsamına ait {$activeFollowUps} aktif otomatik takip kaydı bulundu.",
                'Takip kayıtlarını devre dışı bırak; bot 35 için otomatik takip hiçbir koşulda çalışmamalı.'
            );
        }

        $genericOrders = Order::query()
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->count();

        if ($genericOrders > 0) {
            $this->addIncident(
                $incidents,
                'critical',
                'generic_order_pipeline_leak',
                'order',
                null,
                null,
                "Bot 35 için {$genericOrders} generic sipariş kaydı bulundu.",
                'Emlak AI trafiğini WAI ticaret/sipariş hattından ayır ve bu kayıtların kaynağını incele.'
            );
        }

        $financeLeads = FinanceLead::query()
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->count();

        if ($financeLeads > 0) {
            $this->addIncident(
                $incidents,
                'critical',
                'finance_pipeline_leak',
                'finance_lead',
                null,
                null,
                "Bot 35 için {$financeLeads} finans lead kaydı bulundu.",
                'Emlak AI trafiğinin finans lead extractor hattına girmediğini doğrula ve kaynak kaydı incele.'
            );
        }
    }

    private function auditOutbound(array &$incidents, $cutoff): void
    {
        if (! Schema::hasTable('real_estate_outbound_deliveries')) {
            $this->addIncident(
                $incidents,
                'critical',
                'outbound_ledger_missing',
                'schema',
                null,
                null,
                'Kalıcı outbound teslimat defteri tablosu bulunamadı.',
                'İzole real-estate migrationlarını uygula; teslimat defteri olmadan canlı trafik açma.'
            );

            return;
        }

        $base = RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->where('created_at', '>=', $cutoff);

        foreach ((clone $base)->where('status', 'uncertain')->latest('id')->limit(25)->get() as $delivery) {
            $this->addIncident(
                $incidents,
                'critical',
                'outbound_delivery_uncertain',
                'real_estate_outbound_delivery',
                $delivery->id,
                null,
                'Bir WhatsApp cevabının teslim edilip edilmediği kesin değil; otomatik yeniden gönderim engellenmiş durumda.',
                'Evolution/WhatsApp tarafını manuel doğrula ve güvenli outbound recovery komutuyla kaydı çöz.'
            );
        }

        foreach ((clone $base)
            ->where('status', 'sending')
            ->where('sending_started_at', '<=', now()->subMinutes(2))
            ->latest('id')
            ->limit(25)
            ->get() as $delivery) {
            $this->addIncident(
                $incidents,
                'critical',
                'outbound_delivery_stuck_sending',
                'real_estate_outbound_delivery',
                $delivery->id,
                null,
                'Outbound teslimat iki dakikadan uzun süredir sending durumunda; ağ sınırında belirsizlik olabilir.',
                'Kaydı otomatik yeniden göndermeden önce WhatsApp teslim durumunu manuel doğrula.'
            );
        }

        foreach ((clone $base)
            ->where('status', 'reserved')
            ->where('created_at', '<=', now()->subMinutes(10))
            ->latest('id')
            ->limit(25)
            ->get() as $delivery) {
            $this->addIncident(
                $incidents,
                'warning',
                'outbound_delivery_stale_reserved',
                'real_estate_outbound_delivery',
                $delivery->id,
                null,
                'Outbound teslimat on dakikadan uzun süredir reserved durumda ve ağ gönderimine geçmemiş.',
                'Queue/worker sağlığını ve ilgili inbound receipt durumunu kontrol et.'
            );
        }

        foreach ((clone $base)->where('attempts', '>', 1)->latest('id')->limit(25)->get() as $delivery) {
            $this->addIncident(
                $incidents,
                'critical',
                'outbound_network_retry_invariant',
                'real_estate_outbound_delivery',
                $delivery->id,
                null,
                'Aynı outbound teslimat kaydında birden fazla ağ denemesi görüldü.',
                'Duplicate-send korumasını incele; bu kayıt çözülene kadar canlı trafik açma.'
            );
        }
    }

    private function auditWebhookReceipts(array &$incidents, $cutoff): void
    {
        if (! Schema::hasTable('real_estate_webhook_receipts')) {
            $this->addIncident(
                $incidents,
                'critical',
                'webhook_receipt_ledger_missing',
                'schema',
                null,
                null,
                'Kalıcı inbound webhook receipt tablosu bulunamadı.',
                'İzole webhook receipt migrationını uygula; durable deduplication olmadan canlı trafik açma.'
            );

            return;
        }

        $base = RealEstateWebhookReceipt::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->where('created_at', '>=', $cutoff);

        foreach ((clone $base)->where('status', 'failed')->latest('id')->limit(25)->get() as $receipt) {
            $this->addIncident(
                $incidents,
                'warning',
                'webhook_processing_failed',
                'real_estate_webhook_receipt',
                $receipt->id,
                null,
                'İzole WhatsApp webhook olayı failed durumunda.',
                'Uygulama logları ve receipt attempts bilgisini incele; kalıcı hata giderildikten sonra güvenli retry yap.'
            );
        }

        foreach ((clone $base)
            ->where('status', 'processing')
            ->where('processing_started_at', '<=', now()->subMinutes(5))
            ->latest('id')
            ->limit(25)
            ->get() as $receipt) {
            $this->addIncident(
                $incidents,
                'warning',
                'webhook_processing_stuck',
                'real_estate_webhook_receipt',
                $receipt->id,
                null,
                'Webhook receipt beş dakikadan uzun süredir processing durumunda.',
                'Worker/process sağlığını incele; retry öncesi outbound ledger durumunu birlikte kontrol et.'
            );
        }
    }

    private function auditAudio(array &$incidents, $cutoff): void
    {
        if (! Schema::hasTable('chat_messages')
            || ! Schema::hasColumn('chat_messages', 'media_transcription_status')) {
            return;
        }

        $base = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('message_type', 'audio')
            ->where('created_at', '>=', $cutoff);

        foreach ((clone $base)
            ->whereIn('media_transcription_status', ['failed', 'too_large', 'unsupported_format'])
            ->latest('id')
            ->limit(25)
            ->get() as $message) {
            $status = (string) $message->media_transcription_status;

            $this->addIncident(
                $incidents,
                'warning',
                'audio_transcription_'.$status,
                'chat_message',
                $message->id,
                null,
                "Sesli WhatsApp mesajı {$status} durumunda; içerik CRM karar hattına tam olarak aktarılamamış olabilir.",
                'Müşteriden yazılı tekrar veya desteklenen/kısa bir ses kaydı isteme fallback akışının çalıştığını doğrula.'
            );
        }
    }

    private function auditProfileInvariants(array &$incidents): void
    {
        if (! Schema::hasTable('real_estate_profiles')) {
            return;
        }

        $profiles = RealEstateProfile::query()
            ->with('conversation')
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('profile_type', 'seller')
            ->latest('id')
            ->limit(500)
            ->get();

        foreach ($profiles as $profile) {
            $conversation = $profile->conversation;

            if (! $conversation
                || (int) $conversation->organization_id !== RealEstateIsolationService::ORGANIZATION_ID
                || (int) $conversation->ai_bot_id !== RealEstateIsolationService::BOT_ID) {
                continue;
            }

            $data = is_array($profile->data) ? $profile->data : [];
            $decision = is_array($data['decision_intelligence'] ?? null)
                ? $data['decision_intelligence']
                : [];
            $verification = is_array($data['verification_intelligence'] ?? null)
                ? $data['verification_intelligence']
                : [];

            $readyForMatch = (bool) ($decision['ready_for_match'] ?? false);

            if ($readyForMatch && ($decision['valuation_research_needed'] ?? false)) {
                $this->addIncident(
                    $incidents,
                    'critical',
                    'seller_match_ready_with_unusable_valuation',
                    'real_estate_profile',
                    $profile->id,
                    $conversation->id,
                    'Satıcı profili eşleşmeye hazır işaretlenmiş fakat karar verisi güncel değerleme araştırması gerektiğini söylüyor.',
                    'Valuation decision guard ve match freshness filtresini yeniden çalıştır; eski/kaynaksız değerlemeyle eşleşme sunma.'
                );
            }

            if ($readyForMatch && $verification !== [] && ! ($verification['safe_to_match'] ?? false)) {
                $this->addIncident(
                    $incidents,
                    'critical',
                    'seller_match_ready_with_verification_risk',
                    'real_estate_profile',
                    $profile->id,
                    $conversation->id,
                    'Satıcı profili eşleşmeye hazır işaretlenmiş fakat doğrulama katmanı safe_to_match=false.',
                    'Verification decision guard ve match verification filtresini yeniden çalıştır; risk çözülmeden yatırımcıya fırsat olarak sunma.'
                );
            }

            if (($verification['status'] ?? null) === 'blocked'
                || (int) ($verification['risk_score'] ?? 0) >= 70) {
                $this->addIncident(
                    $incidents,
                    'warning',
                    'seller_verification_high_risk',
                    'real_estate_profile',
                    $profile->id,
                    $conversation->id,
                    'Satıcı taşınmaz doğrulamasında yüksek risk veya blocked durumu bulunuyor.',
                    'Tapu/imar/konum veya belge tutarsızlıklarını operatör incelemesine al; kesin hukuki/fiyat iddiası kurma.'
                );
            }

            $tags = $conversation->etiketler();

            if (! $readyForMatch && in_array('real_estate:state:ready_for_match', $tags, true)) {
                $this->addIncident(
                    $incidents,
                    'critical',
                    'stale_ready_for_match_tag',
                    'conversation_control',
                    $conversation->id,
                    $conversation->id,
                    'CRM etiketi ready_for_match gösteriyor fakat güncel decision_intelligence eşleşmeye hazır değil.',
                    'Karar/değerleme/doğrulama guard zincirini yeniden çalıştır ve eski ready_for_match etiketini temizle.'
                );
            }
        }
    }

    private function addIncident(
        array &$incidents,
        string $severity,
        string $code,
        string $entityType,
        ?int $entityId,
        ?int $conversationId,
        string $summary,
        string $remediation,
    ): void {
        if (count($incidents) >= self::MAX_INCIDENTS * 2) {
            return;
        }

        $incidents[] = [
            'severity' => $severity,
            'code' => $code,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'conversation_control_id' => $conversationId,
            'summary' => $summary,
            'remediation' => $remediation,
        ];
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            default => 1,
        };
    }
}
