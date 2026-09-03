<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RealEstateCommissionService
{
    public const SELLER_RATE_PERCENT = 2;

    public const BUYER_RATE_PERCENT = 2;

    private const DATA_KEY = 'commission_collection_cases';

    public function eligibleDeals(): Collection
    {
        return app(RealEstateClosingService::class)
            ->acceptedDeals()
            ->map(function (array $deal): ?array {
                $closing = app(RealEstateClosingService::class)->caseForPair(
                    (int) $deal['seller_profile_id'],
                    (int) $deal['investor_profile_id'],
                );

                if (! is_array($closing)) {
                    return null;
                }

                return array_merge($deal, [
                    'closing_case' => $closing,
                    'commission' => $this->summaryForPair(
                        (int) $deal['seller_profile_id'],
                        (int) $deal['investor_profile_id'],
                    ) ?: $this->emptySummary($closing),
                ]);
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

        $closing = app(RealEstateClosingService::class)->caseForPair($seller->id, $investor->id);
        abort_unless(is_array($closing), 422);

        $agreedPrice = is_numeric($closing['agreed_price'] ?? null)
            ? (int) $closing['agreed_price']
            : 0;
        abort_unless($agreedPrice > 0, 422);

        $sellerDue = $this->commissionAmount($agreedPrice, self::SELLER_RATE_PERCENT);
        $buyerDue = $this->commissionAmount($agreedPrice, self::BUYER_RATE_PERCENT);

        $validated = Validator::make($input, [
            'seller_collected_amount' => ['required', 'integer', 'min:0', 'max:'.$sellerDue],
            'buyer_collected_amount' => ['required', 'integer', 'min:0', 'max:'.$buyerDue],
            'seller_collected_at' => ['nullable', 'date'],
            'buyer_collected_at' => ['nullable', 'date'],
            'seller_receipt_reference' => ['nullable', 'string', 'max:150'],
            'buyer_receipt_reference' => ['nullable', 'string', 'max:150'],
            'operator_note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $sellerCollected = (int) $validated['seller_collected_amount'];
        $buyerCollected = (int) $validated['buyer_collected_amount'];

        if (
            ($sellerCollected > 0 || $buyerCollected > 0)
            && (string) ($closing['status'] ?? '') !== 'completed'
        ) {
            throw ValidationException::withMessages([
                'seller_collected_amount' => 'Tahsilat, tapu devri ve nihai ödeme doğrulanmadan kaydedilemez.',
            ]);
        }

        if ($sellerCollected > 0 && blank($validated['seller_collected_at'] ?? null)) {
            throw ValidationException::withMessages([
                'seller_collected_at' => 'Satıcı tahsilatı için gerçekleşme tarihi girilmelidir.',
            ]);
        }

        if ($buyerCollected > 0 && blank($validated['buyer_collected_at'] ?? null)) {
            throw ValidationException::withMessages([
                'buyer_collected_at' => 'Alıcı tahsilatı için gerçekleşme tarihi girilmelidir.',
            ]);
        }

        return DB::transaction(function () use (
            $seller,
            $investor,
            $closing,
            $validated,
            $operator,
            $agreedPrice,
            $sellerDue,
            $buyerDue,
            $sellerCollected,
            $buyerCollected,
        ): array {
            $locked = RealEstateProfile::query()
                ->whereKey($seller->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($locked->belongsToIsolatedProductionScope(), 403);

            $data = is_array($locked->data) ? $locked->data : [];
            $records = is_array($data[self::DATA_KEY] ?? null) ? $data[self::DATA_KEY] : [];
            $key = (string) $investor->id;
            $previous = is_array($records[$key] ?? null) ? $records[$key] : [];
            $sellerRemaining = max(0, $sellerDue - $sellerCollected);
            $buyerRemaining = max(0, $buyerDue - $buyerCollected);
            $totalDue = $sellerDue + $buyerDue;
            $totalCollected = $sellerCollected + $buyerCollected;
            $status = match (true) {
                $totalCollected === $totalDue => 'collected',
                $totalCollected > 0 => 'partial',
                default => 'awaiting',
            };
            $now = now()->toIso8601String();

            $record = [
                'id' => $seller->id.':'.$investor->id,
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'closing_status' => (string) ($closing['status'] ?? ''),
                'agreed_price' => $agreedPrice,
                'seller_rate_percent' => self::SELLER_RATE_PERCENT,
                'buyer_rate_percent' => self::BUYER_RATE_PERCENT,
                'seller_due_amount' => $sellerDue,
                'buyer_due_amount' => $buyerDue,
                'total_due_amount' => $totalDue,
                'seller_collected_amount' => $sellerCollected,
                'buyer_collected_amount' => $buyerCollected,
                'total_collected_amount' => $totalCollected,
                'seller_remaining_amount' => $sellerRemaining,
                'buyer_remaining_amount' => $buyerRemaining,
                'total_remaining_amount' => $sellerRemaining + $buyerRemaining,
                'seller_collected_at' => filled($validated['seller_collected_at'] ?? null)
                    ? now()->parse($validated['seller_collected_at'])->toIso8601String()
                    : null,
                'buyer_collected_at' => filled($validated['buyer_collected_at'] ?? null)
                    ? now()->parse($validated['buyer_collected_at'])->toIso8601String()
                    : null,
                'seller_receipt_reference' => trim((string) ($validated['seller_receipt_reference'] ?? '')) ?: null,
                'buyer_receipt_reference' => trim((string) ($validated['buyer_receipt_reference'] ?? '')) ?: null,
                'operator_note' => trim((string) ($validated['operator_note'] ?? '')) ?: null,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'updated_at' => $now,
                'updated_by_user_id' => $operator?->id,
                'automatic_outbound_allowed' => false,
                'automatic_payment_request_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_private_seller_floor' => false,
                'history' => collect((array) ($previous['history'] ?? []))
                    ->push([
                        'status' => $status,
                        'seller_collected_amount' => $sellerCollected,
                        'buyer_collected_amount' => $buyerCollected,
                        'recorded_at' => $now,
                        'recorded_by_user_id' => $operator?->id,
                        'automatic_outbound_allowed' => false,
                    ])
                    ->take(-25)
                    ->values()
                    ->all(),
            ];

            $records[$key] = $record;
            $data[self::DATA_KEY] = $records;
            $locked->forceFill(['data' => $data])->saveQuietly();

            $locked->conversation?->activities()->create([
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'performed_by_user_id' => $operator?->id,
                'type' => 'real_estate_commission_collection',
                'title' => 'Komisyon ve tahsilat kaydı güncellendi',
                'description' => $this->statusLabel($status),
                'new_value' => $status,
                'meta' => [
                    'scope' => 'isolated_real_estate',
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => $status,
                    'seller_rate_percent' => self::SELLER_RATE_PERCENT,
                    'buyer_rate_percent' => self::BUYER_RATE_PERCENT,
                    'total_due_amount' => $totalDue,
                    'total_collected_amount' => $totalCollected,
                    'total_remaining_amount' => $sellerRemaining + $buyerRemaining,
                    'automatic_outbound_allowed' => false,
                    'automatic_payment_request_allowed' => false,
                    'follow_up_scheduling_allowed' => false,
                    'contains_private_seller_floor' => false,
                    'contains_customer_pii' => false,
                    'human_confirmation_required' => true,
                ],
            ]);

            return $this->summary($record);
        });
    }

    public function summaryForPair(int $sellerProfileId, int $investorProfileId): ?array
    {
        $record = $this->recordForPair($sellerProfileId, $investorProfileId);

        return is_array($record) ? $this->summary($record) : null;
    }

    public function recordForPair(int $sellerProfileId, int $investorProfileId): ?array
    {
        $seller = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey($sellerProfileId)
            ->first();

        if (! $seller) {
            return null;
        }

        $record = data_get($seller->data, self::DATA_KEY.'.'.$investorProfileId);

        return is_array($record) && $this->supportsRecord($record) ? $record : null;
    }

    public function summary(array $record): array
    {
        if (! $this->supportsRecord($record)) {
            return [];
        }

        return collect($record)
            ->except([
                'operator_note',
                'seller_receipt_reference',
                'buyer_receipt_reference',
                'history',
            ])
            ->merge([
                'status_label' => $this->statusLabel((string) ($record['status'] ?? 'awaiting')),
                'automatic_outbound_allowed' => false,
                'automatic_payment_request_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_private_seller_floor' => false,
                'contains_customer_pii' => false,
            ])
            ->all();
    }

    public function emptySummary(array $closing): array
    {
        $price = is_numeric($closing['agreed_price'] ?? null) ? (int) $closing['agreed_price'] : 0;
        $sellerDue = $this->commissionAmount($price, self::SELLER_RATE_PERCENT);
        $buyerDue = $this->commissionAmount($price, self::BUYER_RATE_PERCENT);

        return [
            'status' => 'awaiting',
            'status_label' => $this->statusLabel('awaiting'),
            'agreed_price' => $price,
            'seller_rate_percent' => self::SELLER_RATE_PERCENT,
            'buyer_rate_percent' => self::BUYER_RATE_PERCENT,
            'seller_due_amount' => $sellerDue,
            'buyer_due_amount' => $buyerDue,
            'total_due_amount' => $sellerDue + $buyerDue,
            'seller_collected_amount' => 0,
            'buyer_collected_amount' => 0,
            'total_collected_amount' => 0,
            'seller_remaining_amount' => $sellerDue,
            'buyer_remaining_amount' => $buyerDue,
            'total_remaining_amount' => $sellerDue + $buyerDue,
            'automatic_outbound_allowed' => false,
            'automatic_payment_request_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_private_seller_floor' => false,
            'contains_customer_pii' => false,
        ];
    }

    public function totals(): array
    {
        $summaries = $this->eligibleDeals()->pluck('commission');

        return [
            'deal_count' => $summaries->count(),
            'total_due_amount' => $summaries->sum('total_due_amount'),
            'total_collected_amount' => $summaries->sum('total_collected_amount'),
            'total_remaining_amount' => $summaries->sum('total_remaining_amount'),
            'fully_collected_count' => $summaries->where('status', 'collected')->count(),
            'automatic_outbound_allowed' => false,
            'contains_customer_payload' => false,
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'collected' => 'Tam tahsil edildi',
            'partial' => 'Kısmi tahsilat',
            default => 'Tahsilat bekliyor',
        };
    }

    private function commissionAmount(int $agreedPrice, int $rate): int
    {
        return (int) round($agreedPrice * ($rate / 100));
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

    private function supportsRecord(array $record): bool
    {
        return (int) ($record['user_id'] ?? 0) === RealEstateIsolationService::USER_ID
            && (int) ($record['organization_id'] ?? 0) === RealEstateIsolationService::ORGANIZATION_ID
            && (int) ($record['ai_bot_id'] ?? 0) === RealEstateIsolationService::BOT_ID;
    }
}
