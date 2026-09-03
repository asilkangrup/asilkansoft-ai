<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateInvestorBroadcastService
{
    private const GROUP_NAME = 'Emlak AI - Yatırımcı Fırsatları';
    private const CONTACT_PHONE = '05364750098';

    public function __construct(
        private readonly WhatsAppService $whatsAppService,
    ) {
    }

    public function sync(RealEstateProfile $profile): void
    {
        if (
            ! $profile->belongsToIsolatedProductionScope()
            || $profile->profile_type !== 'seller'
        ) {
            return;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $handoff = is_array($data['seller_fast_cash_handoff'] ?? null)
            ? $data['seller_fast_cash_handoff']
            : [];
        $notifications = is_array($data['group_notifications'] ?? null)
            ? $data['group_notifications']
            : [];

        if (
            blank($handoff['completed_at'] ?? null)
            || filled($notifications['investor_broadcast_sent_at'] ?? null)
        ) {
            return;
        }

        try {
            $message = implode("\n", [
                '🏠 YENİ YATIRIM FIRSATI',
                '',
                'Referans No: PF-'.$profile->id,
                'Taşınmaz Türü: '.$this->value($data, ['property_type']),
                'Konum: '.$this->location($data),
                'Alan: '.$this->singleArea($data),
                'İstenen Fiyat: '.$this->price($data['asking_price'] ?? null),
                'Ada/Parsel: '.$this->parcel($data),
                '',
                'Teklif vermek ve detaylı bilgi almak için '.self::CONTACT_PHONE.' numarasını arayabilirsiniz.',
            ]);

            if (! $this->sendToNamedGroup($message)) {
                return;
            }

            $notifications['investor_broadcast_sent_at'] = now()->toIso8601String();
            $data['group_notifications'] = $notifications;
            $profile->forceFill(['data' => $data])->saveQuietly();
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE INVESTOR BROADCAST FAILED', [
                'profile_id' => $profile->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendToNamedGroup(string $message): bool
    {
        $groups = Cache::remember(
            'real-estate-whatsapp-groups:'.RealEstateIsolationService::INSTANCE,
            now()->addMinutes(10),
            fn (): array => $this->whatsAppService->fetchGroups(RealEstateIsolationService::INSTANCE),
        );

        $group = collect($groups)->first(
            fn (array $group): bool => trim((string) ($group['name'] ?? '')) === self::GROUP_NAME
        );

        $jid = is_array($group) ? trim((string) ($group['id'] ?? '')) : '';

        if ($jid === '' || ! str_ends_with($jid, '@g.us')) {
            Log::info('REAL ESTATE INVESTOR BROADCAST GROUP NOT FOUND YET', [
                'group_name' => self::GROUP_NAME,
                'instance' => RealEstateIsolationService::INSTANCE,
            ]);

            return false;
        }

        $this->whatsAppService->sendGroupText(
            instanceName: RealEstateIsolationService::INSTANCE,
            groupJid: $jid,
            text: $message,
        );

        return true;
    }

    private function value(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return 'Belirtilmedi';
    }

    private function location(array $data): string
    {
        $parts = array_filter([
            $data['city'] ?? null,
            $data['district'] ?? null,
            $data['neighborhood'] ?? null,
        ], fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');

        return $parts === [] ? 'Belirtilmedi' : implode(' / ', array_map('strval', $parts));
    }

    private function singleArea(array $data): string
    {
        foreach (['area_sqm', 'sqm', 'land_area_sqm'] as $key) {
            if (is_numeric($data[$key] ?? null)) {
                return number_format((float) $data[$key], 0, ',', '.').' m²';
            }
        }

        return 'Belirtilmedi';
    }

    private function price(mixed $value): string
    {
        return is_numeric($value) && (float) $value > 0
            ? number_format((float) $value, 0, ',', '.').' TL'
            : 'Belirtilmedi';
    }

    private function parcel(array $data): string
    {
        $ada = $data['block'] ?? $data['ada'] ?? null;
        $parsel = $data['parcel'] ?? $data['parsel'] ?? null;

        if (blank($ada) && blank($parsel)) {
            return 'Belirtilmedi';
        }

        return 'Ada '.($ada ?: '—').' / Parsel '.($parsel ?: '—');
    }
}
