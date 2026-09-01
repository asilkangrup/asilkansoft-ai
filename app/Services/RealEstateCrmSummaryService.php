<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;

class RealEstateCrmSummaryService
{
    public function update(ConversationControl $conversation): array
    {
        $isolation = app(RealEstateIsolationService::class);

        if (! $isolation->supportsConversation($conversation)) {
            return $this->result(false, 'scope_mismatch');
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->first();

        if (! $profile) {
            return $this->result(false, 'profile_not_found');
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $freshness = app(RealEstateValuationFreshnessService::class)->assess($profile);

        $summary = $this->buildSummary(
            profileType: (string) $profile->profile_type,
            data: $data,
            valuation: $valuation,
            decision: $decision,
            verification: $verification,
            freshness: $freshness,
        );
        $nextBestAction = $this->nextBestAction(
            decision: $decision,
            verification: $verification,
            freshness: $freshness,
            profileType: (string) $profile->profile_type,
        );
        $messageCount = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('session_id', $conversation->session_id)
            ->whereIn('role', ['user', 'assistant'])
            ->count();

        $conversation->forceFill([
            'ai_summary' => $summary,
            'next_best_action' => $nextBestAction,
            'ai_summary_updated_at' => now(),
            'ai_summary_message_count' => $messageCount,
        ])->save();
        $conversation->refresh();

        Log::info('REAL ESTATE STRUCTURED CRM SUMMARY UPDATED', [
            'conversation_control_id' => $conversation->id,
            'user_id' => RealEstateIsolationService::USER_ID,
            'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
            'ai_bot_id' => RealEstateIsolationService::BOT_ID,
            'profile_type' => $profile->profile_type,
            'message_count' => $messageCount,
            'shared_openai_used' => false,
        ]);

        return [
            'updated' => true,
            'reason' => 'real_estate_structured_summary',
            'summary' => $conversation->ai_summary,
            'next_best_action' => $conversation->next_best_action,
            'source' => 'real_estate_structured',
            'shared_openai_used' => false,
        ];
    }

    private function buildSummary(
        string $profileType,
        array $data,
        array $valuation,
        array $decision,
        array $verification,
        array $freshness,
    ): string {
        $parts = [];
        $role = match ($profileType) {
            'seller' => 'Satıcı',
            'investor', 'buyer' => 'Yatırımcı/alıcı',
            default => 'Gayrimenkul müşterisi',
        };

        $property = array_values(array_filter([
            $this->safeLabel($data['property_type'] ?? null),
            $this->safeLabel($data['city'] ?? null),
            $this->safeLabel($data['district'] ?? null),
            $this->safeLabel($data['neighborhood'] ?? null),
            $this->areaLabel($data['area_sqm'] ?? null),
        ]));

        $parts[] = $property !== []
            ? $role.' profili: '.implode(' / ', $property).'.'
            : $role.' profili; temel taşınmaz kriterleri henüz tamamlanıyor.';

        $stage = $this->safeToken($decision['stage'] ?? null);
        $temperature = $this->safeToken($decision['lead_temperature'] ?? null);
        $score = is_numeric($decision['lead_score'] ?? null)
            ? max(0, min(100, (int) $decision['lead_score']))
            : null;

        if ($stage !== null || $temperature !== null || $score !== null) {
            $leadBits = array_values(array_filter([
                $stage !== null ? 'aşama '.$stage : null,
                $temperature !== null ? 'sıcaklık '.$temperature : null,
                $score !== null ? 'skor '.$score.'/100' : null,
            ]));
            $parts[] = 'CRM durumu: '.implode(', ', $leadBits).'.';
        }

        $verificationStatus = $this->safeToken($verification['status'] ?? null);
        $risk = is_numeric($verification['risk_score'] ?? null)
            ? max(0, min(100, (int) $verification['risk_score']))
            : null;
        $safeToMatch = (bool) ($verification['safe_to_match'] ?? false);

        if ($verificationStatus !== null) {
            $parts[] = 'Doğrulama desteği '.$verificationStatus
                .($risk !== null ? ' (risk '.$risk.'/100)' : '')
                .'; eşleşme güvenlik kapısı '.($safeToMatch ? 'uygun' : 'beklemede').'.';
        }

        $fresh = ($freshness['usable_for_decision'] ?? false) === true;
        $marketMin = $this->positiveNumber($valuation['market_min'] ?? null);
        $marketMax = $this->positiveNumber($valuation['market_max'] ?? null);

        if ($fresh && $marketMin !== null && $marketMax !== null) {
            $parts[] = 'Güncel emsal araştırmasına dayalı piyasa aralığı '
                .$this->money($marketMin).'–'.$this->money($marketMax)
                .' TL; ilan fiyatı gerçekleşmiş satış fiyatı kabul edilmez.';
        } elseif ($profileType === 'seller') {
            $status = $this->safeToken($freshness['status'] ?? null) ?? 'missing';
            $parts[] = 'Değerleme durumu '.$status.'; güncel ve yeterli emsal oluşmadan fiyat ankrajı yapılmamalı.';
        }

        return mb_substr(implode(' ', array_slice($parts, 0, 4)), 0, 1200);
    }

    private function nextBestAction(
        array $decision,
        array $verification,
        array $freshness,
        string $profileType,
    ): string {
        $verificationStatus = (string) ($verification['status'] ?? '');

        if (in_array($verificationStatus, ['blocked', 'high_risk'], true)) {
            return $this->safeAction($verification['next_best_action'] ?? null)
                ?? 'Belge/görsel ile müşteri beyanı arasındaki kritik çelişkiyi netleştir; doğrulamadan eşleştirme veya kesin fiyat iddiası yapma.';
        }

        if ($profileType === 'seller' && ! ($freshness['usable_for_decision'] ?? false)) {
            return 'Güncel ve güvenilir emsal setini tamamla; ilan fiyatını gerçekleşmiş satış gibi kullanmadan değerlemeyi yenile.';
        }

        return $this->safeAction($decision['next_best_action'] ?? null)
            ?? $this->safeAction($verification['next_best_action'] ?? null)
            ?? 'Müşterinin eksik kritik taşınmaz veya yatırım kriterini tek kısa soruyla tamamla.';
    }

    private function safeAction(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        // Only trusted structured decision fields reach here. Still strip common
        // direct-contact patterns before saving a CRM summary/action.
        $value = preg_replace('/\b\+?90?\s*5\d{2}[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}\b/u', '[telefon gizlendi]', $value) ?? $value;
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[e-posta gizlendi]', $value) ?? $value;

        return mb_substr($value, 0, 600);
    }

    private function safeLabel(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_substr(preg_replace('/\s+/u', ' ', $value) ?? $value, 0, 80);
    }

    private function safeToken(mixed $value): ?string
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return preg_match('/^[a-z0-9_\-]{1,40}$/', $value) ? $value : null;
    }

    private function areaLabel(mixed $value): ?string
    {
        $number = $this->positiveNumber($value);

        return $number !== null ? rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.').' m²' : null;
    }

    private function positiveNumber(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function result(bool $updated, string $reason): array
    {
        return [
            'updated' => $updated,
            'reason' => $reason,
            'summary' => null,
            'next_best_action' => null,
            'source' => 'real_estate_structured',
            'shared_openai_used' => false,
        ];
    }
}
