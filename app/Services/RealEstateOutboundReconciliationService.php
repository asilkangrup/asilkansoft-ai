<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\RealEstateOutboundDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateOutboundReconciliationService
{
    public function __construct(
        private readonly RealEstateEvolutionOutboundLookupService $lookup,
        private readonly RealEstateOutboundDeliveryService $deliveryService,
    ) {
    }

    /**
     * Reconcile only aged network-boundary records.
     *
     * No path in this service sends a WhatsApp message. A provider-side exact
     * match is confirmed as sent; an aged `sending` without evidence is moved
     * to `uncertain` so normal delivery code can never retry it automatically.
     * Existing uncertain records remain quarantined when no exact proof exists.
     *
     * @return array{examined:int,confirmed_sent:int,quarantined:int,unchanged:int,lookup_errors:int}
     */
    public function reconcile(int $limit = 10, int $ageSeconds = 120): array
    {
        $limit = max(1, min(50, $limit));
        $ageSeconds = max(60, min(3600, $ageSeconds));
        $cutoff = now()->subSeconds($ageSeconds);

        $deliveries = RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->whereIn('status', ['sending', 'uncertain'])
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('sending_started_at', '<=', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested
                            ->whereNull('sending_started_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->oldest('id')
            ->limit($limit)
            ->get();

        $stats = [
            'examined' => 0,
            'confirmed_sent' => 0,
            'quarantined' => 0,
            'unchanged' => 0,
            'lookup_errors' => 0,
        ];

        foreach ($deliveries as $delivery) {
            $stats['examined']++;

            try {
                $match = $this->lookup->findExactMatch($delivery);
            } catch (Throwable $exception) {
                $stats['lookup_errors']++;
                $stats['unchanged']++;

                Log::warning('REAL ESTATE OUTBOUND RECONCILIATION LOOKUP FAILED', [
                    'delivery_id' => $delivery->id,
                    'status' => $delivery->status,
                    'error_class' => $exception::class,
                    'message' => mb_substr($exception->getMessage(), 0, 500),
                ]);

                continue;
            }

            if ($match !== null) {
                if ($this->confirmSent($delivery, $match)) {
                    $stats['confirmed_sent']++;
                } else {
                    $stats['unchanged']++;
                }

                continue;
            }

            if ($delivery->status === 'sending') {
                if ($this->quarantineSending($delivery)) {
                    $stats['quarantined']++;
                } else {
                    $stats['unchanged']++;
                }

                continue;
            }

            $stats['unchanged']++;
        }

        return $stats;
    }

    private function confirmSent(
        RealEstateOutboundDelivery $delivery,
        array $match,
    ): bool {
        $providerMessageId = trim((string) ($match['provider_message_id'] ?? ''));
        $providerTimestamp = $match['provider_timestamp'] ?? null;

        if ($providerMessageId === '' || ! is_numeric($providerTimestamp)) {
            return false;
        }

        $updated = DB::transaction(function () use (
            $delivery,
            $providerMessageId,
            $providerTimestamp,
        ): ?RealEstateOutboundDelivery {
            $locked = RealEstateOutboundDelivery::query()
                ->whereKey($delivery->id)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('instance', RealEstateIsolationService::INSTANCE)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! in_array($locked->status, ['sending', 'uncertain'], true)) {
                return null;
            }

            // Fail closed if another isolated delivery acquired this provider
            // message id after the read-only lookup but before this transaction.
            // One Evolution message may belong to one outbound row only.
            $alreadyClaimed = RealEstateOutboundDelivery::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('instance', RealEstateIsolationService::INSTANCE)
                ->where('id', '!=', $locked->id)
                ->where('whatsapp_message_id', $providerMessageId)
                ->exists();

            if ($alreadyClaimed) {
                Log::warning('REAL ESTATE OUTBOUND PROVIDER MESSAGE ALREADY CLAIMED', [
                    'delivery_id' => $locked->id,
                    'provider_message_id_hash' => hash('sha256', $providerMessageId),
                ]);

                return null;
            }

            $locked->forceFill([
                'status' => 'sent',
                'whatsapp_message_id' => $providerMessageId,
                'sent_at' => CarbonImmutable::createFromTimestampUTC((int) $providerTimestamp),
                'last_error' => null,
            ])->save();

            return $locked->fresh();
        }, 3);

        if (! $updated) {
            return false;
        }

        $this->deliveryService->persistAssistantMessage($updated);

        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->first();

        if (app(RealEstateIsolationService::class)->supportsProductionBot($bot)) {
            $this->deliveryService->consumeTrialOnce($updated->fresh(), $bot);
        }

        Log::info('REAL ESTATE OUTBOUND RECONCILED AS SENT', [
            'delivery_id' => $updated->id,
            'attempts' => $updated->attempts,
            'provider_evidence' => true,
        ]);

        return true;
    }

    private function quarantineSending(
        RealEstateOutboundDelivery $delivery,
    ): bool {
        return DB::transaction(function () use ($delivery): bool {
            $locked = RealEstateOutboundDelivery::query()
                ->whereKey($delivery->id)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('instance', RealEstateIsolationService::INSTANCE)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'sending') {
                return false;
            }

            $locked->forceFill([
                'status' => 'uncertain',
                'last_error' => 'Ağ sınırında kalan gönderim için Evolution tarafında tekil ve kesin kanıt bulunamadı; otomatik yeniden gönderim engellendi.',
            ])->save();

            Log::warning('REAL ESTATE STALE OUTBOUND QUARANTINED', [
                'delivery_id' => $locked->id,
                'attempts' => $locked->attempts,
            ]);

            return true;
        }, 3);
    }
}
