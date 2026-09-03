<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        return array_merge($this->defaults(), $control, [
            'seller_commission_percent' => self::SELLER_COMMISSION_PERCENT,
            'buyer_commission_percent' => self::BUYER_COMMISSION_PERCENT,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'legal_approval' => false,
        ]);
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

        return $control;
    }

    private function status(array $validated, ?string $signedAt, ?string $expiresAt): string
    {
        if ($signedAt && $expiresAt && Carbon::parse($expiresAt)->lt(Carbon::parse($signedAt))) {
            return 'review_required';
        }

        if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
            return 'expired';
        }

        $ready = $validated['mandate_type'] !== 'none'
            && $signedAt
            && $expiresAt
            && (bool) $validated['seller_presentation_consent']
            && (bool) $validated['commission_terms_acknowledged']
            && (bool) $validated['title_owner_confirmed']
            && (bool) $validated['authorization_document_present']
            && ! (bool) $validated['legal_review_required'];

        return $ready ? 'ready' : ((bool) $validated['legal_review_required'] ? 'review_required' : 'incomplete');
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
