<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstatePrivateMedia;
use App\Models\RealEstateProfile;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SatilikMiPortfolioPublisherService
{
    public function publish(RealEstateProfile $profile): ?array
    {
        if (
            $profile->profile_type !== 'seller'
            || ! $profile->belongsToIsolatedProductionScope()
        ) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $handoff = is_array($data['seller_fast_cash_handoff'] ?? null)
            ? $data['seller_fast_cash_handoff']
            : [];
        $publication = is_array($data['satilikmi_publication'] ?? null)
            ? $data['satilikmi_publication']
            : [];

        if (blank($handoff['completed_at'] ?? null)) {
            return null;
        }

        if (filled($publication['share_url'] ?? null)) {
            return $publication;
        }

        if (! $this->configured()) {
            Log::info('SATILIKMI PORTFOLIO PUBLISHER NOT CONFIGURED', [
                'profile_id' => $profile->id,
            ]);

            return null;
        }

        try {
            $response = $this->client()->post(
                '/integrations/emlak-ai/portfolios',
                $this->payload($profile, $data),
            );

            if (! $response->successful()) {
                Log::warning('SATILIKMI PORTFOLIO CREATE FAILED', [
                    'profile_id' => $profile->id,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return null;
            }

            $result = $response->json();
            $publicId = trim((string) data_get($result, 'id', ''));
            $shareUrl = trim((string) data_get($result, 'shareUrl', ''));
            $referenceNo = trim((string) data_get($result, 'referenceNo', ''));

            if ($publicId === '' || $shareUrl === '') {
                Log::warning('SATILIKMI PORTFOLIO RESPONSE INVALID', [
                    'profile_id' => $profile->id,
                ]);

                return null;
            }

            $uploaded = $this->uploadPhotos($profile, $publicId);

            $publication = [
                'public_id' => $publicId,
                'reference_no' => $referenceNo !== '' ? $referenceNo : null,
                'share_url' => $shareUrl,
                'media_uploaded_count' => $uploaded,
                'published_at' => now()->toIso8601String(),
                'seller_identity_shared' => false,
                'seller_phone_shared' => false,
                'asking_price_shared' => false,
                'minimum_price_shared' => false,
            ];

            $data['satilikmi_publication'] = $publication;
            $profile->forceFill(['data' => $data])->saveQuietly();

            Log::info('SATILIKMI PORTFOLIO PUBLISHED', [
                'profile_id' => $profile->id,
                'public_id' => $publicId,
                'media_uploaded_count' => $uploaded,
            ]);

            return $publication;
        } catch (Throwable $exception) {
            Log::warning('SATILIKMI PORTFOLIO PUBLISH FAILED', [
                'profile_id' => $profile->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function payload(RealEstateProfile $profile, array $data): array
    {
        $motivation = is_array($data['seller_motivation_intelligence'] ?? null)
            ? $data['seller_motivation_intelligence']
            : [];
        $urgency = in_array($data['urgency'] ?? null, ['low', 'medium', 'high'], true)
            ? (string) $data['urgency']
            : null;
        $motivationLevel = trim((string) ($motivation['motivation_level'] ?? ''));

        return array_filter([
            'sourceProfileId' => (string) $profile->id,
            'propertyType' => $this->stringValue($data, ['property_type'], 'Gayrimenkul'),
            'province' => $this->stringValue($data, ['city'], 'Belirtilmedi'),
            'district' => $this->stringValue($data, ['district'], 'Belirtilmedi'),
            'neighborhood' => $this->nullableString($data, ['neighborhood']),
            'title' => $this->publicTitle($data),
            'grossAreaM2' => $this->positiveNumber($data, ['area_sqm', 'gross_area_sqm', 'sqm']),
            'titleDeedType' => $this->nullableString($data, ['title_deed_type', 'deed_type']),
            'blockNo' => $this->nullableString($data, ['block_no', 'block', 'ada']),
            'parcelNo' => $this->nullableString($data, ['parcel_no', 'parcel', 'parsel']),
            'zoningStatus' => $this->nullableString($data, ['zoning_status', 'zoning']),
            'pitch' => $this->publicPitch($urgency, $motivationLevel),
            'urgencyLabel' => $this->urgencyLabel($urgency, $motivationLevel),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function publicPitch(?string $urgency, string $motivationLevel): string
    {
        if ($urgency === 'high' || $motivationLevel === 'high_explicit') {
            return 'Satıcı kısa sürede satışa açık. Acil nakit teklifleri iletilebilir.';
        }

        if ($urgency === 'medium' || $motivationLevel === 'medium_explicit') {
            return 'Satıcı satışa açık. Ciddi yatırımcıların nakit teklifleri değerlendirilebilir.';
        }

        if ($urgency === 'low' || $motivationLevel === 'low_explicit') {
            return 'Güncel yatırım portföyüdür. Uygun yatırımcı teklifleri satıcıya iletilebilir.';
        }

        return 'Satıcı yatırımcı tekliflerini değerlendirmeye açık. Nakit teklifler iletilebilir.';
    }

    private function urgencyLabel(?string $urgency, string $motivationLevel): ?string
    {
        return match (true) {
            $urgency === 'high', $motivationLevel === 'high_explicit' => 'Acil satış',
            $urgency === 'medium', $motivationLevel === 'medium_explicit' => 'Teklife açık',
            default => null,
        };
    }

    private function publicTitle(array $data): string
    {
        $type = $this->stringValue($data, ['property_type'], 'Gayrimenkul');
        $district = $this->nullableString($data, ['district']);
        $city = $this->nullableString($data, ['city']);

        $location = collect([$district, $city])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->implode(' / ');

        return $location !== ''
            ? $type.' · '.$location
            : $type.' yatırım portföyü';
    }

    private function uploadPhotos(RealEstateProfile $profile, string $publicId): int
    {
        $conversation = $profile->conversation()->first();

        if (! $conversation) {
            return 0;
        }

        $messageIds = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $conversation->session_id)
            ->where('sender_type', 'customer')
            ->where('message_type', 'image')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($messageIds === []) {
            return 0;
        }

        $mediaRows = RealEstatePrivateMedia::query()
            ->isolatedProduction()
            ->whereIn('chat_message_id', $messageIds)
            ->where('mime_type', 'like', 'image/%')
            ->whereNotNull('content_base64')
            ->orderBy('chat_message_id')
            ->limit(12)
            ->get();

        $uploaded = 0;

        foreach ($mediaRows as $index => $media) {
            $bytes = base64_decode((string) $media->content_base64, true);

            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $mime = strtolower(trim((string) $media->mime_type));
            $extension = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            try {
                $response = $this->client()
                    ->attach('file', $bytes, 'portfolio-'.($index + 1).'.'.$extension, [
                        'Content-Type' => $mime !== '' ? $mime : 'image/jpeg',
                    ])
                    ->post('/integrations/emlak-ai/portfolios/'.$publicId.'/media');

                if ($response->successful()) {
                    $uploaded++;
                } else {
                    Log::warning('SATILIKMI PORTFOLIO MEDIA UPLOAD FAILED', [
                        'profile_id' => $profile->id,
                        'public_id' => $publicId,
                        'media_id' => $media->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('SATILIKMI PORTFOLIO MEDIA UPLOAD EXCEPTION', [
                    'profile_id' => $profile->id,
                    'public_id' => $publicId,
                    'media_id' => $media->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $uploaded;
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.satilikmi.api_url'), '/');
        $key = (string) config('services.satilikmi.integration_key');

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'x-integration-key' => $key,
                'Accept' => 'application/json',
            ])
            ->timeout(45)
            ->connectTimeout(8);
    }

    private function configured(): bool
    {
        return trim((string) config('services.satilikmi.api_url')) !== ''
            && trim((string) config('services.satilikmi.integration_key')) !== '';
    }

    private function stringValue(array $data, array $keys, string $fallback): string
    {
        return $this->nullableString($data, $keys) ?? $fallback;
    }

    private function nullableString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 160);
            }
        }

        return null;
    }

    private function positiveNumber(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }
}
