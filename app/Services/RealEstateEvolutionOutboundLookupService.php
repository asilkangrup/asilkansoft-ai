<?php

namespace App\Services;

use App\Models\RealEstateOutboundDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RealEstateEvolutionOutboundLookupService
{
    private string $url;

    private string $apiKey;

    public function __construct()
    {
        $this->url = rtrim((string) config('evolution.url'), '/');
        $this->apiKey = trim((string) config('evolution.api_key'));
    }

    /**
     * Find one exact provider-side copy of an already-attempted outbound.
     *
     * This method is intentionally read-only. It never sends a WhatsApp
     * message and returns no customer text or phone number to callers.
     *
     * Exact provider messages that are already attached to another isolated
     * outbound row are excluded before ambiguity is evaluated. This matters
     * when two separate customer turns legitimately receive the same answer
     * within the reconciliation window: a provider message already owned by
     * the later turn must not keep the earlier turn quarantined forever.
     *
     * @return array{provider_message_id:string, provider_timestamp:int}|null
     */
    public function findExactMatch(
        RealEstateOutboundDelivery $delivery,
    ): ?array {
        $this->assertScope($delivery);
        $this->assertConfigured();

        $digits = preg_replace('/\D+/', '', (string) $delivery->phone_number);

        if (! is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
            throw new RuntimeException('İzole Emlak AI outbound telefon kimliği geçersiz.');
        }

        $remoteJid = $digits.'@s.whatsapp.net';
        $response = $this->client()->post(
            $this->url.'/chat/findMessages/'.RealEstateIsolationService::INSTANCE,
            [
                'where' => [
                    'key' => [
                        'remoteJid' => $remoteJid,
                        'fromMe' => true,
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Evolution outbound mutabakat araması başarısız: HTTP '.$response->status()
            );
        }

        $records = $this->records($response->json());
        $startedAt = $delivery->sending_started_at ?: $delivery->created_at;

        if ($startedAt === null) {
            return null;
        }

        $windowStart = CarbonImmutable::parse($startedAt)->subSeconds(45)->timestamp;
        $windowEnd = CarbonImmutable::parse($startedAt)->addSeconds(150)->timestamp;
        $expectedHash = trim((string) $delivery->answer_hash);

        if ($expectedHash === '') {
            $expectedHash = hash('sha256', trim((string) $delivery->answer));
        }

        $matches = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            if ((bool) data_get($record, 'key.fromMe', false) !== true) {
                continue;
            }

            if (trim((string) data_get($record, 'key.remoteJid', '')) !== $remoteJid) {
                continue;
            }

            $providerMessageId = trim((string) data_get($record, 'key.id', ''));
            $providerTimestamp = data_get($record, 'messageTimestamp');

            if (
                $providerMessageId === ''
                || ! is_numeric($providerTimestamp)
                || (int) $providerTimestamp < $windowStart
                || (int) $providerTimestamp > $windowEnd
            ) {
                continue;
            }

            $text = $this->messageText($record);

            if ($text === null || ! hash_equals($expectedHash, hash('sha256', $text))) {
                continue;
            }

            $matches[$providerMessageId] = [
                'provider_message_id' => $providerMessageId,
                'provider_timestamp' => (int) $providerTimestamp,
            ];
        }

        $matches = $this->withoutProviderMessagesClaimedByOtherDeliveries(
            delivery: $delivery,
            matches: $matches,
        );

        if (count($matches) !== 1) {
            return null;
        }

        return array_values($matches)[0];
    }

    /**
     * @param array<string, array{provider_message_id:string, provider_timestamp:int}> $matches
     * @return array<string, array{provider_message_id:string, provider_timestamp:int}>
     */
    private function withoutProviderMessagesClaimedByOtherDeliveries(
        RealEstateOutboundDelivery $delivery,
        array $matches,
    ): array {
        if ($matches === []) {
            return [];
        }

        $claimed = RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->where('id', '!=', $delivery->id)
            ->whereIn('whatsapp_message_id', array_keys($matches))
            ->pluck('whatsapp_message_id')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->all();

        foreach ($claimed as $providerMessageId) {
            unset($matches[$providerMessageId]);
        }

        return $matches;
    }

    private function records(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['messages.records', 'records', 'data', 'messages'] as $path) {
            $value = data_get($payload, $path);

            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return [];
    }

    private function messageText(array $record): ?string
    {
        foreach ([
            'message.conversation',
            'message.extendedTextMessage.text',
            'message.text',
        ] as $path) {
            $value = data_get($record, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);
    }

    private function assertConfigured(): void
    {
        if ($this->url === '' || $this->apiKey === '') {
            throw new RuntimeException('Evolution outbound mutabakat ayarları eksik.');
        }
    }

    private function assertScope(RealEstateOutboundDelivery $delivery): void
    {
        if (
            (int) $delivery->user_id !== RealEstateIsolationService::USER_ID
            || (int) $delivery->organization_id !== RealEstateIsolationService::ORGANIZATION_ID
            || (int) $delivery->ai_bot_id !== RealEstateIsolationService::BOT_ID
            || trim((string) $delivery->instance) !== RealEstateIsolationService::INSTANCE
        ) {
            throw new RuntimeException('İzole Emlak AI Evolution outbound mutabakat kapsamı ihlali.');
        }
    }
}
