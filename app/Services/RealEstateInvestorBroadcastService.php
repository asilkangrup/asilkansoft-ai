<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateInvestorBroadcastService
{
    private const GROUP_NAME = 'Emlak AI - Yatırımcılar';
    private const CONTACT_PHONE = '0536 475 00 98';

    public function __construct(
        private readonly WhatsAppService $whatsAppService,
        private readonly SatilikMiPortfolioPublisherService $publisher,
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
            || ! ($handoff['seller_consented_to_presentation'] ?? false)
            || filled($notifications['investor_broadcast_sent_at'] ?? null)
        ) {
            return;
        }

        try {
            $publication = $this->publisher->publish($profile);

            if (! is_array($publication) || blank($publication['share_url'] ?? null)) {
                Log::info('REAL ESTATE INVESTOR BROADCAST WAITING FOR SATILIKMI', [
                    'profile_id' => $profile->id,
                ]);
                return;
            }

            $profile->refresh();
            $data = is_array($profile->data) ? $profile->data : [];
            $notifications = is_array($data['group_notifications'] ?? null)
                ? $data['group_notifications']
                : [];

            if (filled($notifications['investor_broadcast_sent_at'] ?? null)) {
                return;
            }

            $reference = trim((string) ($publication['reference_no'] ?? ''));
            if ($reference === '') {
                $reference = 'PF-'.str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT);
            }

            $message = $this->message(
                profile: $profile,
                data: $data,
                reference: $reference,
                shareUrl: trim((string) $publication['share_url']),
            );

            if (! $this->sendToNamedGroup($message)) {
                return;
            }

            $notifications['investor_broadcast_sent_at'] = now()->toIso8601String();
            $notifications['investor_broadcast_group'] = self::GROUP_NAME;
            $notifications['investor_broadcast_share_url'] = trim((string) $publication['share_url']);
            $data['group_notifications'] = $notifications;
            $profile->forceFill(['data' => $data])->saveQuietly();
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE INVESTOR BROADCAST FAILED', [
                'profile_id' => $profile->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function message(
        RealEstateProfile $profile,
        array $data,
        string $reference,
        string $shareUrl,
    ): string {
        $lines = [
            '🏠 YENİ YATIRIM FIRSATI',
            '',
            'Referans No: '.$reference,
            'Tür: '.$this->value($data, ['property_type']),
            'Konum: '.$this->location($data),
            'Alan: '.$this->singleArea($data),
        ];

        $parcel = $this->parcel($data);
        if ($parcel !== null) {
            $lines[] = 'Ada / Parsel: '.$parcel;
        }

        $highlights = $this->highlights($data);
        if ($highlights !== []) {
            $lines[] = '';
            $lines[] = '✅ Öne çıkan avantajlar:';
            foreach ($highlights as $highlight) {
                $lines[] = '• '.$highlight;
            }
        }

        $lines[] = '';
        $lines[] = $this->sellerPosture($data);
        $lines[] = '';
        $lines[] = '📸 Fotoğraflar ve portföy detayları:';
        $lines[] = $shareUrl;
        $lines[] = '';
        $lines[] = '📞 Teklif vermek ve detaylı bilgi almak için:';
        $lines[] = self::CONTACT_PHONE;

        return implode("\n", $lines);
    }

    private function sellerPosture(array $data): string
    {
        $urgency = strtolower(trim((string) ($data['urgency'] ?? '')));
        $motivation = is_array($data['seller_motivation_intelligence'] ?? null)
            ? strtolower(trim((string) ($data['seller_motivation_intelligence']['motivation_level'] ?? '')))
            : '';

        if ($urgency === 'high' || $motivation === 'high_explicit') {
            return 'Satıcı kısa sürede satışa açık. Acil nakit teklifleri iletilebilir.';
        }

        if ($urgency === 'medium' || $motivation === 'medium_explicit') {
            return 'Satıcı satışa açık. Ciddi yatırımcıların nakit teklifleri değerlendirilebilir.';
        }

        return 'Satıcı yatırımcı tekliflerini değerlendirmeye açık. Nakit teklifler iletilebilir.';
    }

    private function highlights(array $data): array
    {
        $map = [
            'road_frontage' => 'Yola cepheli',
            'electricity_available' => 'Elektrik mevcut',
            'water_available' => 'Su mevcut',
            'natural_gas_available' => 'Doğalgaz mevcut',
            'sewer_available' => 'Kanalizasyon mevcut',
            'internet_available' => 'İnternet altyapısı mevcut',
            'flat_land' => 'Düz arazi',
            'corner_parcel' => 'Köşe parsel',
            'sea_view' => 'Deniz manzaralı',
            'villa_suitable' => 'Villa yapımına uygun',
            'commercial_potential' => 'Ticari potansiyel',
            'investment_zone' => 'Yatırım bölgesinde',
            'near_main_road' => 'Ana yola yakın',
            'near_sea' => 'Denize yakın',
            'near_city_center' => 'Şehir merkezine yakın',
            'near_hospital' => 'Hastaneye yakın',
            'near_school' => 'Okula yakın',
            'near_public_transport' => 'Toplu taşımaya yakın',
            'near_market' => 'Market / çarşıya yakın',
        ];

        $items = [];
        foreach ($map as $key => $label) {
            if (($data[$key] ?? null) === true) {
                $items[] = $label;
            }
        }

        $notes = mb_strtolower(trim(implode(' ', array_filter([
            is_scalar($data['notes'] ?? null) ? (string) $data['notes'] : '',
            is_scalar($data['property_advantages'] ?? null) ? (string) $data['property_advantages'] : '',
            is_scalar($data['location_advantages'] ?? null) ? (string) $data['location_advantages'] : '',
        ]))));

        $keywords = [
            'yola cephe' => 'Yola cepheli',
            'elektrik' => 'Elektrik mevcut',
            'su var' => 'Su mevcut',
            'düz arazi' => 'Düz arazi',
            'köşe parsel' => 'Köşe parsel',
            'denize' => 'Denize yakın',
            'hastane' => 'Hastaneye yakın',
            'okul' => 'Okula yakın',
            'toplu taşıma' => 'Toplu taşımaya yakın',
        ];

        foreach ($keywords as $needle => $label) {
            if ($notes !== '' && str_contains($notes, $needle)) {
                $items[] = $label;
            }
        }

        return array_values(array_slice(array_unique($items), 0, 6));
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
            Log::info('REAL ESTATE INVESTOR BROADCAST GROUP NOT FOUND', [
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

    private function parcel(array $data): ?string
    {
        $ada = $data['block_no'] ?? $data['block'] ?? $data['ada'] ?? null;
        $parsel = $data['parcel_no'] ?? $data['parcel'] ?? $data['parsel'] ?? null;

        if (blank($ada) && blank($parsel)) {
            return null;
        }

        return 'Ada '.($ada ?: '—').' / Parsel '.($parsel ?: '—');
    }
}
