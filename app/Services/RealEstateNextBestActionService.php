<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;

class RealEstateNextBestActionService
{
    private const DATA_KEY = 'next_best_action_intelligence';

    public function process(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! $profile->belongsToIsolatedProductionScope()) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];

        if ($decision === []) {
            return null;
        }

        $plan = $this->build($profile, $data, $decision);
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) === $plan) {
            $stored = $existing;
        } else {
            $stored = [
                ...$plan,
                'updated_at' => now()->toIso8601String(),
            ];
            $data[self::DATA_KEY] = $stored;
            $profile->data = $data;
            $profile->saveQuietly();
        }

        $conversation->update([
            'next_best_action' => $stored['action_text'],
        ]);

        $this->recordState($profile, $stored);

        return $stored;
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        $plan = is_array($profile?->data)
            ? ($profile->data[self::DATA_KEY] ?? null)
            : null;

        if (! is_array($plan) || $plan === []) {
            return '';
        }

        $safe = [
            'action_code' => $plan['action_code'] ?? null,
            'priority' => $plan['priority'] ?? null,
            'action_text' => $plan['action_text'] ?? null,
            'single_question' => $plan['single_question'] ?? null,
            'blocking' => (bool) ($plan['blocking'] ?? false),
            'reason_codes' => array_values($plan['reason_codes'] ?? []),
            'match_count' => (int) ($plan['match_count'] ?? 0),
            'guardrails' => $plan['guardrails'] ?? [],
        ];

        $json = json_encode(
            $safe,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE NEXT BEST ACTION]
