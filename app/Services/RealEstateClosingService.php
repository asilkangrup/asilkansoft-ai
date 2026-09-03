<?php

namespace App\Services;

use App\Models\CrmActivity;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RealEstateClosingService
{
    private const DATA_KEY = 'transaction_closing_cases';

    public function acceptedDeals(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->get()
            ->keyBy('id');

        return $this->offerActivities()
            ->groupBy(fn (CrmActivity $activity): string => implode(':', [
                (int) data_get($activity->meta, 'seller_profile_id'),
                (int) data_get($activity->meta, 'investor_profile_id'),
            ]))
            ->map(function (Collection $events) use ($profiles): ?array {
                $events = $events->sortBy('id')->values();
                $latest = $events->last();
                $seller = $profiles->get((int) data_get($latest->meta, 'seller_profile_id'));
                $investor = $profiles->get((int) data_get($latest->meta, 'investor_profile_id'));

                if (
                    ! $seller
                    || ! $investor
                    || $seller->profile_type !== 'seller'
                    || ! in_array($investor->profile_type, ['investor', 'buyer'], true)
                    || ! $this->isAccepted($latest)
                ) {
                    return null;
                }

                $amount = $events->reverse()
                    ->map(fn (CrmActivity $event): mixed => data_get($event->meta, 'offer_amount'))
                    ->first(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0);

                return [
                    'key' => $seller->id.':'.$investor->id,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'seller' => $seller,
                    'investor' => $investor,
                    'agreed_price' => is_numeric($amount) ? (int) $amount : null,
                    'accepted_at' => $latest->created_at,
                ];
            })
            ->filter()
            ->values();
    }

    public function save(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        array $input,
        ?User $operator = null,
    ): array {
        $this->assertPair($seller, $investor);

        $deal = $this->acceptedDeals()->first(
            fn (array $candidate): bool => $candidate['seller_profile_id'] === $seller->id
                && $candidate['investor_profile_id'] === $investor->id
        );
        abort_unless($deal !== null, 422);

        $authorization = app(RealEstateAuthorizationService::class)->readiness($seller);
        abort_unless((bool) ($authorization['ready'] ?? false), 422);

        $validated = Validator::make($input, [
            'agreed_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'title_deed_verified' => ['required', 'boolean'],
            'identity_authority_verified' => ['required', 'boolean'],
            'encumbrance_checked' => ['required', 'boolean'],
            'tax_fee_checked' => ['required', 'boolean'],
            'payment_method_confirmed' => ['required', 'boolean'],
            'appointment_at' => ['nullable', 'date'],
            'appointment_location' => ['nullable', 'string', 'max:300'],
            'deposit_amount' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'deposit_received' => ['required', 'boolean'],
            'final_payment_verified' => ['required', 'boolean'],
            'deed_transfer_completed' => ['required', 'boolean'],
            'operator_note' => ['nullable', 'string', 'max:1500'],
        ])->validate();

        $checksComplete = collect([
            'title_deed_verified',
            'identity_authority_verified',
            'encumbrance_checked',
            'tax_fee_checked',
            'payment_method_confirmed',
        ])->every(fn (string $field): bool => (bool) $validated[$field]);

        if ((bool) $validated['deposit_received'] && ! is_numeric($validated['deposit_amount'] ?? null)) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Kapora alındıysa tutarı girilmelidir.',
            ]);
        }

        if (
            ((bool) $validated['final_payment_verified'] || (bool) $validated['deed_transfer_completed'])
            && (! $checksComplete || blank($validated['appointment_at'] ?? null))
        ) {
            throw ValidationException::withMessages([
                'deed_transfer_completed' => 'Ödeme veya devir kapanışı için tüm kontroller ve tapu randevusu tamamlanmalıdır.',
            ]);
        }

        $status = $this->status($validated, $checksComplete);
        $key = (string) $investor->id;

        return DB::transaction(function () use (
            $seller,
            $investor,
            $validated,
            $operator,
            $status,
            $key,
        ): array {
            $locked = RealEstateProfile::query()
                ->whereKey($seller->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($locked->belongsToIsolatedProductionScope(), 403);

            $data = is_array($locked->data) ? $locked->data : [];
            $cases = is_array($data[self::DATA_KEY] ?? null) ? $data[self::DATA_KEY] : [];
            $previous = is_array($cases[$key] ?? null) ? $cases[$key] : [];
            $now = now()->toIso8601String();

            $state = [
                'id' => $seller->id.':'.$investor->id,
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'agreed_price' => (int) $validated['agreed_price'],
                'title_deed_verified' => (bool) $validated['title_deed_verified'],
                'identity_authority_verified' => (bool) $validated['identity_authority_verified'],
                'encumbrance_checked' => (bool) $validated['encumbrance_checked'],
                'tax_fee_checked' => (bool) $validated['tax_fee_checked'],
                'payment_method_confirmed' => (bool) $validated['payment_method_confirmed'],
                'appointment_at' => filled($validated['appointment_at'] ?? null)
                    ? now()->parse($validated['appointment_at'])->toIso8601String()
                    : null,
                'appointment_location' => trim((string) ($validated['appointment_location'] ?? '')) ?: null,
                'deposit_amount' => is_numeric($validated['deposit_amount'] ?? null)
                    ? (int) $validated['deposit_amount']
                    : null,
                'deposit_received' => (bool) $validated['deposit_received'],
                'final_payment_verified' => (bool) $validated['final_payment_verified'],
                'deed_transfer_completed' => (bool) $validated['deed_transfer_completed'],
                'operator_note' => trim((string) ($validated['operator_note'] ?? '')) ?: null,
                'closed_at' => $status === 'completed' ? ($previous['closed_at'] ?? $now) : null,
                'updated_at' => $now,
                'updated_by_user_id' => $operator?->id,
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_private_seller_floor' => false,
                'contains_customer_pii' => false,
                'history' => collect((array) ($previous['history'] ?? []))
                    ->push([
                        'status' => $status,
                        'recorded_at' => $now,
                        'recorded_by_user_id' => $operator?->id,
                        'automatic_outbound_allowed' => false,
                    ])
                    ->take(-25)
                    ->values()
                    ->all(),
            ];

            $cases[$key] = $state;
            $data[self::DATA_KEY] = $cases;
            $locked->forceFill(['data' => $data])->saveQuietly();

            $locked->conversation?->activities()->create([
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'performed_by_user_id' => $operator?->id,
                'type' => 'real_estate_closing_control',
                'title' => 'Tapu ve işlem kapanışı güncellendi',
                'description' => $this->statusLabel($status),
                'new_value' => $status,
                'meta' => [
                    'scope' => 'isolated_real_estate',
                    'closing_case_id' => $state['id'],
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => $status,
                    'agreed_price' => (int) $validated['agreed_price'],
                    'automatic_outbound_allowed' => false,
                    'follow_up_scheduling_allowed' => false,
                    'contains_private_seller_floor' => false,
                    'contains_customer_pii' => false,
                    'human_confirmation_required' => true,
                ],
            ]);

            return $this->summary($state);
        });
    }

    public function summary(array $case): array
    {
        if (! $this->supportsCase($case)) {
            return [];
        }

        return array_merge($case, [
            'status_label' => $this->statusLabel((string) ($case['status'] ?? 'document_review')),
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_private_seller_floor' => false,
            'contains_customer_pii' => false,
        ]);
    }

    public function caseForPair(int $sellerProfileId, int $investorProfileId): ?array
    {
        $seller = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey($sellerProfileId)
            ->first();

        if (! $seller) {
            return null;
        }

        $case = data_get($seller->data, self::DATA_KEY.'.'.$investorProfileId);

        return is_array($case) && $this->supportsCase($case)
            ? $this->summary($case)
            : null;
    }

    private function offerActivities(): Collection
    {
        return CrmActivity::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('type', 'real_estate_operator_call')
            ->whereHas('conversationControl', fn ($query) => $query
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID))
            ->orderBy('id')
            ->limit(4000)
            ->get()
            ->filter(fn (CrmActivity $activity): bool => is_array($activity->meta)
                && is_numeric(data_get($activity->meta, 'seller_profile_id'))
                && is_numeric(data_get($activity->meta, 'investor_profile_id')));
    }

    private function isAccepted(CrmActivity $activity): bool
    {
        $kind = (string) data_get($activity->meta, 'task_kind');
        $outcome = (string) data_get($activity->meta, 'outcome');

        return $outcome === 'Kabul etti'
            && in_array($kind, ['seller_offer', 'seller_final'], true);
    }

    private function assertPair(RealEstateProfile $seller, RealEstateProfile $investor): void
    {
        abort_unless(
            $seller->belongsToIsolatedProductionScope()
            && $investor->belongsToIsolatedProductionScope()
            && $seller->profile_type === 'seller'
            && in_array($investor->profile_type, ['investor', 'buyer'], true),
            403
        );
    }

    private function supportsCase(array $case): bool
    {
        return (int) ($case['user_id'] ?? 0) === RealEstateIsolationService::USER_ID
            && (int) ($case['organization_id'] ?? 0) === RealEstateIsolationService::ORGANIZATION_ID
            && (int) ($case['ai_bot_id'] ?? 0) === RealEstateIsolationService::BOT_ID;
    }

    private function status(array $input, bool $checksComplete): string
    {
        if ((bool) $input['final_payment_verified'] && (bool) $input['deed_transfer_completed']) {
            return 'completed';
        }

        if ((bool) $input['final_payment_verified'] || (bool) $input['deed_transfer_completed']) {
            return 'completion_review';
        }

        if (filled($input['appointment_at'] ?? null) && $checksComplete) {
            return 'appointment_scheduled';
        }

        if ($checksComplete) {
            return 'appointment_ready';
        }

        return 'document_review';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'appointment_ready' => 'Tapu randevusuna hazır',
            'appointment_scheduled' => 'Tapu randevusu planlandı',
            'completion_review' => 'Kapanış teyidi gerekiyor',
            'completed' => 'Devir ve ödeme tamamlandı',
            default => 'Belge ve taraf kontrolü',
        };
    }
}
