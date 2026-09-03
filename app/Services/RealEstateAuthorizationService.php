<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class RealEstateAuthorizationService
{
    public const SELLER_COMMISSION_PERCENT = 2;

    public const BUYER_COMMISSION_PERCENT = 2;

    public function summarize(RealEstateProfile $profile): array
    {
        if (! $profile->belongsToIsolatedProductionScope() || $profile->profile_type !== 'seller') {
            return [];
        }

        $control = is_array(data_get($profile->data, 'authorization_control'))
            ? data_get($profile->data, 'authorization_control')
            : [];
        $state = array_merge($this->defaults(), $control);
        $effectiveStatus = $this->effectiveStatus($state);
        $blockingReasons = $this->blockingReasons($state, $effectiveStatus);

        return array_merge($state, [
            'status' => $effectiveStatus,
            'status_label' => $this->statusLabel($effectiveStatus),
            'blocking_reason_codes' => $blockingReasons,
            'ready' => $effectiveStatus === 'ready',
            'ready_for_investor_presentation' => $effectiveStatus === 'ready',
            'ready_for_offer_collection' => $effectiveStatus === 'ready',
            'seller_commission_percent' => self::SELLER_COMMISSION_PERCENT,
            'buyer_commission_percent' => self::BUYER_COMMISSION_PERCENT,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'legal_approval' => false,
            'recommended_operator_action' => $this->recommendedOperatorAction(
                $effectiveStatus,
                $blockingReasons,
            ),
        ]);
    }

    public function readiness(RealEstateProfile $profile): array
    {
        $summary = $this->summarize($profile);

        if ($summary === []) {
            return [];
        }

        return [
            'status' => $summary['status'],
            'status_label' => $summary['status_label'],
            'ready' => (bool) $summary['ready'],
            'ready_for_investor_presentation' => (bool) $summary['ready_for_investor_presentation'],
            'ready_for_offer_collection' => (bool) $summary['ready_for_offer_collection'],
            'mandate_type' => $summary['mandate_type'],
            'signed_at' => $summary['signed_at'],
            'expires_at' => $summary['expires_at'],
            'seller_presentation_consent' => (bool) $summary['seller_presentation_consent'],
            'commission_terms_acknowledged' => (bool) $summary['commission_terms_acknowledged'],
            'title_owner_confirmed' => (bool) $summary['title_owner_confirmed'],
            'authorization_document_present' => (bool) $summary['authorization_document_present'],
            'legal_review_required' => (bool) $summary['legal_review_required'],
            'blocking_reason_codes' => array_values($summary['blocking_reason_codes'] ?? []),
            'recommended_operator_action' => $summary['recommended_operator_action'],
            'seller_commission_percent' => self::SELLER_COMMISSION_PERCENT,
            'buyer_commission_percent' => self::BUYER_COMMISSION_PERCENT,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
        ];
    }

    public function update(RealEstateProfile $profile, array $input, ?User $operator = null): array
    {
        abort_unless(
            $profile->belongsToIsolatedProductionScope() && $profile->profile_type === 'seller',
            403
        );

        $validated = Validator::make($input, [
            'mandate_type' => ['required', Rule::in(['none', 'non_exclusive', 'exclusive'])],
            'signed_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'seller_presentation_consent' => ['required', 'boolean'],
            'commission_terms_acknowledged' => ['required', 'boolean'],
            'title_owner_confirmed' => ['required', 'boolean'],
            'authorization_document_present' => ['required', 'boolean'],
            'legal_review_required' => ['required', 'boolean'],
            'operator_note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $signedAt = filled($validated['signed_at'] ?? null)
            ? Carbon::parse($validated['signed_at'])->toDateString()
            : null;
        $expiresAt = filled($validated['expires_at'] ?? null)
            ? Carbon::parse($validated['expires_at'])->toDateString()
            : null;

        $status = $this->status($validated, $signedAt, $expiresAt);
        $previous = $this->summarize($profile);
        $now = now()->toIso8601String();

        $control = [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'mandate_type' => $validated['mandate_type'],
            'signed_at' => $signedAt,
            'expires_at' => $expiresAt,
            'seller_presentation_consent' => (bool) $validated['seller_presentation_consent'],
            'commission_terms_acknowledged' => (bool) $validated['commission_terms_acknowledged'],
            'title_owner_confirmed' => (bool) $validated['title_owner_confirmed'],
            'authorization_document_present' => (bool) $validated['authorization_document_present'],
            'legal_review_required' => (bool) $validated['legal_review_required'],
            'operator_note' => trim((string) ($validated['operator_note'] ?? '')) ?: null,
            'seller_commission_percent' => self::SELLER_COMMISSION_PERCENT,
            'buyer_commission_percent' => self::BUYER_COMMISSION_PERCENT,
            'updated_at' => $now,
            'updated_by_user_id' => $operator?->id,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'legal_approval' => false,
        ];

        $history = collect((array) ($previous['history'] ?? []))
            ->push([
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'mandate_type' => $validated['mandate_type'],
                'recorded_at' => $now,
                'recorded_by_user_id' => $operator?->id,
                'automatic_outbound_allowed' => false,
            ])
            ->take(-25)
            ->values()
            ->all();

        $control['history'] = $history;

        $data = is_array($profile->data) ? $profile->data : [];
        $data['authorization_control'] = $control;
        $profile->updateQuietly(['data' => $data]);

        $conversation = $profile->conversation;
        if ($conversation) {
            $conversation->activities()->create([
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'performed_by_user_id' => $operator?->id,
                'type' => 'real_estate_authorization_control',
                'title' => 'Yetkilendirme ve sözleşme kontrolü güncellendi',
                'description' => $this->statusLabel($status),
                'old_value' => $previous['status'] ?? null,
                'new_value' => $status,
                'meta' => [
                    'scope' => 'isolated_real_estate',
                    'profile_id' => $profile->id,
                    'mandate_type' => $validated['mandate_type'],
                    'seller_commission_percent' => self::SELLER_COMMISSION_PERCENT,
                    'buyer_commission_percent' => self::BUYER_COMMISSION_PERCENT,
                    'automatic_outbound_allowed' => false,
                    'follow_up_scheduling_allowed' => false,
                ],
            ]);
        }

        $this->propagateReadiness($profile->fresh());

        return $this->summarize($profile->fresh());
    }

    private function effectiveStatus(array $state): string
    {
        try {
            $signedAt = filled($state['signed_at'] ?? null)
                ? Carbon::parse($state['signed_at'])->toDateString()
                : null;
            $expiresAt = filled($state['expires_at'] ?? null)
                ? Carbon::parse($state['expires_at'])->toDateString()
                : null;
        } catch (Throwable) {
            return 'review_required';
        }

        return $this->status($state, $signedAt, $expiresAt);
    }

    private function status(array $validated, ?string $signedAt, ?string $expiresAt): string
    {
        if ($signedAt && $expiresAt && Carbon::parse($expiresAt)->lt(Carbon::parse($signedAt))) {
            return 'review_required';
        }

        if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
            return 'expired';
        }

        $ready = ($validated['mandate_type'] ?? 'none') !== 'none'
            && $signedAt
            && $expiresAt
            && (bool) ($validated['seller_presentation_consent'] ?? false)
            && (bool) ($validated['commission_terms_acknowledged'] ?? false)
            && (bool) ($validated['title_owner_confirmed'] ?? false)
            && (bool) ($validated['authorization_document_present'] ?? false)
            && ! (bool) ($validated['legal_review_required'] ?? false);

        return $ready
            ? 'ready'
            : ((bool) ($validated['legal_review_required'] ?? false) ? 'review_required' : 'incomplete');
    }

    private function blockingReasons(array $state, string $status): array
    {
        $reasons = [];

        if (($state['mandate_type'] ?? 'none') === 'none') {
            $reasons[] = 'mandate_missing';
        }
        if (! filled($state['signed_at'] ?? null)) {
            $reasons[] = 'signed_at_missing';
        }
        if (! filled($state['expires_at'] ?? null)) {
            $reasons[] = 'expires_at_missing';
        }
        if (! (bool) ($state['seller_presentation_consent'] ?? false)) {
            $reasons[] = 'presentation_consent_missing';
        }
        if (! (bool) ($state['commission_terms_acknowledged'] ?? false)) {
            $reasons[] = 'commission_terms_unacknowledged';
        }
        if (! (bool) ($state['title_owner_confirmed'] ?? false)) {
            $reasons[] = 'title_owner_unconfirmed';
        }
        if (! (bool) ($state['authorization_document_present'] ?? false)) {
            $reasons[] = 'authorization_document_missing';
        }
        if ((bool) ($state['legal_review_required'] ?? false)) {
            $reasons[] = 'legal_review_required';
        }
        if ($status === 'expired') {
            $reasons[] = 'authorization_expired';
        }
        if ($status === 'review_required' && ! (bool) ($state['legal_review_required'] ?? false)) {
            $reasons[] = 'authorization_dates_or_record_require_review';
        }

        return array_values(array_unique($reasons));
    }

    private function recommendedOperatorAction(string $status, array $blockingReasons): string
    {
        if ($status === 'ready') {
            return 'Yetkilendirme kontrolü güncel. Yatırımcı sunumu ve gerçek teklif toplama adımı yalnız operatör incelemesiyle ilerleyebilir; otomatik mesaj gönderme.';
        }

        if ($status === 'expired') {
            return 'Yetkilendirme süresi dolmuş. Yeni/geçerli yetkilendirme kaydı tamamlanmadan yatırımcı sunumu, PDF dışa aktarımı veya teklif toplama sürecini başlatma.';
        }

        if ($status === 'review_required') {
            return 'Yetkilendirme kaydı hukuki/operasyonel inceleme gerektiriyor. İnceleme tamamlanmadan yatırımcı sunumu veya teklif toplama adımına geçme.';
        }

        $first = $blockingReasons[0] ?? 'authorization_incomplete';

        return match ($first) {
            'mandate_missing' => 'Yetkilendirme türünü ve geçerli imzalı kaydı tamamlamadan yatırımcı sunumu veya teklif toplama adımına geçme.',
            'signed_at_missing', 'expires_at_missing' => 'Yetkilendirmenin imza ve geçerlilik tarihlerini tamamlamadan yatırımcı sunumu veya teklif toplama adımına geçme.',
            'presentation_consent_missing' => 'Satıcının yatırımcı sunum onayını kaydetmeden dış paylaşım veya yatırımcı sunumu yapma.',
            'commission_terms_unacknowledged' => 'Hizmet bedeli koşulları teyit edilmeden yatırımcı teklif toplama sürecine geçme.',
            'title_owner_unconfirmed' => 'Tapu sahibi ilişkisi operatör tarafından teyit edilmeden yatırımcı sunumu veya teklif toplama adımına geçme.',
            'authorization_document_missing' => 'Yetkilendirme belgesi kontrol edilmeden yatırımcı sunumu veya teklif toplama adımına geçme.',
            default => 'Yetkilendirme kontrolündeki eksikleri tamamlamadan yatırımcı sunumu veya teklif toplama adımına geçme.',
        };
    }

    private function propagateReadiness(RealEstateProfile $profile): void
    {
        if (! $profile->belongsToIsolatedProductionScope() || $profile->profile_type !== 'seller') {
            return;
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        app(RealEstateSellerInvestorHandoffService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateNextBestActionService::class)->process($conversation);
        app(RealEstateAuthorizationNextBestActionService::class)->sync($conversation);
        app(RealEstateFactConsistencyActionService::class)->sync($conversation);
        app(RealEstateNextBestActionDecisionBridgeService::class)->sync($conversation);
        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());
        app(RealEstateCaseLifecycleService::class)->sync($profile->fresh());
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'ready' => 'Sunum ve teklif süreci için hazır',
            'expired' => 'Yetkilendirme süresi dolmuş',
            'review_required' => 'Hukuki / operasyonel inceleme gerekli',
            default => 'Eksik kontrol bulunuyor',
        };
    }

    private function defaults(): array
    {
        return [
            'status' => 'incomplete',
            'status_label' => 'Eksik kontrol bulunuyor',
            'mandate_type' => 'none',
            'signed_at' => null,
            'expires_at' => null,
            'seller_presentation_consent' => false,
            'commission_terms_acknowledged' => false,
            'title_owner_confirmed' => false,
            'authorization_document_present' => false,
            'legal_review_required' => false,
            'operator_note' => null,
            'history' => [],
        ];
    }
}