Bu blok mevcut CRM, değerleme, doğrulama, belge, yatırımcı kriteri ve eşleşme durumundan deterministik olarak seçilen tek sonraki aksiyondur. action_code veya reason_codes gibi dahili alanları müşteriye gösterme. Öncelikle bu aksiyonu uygula; single_question doluysa aynı mesajda ikinci bir keşif sorusu ekleme. blocking=true ise blokaj çözülmeden fiyatı doğrulanmış gerçek, yatırımcıyı hazır alıcı veya eşleşmeyi bağlayıcı teklif gibi sunma. Hiçbir durumda otomatik follow-up planlama veya next_follow_up_at üretme. Satıcının gizli minimum fiyatını yatırımcıya açıklama ve aciliyeti baskı kurmak için kullanma.
Sonraki aksiyon: {$json}
PROMPT;
    }

    private function build(
        RealEstateProfile $profile,
        array $data,
        array $decision,
    ): array {
        $matches = is_array($data['opportunity_matches'] ?? null)
            ? $data['opportunity_matches']
            : [];
        $matchCount = count($matches);
        $stage = trim((string) ($decision['stage'] ?? 'new')) ?: 'new';

        return match ((string) $profile->profile_type) {
            'seller' => $this->sellerPlan(
                $profile,
                $decision,
                $matchCount,
                $stage,
            ),
            'investor', 'buyer' => $this->investorPlan(
                $profile,
                $decision,
                $matchCount,
                $stage,
            ),
            default => $this->payload(
                actionCode: 'clarify_customer_role',
                priority: 'high',
                actionText: 'Müşterinin taşınmaz satmak mı yoksa yatırım/alım yapmak mı istediğini tek kısa soruyla netleştir.',
                singleQuestion: 'Taşınmaz satmak mı istiyorsunuz, yoksa yatırım için mülk mü arıyorsunuz?',
                blocking: true,
                reasonCodes: ['profile_role_unresolved'],
                matchCount: $matchCount,
                stage: $stage,
            ),
        };
    }

    private function sellerPlan(
        RealEstateProfile $profile,
        array $decision,
        int $matchCount,
        string $stage,
    ): array {
        $motivation = app(RealEstateSellerMotivationService::class)
            ->summaryForProfile($profile);
        $sellerQuestion = $this->cleanQuestion(
            $motivation['recommended_next_question'] ?? null
        );
        $missing = $this->stringList(
            $decision['missing_critical_data'] ?? []
        );
        $offerPacket = app(RealEstateSellerOfferPacketService::class)
            ->summaryForProfile($profile);
        $offerMissing = $this->stringList(
            $offerPacket['missing_critical_for_offer'] ?? []
        );
        $offerQuestion = $this->cleanQuestion(
            $offerPacket['recommended_next_request'] ?? null
        );

        // A material verification conflict is the strongest safety signal in
        // the pipeline. It must never be overwritten by a softer discovery
        // question (for example urgency or timeline collection).
        if ($this->hardVerificationConflict($decision)) {
            return $this->verificationPlan(
                $decision,
                $matchCount,
                $stage,
                ['hard_verification_conflict'],
            );
        }

        if ($missing !== []) {
            return $this->payload(
                actionCode: 'complete_seller_core_data',
                priority: 'high',
                actionText: $sellerQuestion
                    ?? 'Değerleme ve eşleştirme öncesi eksik temel taşınmaz bilgisini tek kısa soruyla tamamla.',
                singleQuestion: $sellerQuestion,
                blocking: true,
                reasonCodes: array_values(array_unique([
                    'seller_core_data_incomplete',
                    ...array_map(
                        fn (string $field): string => 'missing_'.$this->code($field),
                        $missing
                    ),
                ])),
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if (in_array('property_identity', $offerMissing, true)) {
            return $this->payload(
                actionCode: 'complete_seller_offer_packet',
                priority: 'high',
                actionText: $offerQuestion
                    ?? 'Yatırımcı ön teklifi için taşınmaz kimliğini konum linki veya ada/parsel ile tamamla.',
                singleQuestion: $offerQuestion,
                blocking: true,
                reasonCodes: ['seller_offer_packet_incomplete', 'missing_property_identity'],
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        // Pricing research blockers outrank optional seller-discovery prompts:
        // stale or structurally weak valuation data must not become an anchor.
        if ((bool) ($decision['valuation_research_needed'] ?? false)) {
            return $this->payload(
                actionCode: 'refresh_valuation_research',
                priority: 'high',
                actionText: 'Güncel emsal araştırmasını yenile; yeni araştırma güvenli hale gelmeden eski değerlemeyi fiyat pazarlığında veya yatırımcı eşleştirmesinde kullanma.',
                singleQuestion: null,
                blocking: true,
                reasonCodes: ['valuation_missing_or_stale'],
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if ((bool) ($decision['valuation_integrity_needed'] ?? false)) {
            return $this->payload(
                actionCode: 'repair_comparable_integrity',
                priority: 'high',
                actionText: 'Emsal setini temizle ve aynı lokasyon/tür için fiyat, m² ve kaynak URL bütünlüğü yeterli emsallerle değerlemeyi güçlendir.',
                singleQuestion: null,
                blocking: true,
                reasonCodes: array_values(array_unique([
                    'comparable_integrity_insufficient',
                    ...array_map(
                        fn (string $reason): string => 'integrity_'.$this->code($reason),
                        $this->stringList(
                            $decision['valuation_integrity_reasons'] ?? []
                        )
                    ),
                ])),
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if (! $this->verificationSafe($decision)) {
            return $this->verificationPlan(
                $decision,
                $matchCount,
                $stage,
                ['property_verification_or_evidence_incomplete'],
            );
        }

        if ($offerMissing !== []) {
            return $this->payload(
                actionCode: 'complete_seller_offer_packet',
                priority: 'medium',
                actionText: $offerQuestion
                    ?? 'Satıcı dosyasını yatırımcı ön teklifine hazırlamak için kalan en kritik taşınmaz bilgisini veya gerçek mülk fotoğrafını iste.',
                singleQuestion: $offerQuestion,
                blocking: false,
                reasonCodes: array_values(array_unique([
                    'seller_offer_packet_incomplete',
                    ...array_map(
                        fn (string $field): string => 'offer_missing_'.$this->code($field),
                        $offerMissing
                    ),
                ])),
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        // Optional conversation-completeness questions are allowed only after
        // hard valuation, verification and investor-offer-packet guards are clear.
        if ($sellerQuestion !== null) {
            return $this->payload(
                actionCode: 'complete_seller_discovery',
                priority: 'medium',
                actionText: $sellerQuestion,
                singleQuestion: $sellerQuestion,
                blocking: false,
                reasonCodes: ['seller_discovery_context_incomplete'],
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if ((bool) ($decision['ready_for_match'] ?? false) && $matchCount > 0) {
            return $this->payload(
                actionCode: 'review_safe_match_candidates',
                priority: 'medium',
                actionText: 'Güvenlik filtrelerinden geçen en güçlü yatırımcı eşleşmesini incele; uygunluğu olasılık olarak anlat ve gerçek kişi teyidi olmadan hazır alıcı veya kesin teklif iddiası kullanma.',
                singleQuestion: null,
                blocking: false,
                reasonCodes: ['seller_match_ready', 'safe_match_candidates_present'],
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if ((bool) ($decision['ready_for_match'] ?? false)) {
            return $this->payload(
                actionCode: 'keep_seller_ready_for_matching',
                priority: 'medium',
                actionText: 'Satıcı dosyasını doğrulanmış ve eşleşmeye hazır durumda tut; uygun yatırımcı profili oluşmadan hazır alıcı varmış gibi konuşma ve otomatik takip planlama.',
                singleQuestion: null,
                blocking: false,
                reasonCodes: ['seller_match_ready', 'no_safe_match_candidate'],
                matchCount: 0,
                stage: $stage,
            );
        }

        return $this->payload(
            actionCode: 'progress_seller_case',
            priority: 'medium',
            actionText: $this->fixedFallback($decision['next_best_action'] ?? null),
            singleQuestion: null,
            blocking: false,
            reasonCodes: ['seller_case_progression'],
            matchCount: $matchCount,
            stage: $stage,
        );
    }

    private function investorPlan(
        RealEstateProfile $profile,
        array $decision,
        int $matchCount,
        string $stage,
    ): array {
        $mandate = app(RealEstateInvestorMandateService::class)
            ->summaryForProfile($profile);
        $question = $this->cleanQuestion(
            $mandate['recommended_next_question'] ?? null
        );

        if ($question !== null) {
            return $this->payload(
                actionCode: 'complete_investor_mandate',
                priority: 'high',
                actionText: $question,
                singleQuestion: $question,
                blocking: true,
                reasonCodes: array_values(array_unique([
                    'investor_mandate_incomplete',
                    ...array_map(
                        fn (string $criterion): string => 'missing_'.$this->code($criterion),
                        $this->stringList(
                            $mandate['missing_high_value_criteria'] ?? []
                        )
                    ),
                ])),
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if ((bool) ($decision['ready_for_match'] ?? false) && $matchCount > 0) {
            return $this->payload(
                actionCode: 'review_safe_property_candidates',
                priority: 'medium',
                actionText: 'Yatırımcının açık kriterlerinden geçen en güçlü portföy eşleşmesini risk, veri güveni ve fiyat dayanağıyla incele; doğrulanmamış unsurları kesin bilgi gibi sunma.',
                singleQuestion: null,
                blocking: false,
                reasonCodes: ['investor_mandate_ready', 'safe_property_candidates_present'],
                matchCount: $matchCount,
                stage: $stage,
            );
        }

        if ((bool) ($decision['ready_for_match'] ?? false)) {
            return $this->payload(
                actionCode: 'keep_investor_mandate_ready',
                priority: 'medium',
                actionText: 'Yatırımcı kriterlerini eşleşmeye hazır durumda tut; uygun portföy oluşmadan varmış gibi ilan veya teklif uydurma ve otomatik takip planlama.',
                singleQuestion: null,
                blocking: false,
                reasonCodes: ['investor_mandate_ready', 'no_safe_property_candidate'],
                matchCount: 0,
                stage: $stage,
            );
        }

        return $this->payload(
            actionCode: 'progress_investor_qualification',
            priority: 'medium',
            actionText: $this->fixedFallback($decision['next_best_action'] ?? null),
            singleQuestion: null,
            blocking: false,
            reasonCodes: ['investor_qualification_progression'],
            matchCount: $matchCount,
            stage: $stage,
        );
    }

    private function verificationPlan(
        array $decision,
        int $matchCount,
        string $stage,
        array $reasonCodes,
    ): array {
        $status = trim((string) ($decision['verification_status'] ?? 'unknown'))
            ?: 'unknown';

        return $this->payload(
            actionCode: 'complete_property_verification',
            priority: 'high',
            actionText: $this->fixedFallback(
                $decision['next_best_action']
                ?? 'Taşınmaz doğrulamasını ve yeterli belge desteğini tamamla; doğrulanmadan yatırımcı eşleşmesini hazır fırsat gibi sunma.'
            ),
            singleQuestion: null,
            blocking: true,
            reasonCodes: array_values(array_unique([
                ...$reasonCodes,
                'verification_'.$this->code($status),
            ])),
            matchCount: $matchCount,
            stage: $stage,
        );
    }

    private function hardVerificationConflict(array $decision): bool
    {
        $status = mb_strtolower(trim((string) (
            $decision['verification_status'] ?? ''
        )));
        $riskScore = max(0, min(100, (int) (
            $decision['verification_risk_score'] ?? 0
        )));

        return in_array(
            $status,
            ['blocked', 'unsafe', 'critical', 'conflict'],
            true
        ) || $riskScore >= 85;
    }

    private function verificationSafe(array $decision): bool
    {
        if ((bool) ($decision['ready_for_match'] ?? false)) {
            return true;
        }

        $status = mb_strtolower(trim((string) (
            $decision['verification_status'] ?? ''
        )));

        return in_array($status, ['verified', 'safe'], true)
            && (bool) ($decision['evidence_sufficient_for_matching'] ?? false);
    }

    private function payload(
        string $actionCode,
        string $priority,
        string $actionText,
        ?string $singleQuestion,
        bool $blocking,
        array $reasonCodes,
        int $matchCount,
        string $stage,
    ): array {
        return [
            'action_code' => $actionCode,
            'priority' => $priority,
            'action_text' => trim($actionText),
            'single_question' => $singleQuestion,
            'blocking' => $blocking,
            'reason_codes' => array_values(array_unique(array_filter(
                $reasonCodes,
                fn (mixed $reason): bool => is_string($reason)
                    && trim($reason) !== ''
            ))),
            'match_count' => max(0, $matchCount),
            'stage' => $stage,
            'deterministic' => true,
            'guardrails' => [
                'one_primary_action_per_turn' => true,
                'follow_up_scheduling_allowed' => false,
                'binding_offer_claims_allowed' => false,
                'official_verification_claims_allowed_without_evidence' => false,
                'seller_private_floor_may_be_disclosed_to_investor' => false,
                'urgency_may_be_used_for_pressure' => false,
            ],
        ];
    }

    private function recordState(RealEstateProfile $profile, array $plan): void
    {
        if (! Schema::hasTable('real_estate_next_best_action_events')) {
            return;
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        $state = [
            'profile_id' => (int) $profile->id,
            'action_code' => (string) ($plan['action_code'] ?? ''),
            'priority' => (string) ($plan['priority'] ?? ''),
            'stage' => (string) ($plan['stage'] ?? ''),
            'reason_codes' => array_values($plan['reason_codes'] ?? []),
            'match_count' => (int) ($plan['match_count'] ?? 0),
            'blocking' => (bool) ($plan['blocking'] ?? false),
        ];
        $stateKey = hash(
            'sha256',
            (string) json_encode(
                $state,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        RealEstateNextBestActionEvent::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'state_key' => $stateKey,
            ],
            [
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile->id,
                'action_code' => $state['action_code'],
                'priority' => $state['priority'],
                'stage' => $state['stage'],
                'reason_codes' => $state['reason_codes'],
                'metadata' => [
                    'profile_type' => (string) $profile->profile_type,
                    'blocking' => $state['blocking'],
                    'match_count' => $state['match_count'],
                    'deterministic' => true,
                    'contains_raw_customer_message' => false,
                    'contains_contact_details' => false,
                    'contains_private_seller_floor' => false,
                    'follow_up_scheduling_allowed' => false,
                ],
                'occurred_at' => now(),
            ]
        );
    }

    private function comparable(array $value): array
    {
        unset($value['updated_at']);

        return $value;
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn (mixed $item): bool => is_string($item)
                && trim($item) !== ''
        ));
    }

    private function cleanQuestion(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || str_starts_with($value, 'Yeni soru sormadan')) {
            return null;
        }

        return mb_substr($value, 0, 320);
    }

    private function fixedFallback(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            return mb_substr(trim($value), 0, 500);
        }

        return 'Dosyadaki en yüksek değerli eksik veya riskli adımı tamamla; doğrulanmamış bilgi, kesin teklif veya otomatik takip üretme.';
    }

    private function code(string $value): string
    {
        $normalized = preg_replace(
            '/[^a-z0-9_\-]+/i',
            '_',
            mb_strtolower(trim($value))
        );

        return trim((string) $normalized, '_-') ?: 'unknown';
    }
}
