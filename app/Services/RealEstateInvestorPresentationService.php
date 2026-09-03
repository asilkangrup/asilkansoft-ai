<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstateProfile;
use Illuminate\Support\Collection;

class RealEstateInvestorPresentationService
{
    public function build(RealEstateProfile $profile): array
    {
        if ($profile->profile_type !== 'seller' || ! $profile->belongsToIsolatedProductionScope()) {
            return [];
        }

        $profile->loadMissing('conversation');
        $data = is_array($profile->data) ? $profile->data : [];
        $packet = is_array($data['seller_offer_packet_intelligence'] ?? null)
            ? $data['seller_offer_packet_intelligence'] : [];

        $city = $this->first($data, ['city']);
        $district = $this->first($data, ['district']);
        $neighborhood = $this->first($data, ['neighborhood', 'location', 'property.location']);
        $type = $this->first($data, ['property_type', 'property.type']) ?: 'Gayrimenkul';
        $location = collect([$city, $district, $neighborhood])->filter()->unique()->implode(' / ');

        return [
            'reference' => 'EML-'.$profile->id,
            'title' => trim(($location ?: 'Türkiye').' '.$type),
            'status' => (string) ($packet['status'] ?? 'draft'),
            'facts' => [
                'Taşınmaz türü' => $type,
                'İl' => $city,
                'İlçe' => $district,
                'Mahalle / Bölge' => $neighborhood,
                'Metrekare' => $this->first($data, ['area_sqm', 'square_meters', 'property.area_sqm']),
                'Ada' => $this->first($data, ['block_no', 'ada_no']),
                'Parsel' => $this->first($data, ['parcel_no', 'parsel_no']),
                'Tapu niteliği' => $this->first($data, ['title_deed_type']),
                'İmar durumu' => $this->first($data, ['zoning_status']),
                'Hisse durumu' => $this->first($data, ['owner_share', 'share_status']),
            ],
            'photos' => $this->photos($profile, $data),
            'offer_note' => 'Bu dosya için kriterleri uyan gerçek yatırımcılardan teklifler toplanmaktadır.',
            'buyer_fee_note' => 'Alıcı hizmet bedeli, aksi yazılı kararlaştırılmadıkça satış bedelinin %2’sidir.',
            'disclaimer' => 'Bu sunum bilgilendirme amaçlıdır; kesin fiyat, getiri veya satış garantisi içermez. Tapu, imar ve diğer resmi bilgiler işlem öncesinde yetkili kurumlardan doğrulanmalıdır.',
            'guardrails' => [
                'seller_identity_included' => false,
                'seller_phone_included' => false,
                'seller_notes_included' => false,
                'seller_confidential_floor_included' => false,
                'automatic_outbound_allowed' => false,
            ],
        ];
    }

    private function photos(RealEstateProfile $profile, array $data): Collection
    {
        $findings = collect(is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [])
            ->filter(fn ($item): bool => is_array($item)
                && ($item['media_category'] ?? null) === 'property_photo'
                && filled($item['message_id'] ?? null))
            ->keyBy(fn (array $item): string => trim((string) $item['message_id']));

        $whatsapp = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $profile->conversation?->session_id)
            ->where('sender_type', 'customer')
            ->where('message_type', 'image')
            ->whereNotNull('media_url')
            ->latest('id')
            ->get()
            ->filter(fn (ChatMessage $message): bool => $findings->has(trim((string) $message->whatsapp_message_id)))
            ->map(function (ChatMessage $message) use ($findings): array {
                $stored = trim((string) $message->media_url);
                $url = str_starts_with($stored, 'private:real-estate-inbound/'.$message->id.'/')
                    ? route('real-estate.private-inbound-media', ['message' => $message->id], false)
                    : $stored;

                return [
                    'url' => $url,
                    'summary' => trim((string) data_get(
                        $findings->get(trim((string) $message->whatsapp_message_id), []),
                        'summary',
                        'Taşınmaz fotoğrafı'
                    )),
                ];
            })
            ->filter(fn (array $item): bool => str_starts_with($item['url'], '/admin/emlak-whatsapp-medya/')
                || preg_match('/^https:\/\//i', $item['url']) === 1)
            ->take(8);

        $manual = collect(is_array($data['manual_media'] ?? null) ? $data['manual_media'] : [])
            ->filter(fn ($item): bool => is_array($item)
                && ($item['category'] ?? null) === 'property_photo'
                && filled($item['id'] ?? null))
            ->map(fn (array $item): array => [
                'url' => route('real-estate.private-media', [
                    'profile' => $profile->id,
                    'media' => $item['id'],
                ], false),
                'summary' => trim((string) ($item['label'] ?? 'Taşınmaz fotoğrafı')),
            ])
            ->take(max(0, 8 - $whatsapp->count()));

        return $whatsapp->merge($manual)->values();
    }

    private function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
