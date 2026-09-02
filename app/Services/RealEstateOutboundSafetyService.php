<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateOutboundSafetyEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RealEstateOutboundSafetyService
{
    private const SAFE_RESPONSE_VERSION = 'real-estate-outbound-safety-v1';

    private const CERTAINTY_PHRASES = [
        'kesin alici',
        'hazir alici',
        'alici hazir',
        'hazir yatirimci',
        'yatirimci hazir',
        'kesin satilir',
        'garanti satilir',
        'satis garantisi',
        'kesin teklif',
        'teklif kesin',
        'baglayici teklif',
        'kesin alir',
        'kesin alacak',
        'yatirimci kesin alir',
    ];

    private const OFFICIAL_VERIFICATION_PHRASES = [
        'tapu dogrulandi',
        'tapu dogrulanmis',
        'tapu kaydi temiz',
        'resmi olarak dogrulandi',
        'resmen dogrulandi',
        'imar onayli',
        'imar onaylidir',
        'takyidat temiz',
        'takyidat bulunmuyor',
        'borc yok',
        'borcu yok',
        'haciz yok',
        'ipotek yok',
        'serh yok',
        'resmi kayitlar temiz',
    ];

    private const CONFIDENTIAL_FLOOR_PHRASES = [
        'saticinin minimumu',
        'saticinin minimum fiyati',
        'saticinin tabani',
        'saticinin taban fiyati',
        'saticinin alt siniri',
        'saticinin en dusuk fiyati',
        'saticinin son verecegi fiyat',
        'en dusuk verecegi fiyat',
        'son verecegi fiyat',
    ];

    private const FLOOR_CONTEXT_TERMS = [
        'minimum',
        'taban',
        'alt sinir',
        'en dusuk',
        'son fiyat',
        'son verecegi',
        'son rakam',
    ];

    private const NEGATION_TERMS = [
        'degil',
        'degildir',
        'diyemem',
        'soyleyemem',
        'iddia edemem',
        'garanti veremem',
        'kesinlestirilmedi',
        'dogrulanmadi',
        'dogrulanmis degil',
        'dogrulayamayiz',
        'dogrulayamam',
        'teyit edilmedi',
        'teyit edemem',
    ];

    public function protect(
        ConversationControl $conversation,
        string $answer,
        string $inboundMessageId,
    ): array {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            throw new RuntimeException('Emlak AI outbound güvenlik kapsamı ihlali.');
        }

        $answer = trim($answer);

        if ($answer === '') {
            throw new RuntimeException('Emlak AI outbound güvenlik kontrolüne boş cevap verildi.');
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        $role = in_array($profile?->profile_type, ['seller', 'investor', 'buyer'], true)
            ? (string) $profile->profile_type
            : 'general';
        $normalized = $this->normalize($answer);
        $reasons = [];

        if ($this->containsUnsafePositivePhrase($normalized, self::CERTAINTY_PHRASES)) {
            $reasons[] = 'unsupported_transaction_certainty';
        }

        if ($this->containsUnsafePositivePhrase($normalized, self::OFFICIAL_VERIFICATION_PHRASES)) {
            $reasons[] = 'unsupported_official_verification';
        }

        if (in_array($role, ['investor', 'buyer'], true)) {
            if ($this->containsUnsafePositivePhrase($normalized, self::CONFIDENTIAL_FLOOR_PHRASES)) {
                $reasons[] = 'confidential_seller_floor';
            } elseif ($profile && $this->containsMatchedSellerFloor($profile, $normalized)) {
                $reasons[] = 'confidential_seller_floor';
            }
        }

        $reasons = array_values(array_unique($reasons));

        if ($reasons === []) {
            return [
                'answer' => $answer,
                'replaced' => false,
                'reasons' => [],
                'safe_response_version' => self::SAFE_RESPONSE_VERSION,
            ];
        }

        $safeAnswer = in_array('confidential_seller_floor', $reasons, true)
            ? 'Satıcıya ait özel pazarlık sınırlarını paylaşamam. Size yalnız paylaşılabilir fiyat/değerleme aralığı ve doğrulanabilir portföy bilgileri üzerinden yardımcı olabilirim.'
            : 'Bu aşamada kesin veya resmî olarak doğrulanmış bir sonuç paylaşamam. Elimizdeki bilgiler ön değerlendirme niteliğinde; doğrulanabilir fiyat, belge ve eşleşme bilgilerini netleştikçe şeffaf biçimde paylaşabilirim.';

        $event = $this->recordReplacement(
            conversation: $conversation,
            profile: $profile,
            inboundMessageId: $inboundMessageId,
            originalAnswer: $answer,
            role: $role,
            reasons: $reasons,
        );

        Log::warning('REAL ESTATE OUTBOUND ANSWER REPLACED BY SAFETY FIREWALL', [
            'user_id' => RealEstateIsolationService::USER_ID,
            'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
            'ai_bot_id' => RealEstateIsolationService::BOT_ID,
            'conversation_id' => $conversation->id,
            'profile_id' => $profile?->id,
            'event_id' => $event?->id,
            'recipient_role' => $role,
            'reasons' => $reasons,
            'response_hash_prefix' => substr(hash('sha256', $answer), 0, 12),
        ]);

        return [
            'answer' => $safeAnswer,
            'replaced' => true,
            'reasons' => $reasons,
            'safe_response_version' => self::SAFE_RESPONSE_VERSION,
        ];
    }

    private function containsUnsafePositivePhrase(string $normalized, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            $offset = 0;
            $phraseLength = mb_strlen($phrase, 'UTF-8');

            while (($position = mb_strpos($normalized, $phrase, $offset, 'UTF-8')) !== false) {
                $contextStart = max(0, $position - 48);
                $context = mb_substr(
                    $normalized,
                    $contextStart,
                    $phraseLength + 96,
                    'UTF-8'
                );

                if (! $this->hasNegation($context)) {
                    return true;
                }

                $offset = $position + max(1, $phraseLength);
            }
        }

        return false;
    }

    private function hasNegation(string $context): bool
    {
        foreach (self::NEGATION_TERMS as $term) {
            if (str_contains($context, $term)) {
                return true;
            }
        }

        return false;
    }

    private function containsMatchedSellerFloor(
        RealEstateProfile $recipientProfile,
        string $normalizedAnswer,
    ): bool {
        $data = is_array($recipientProfile->data) ? $recipientProfile->data : [];
        $matches = $data['opportunity_matches'] ?? [];

        if (! is_array($matches) || $matches === []) {
            return false;
        }

        $sellerIds = collect($matches)
            ->filter(fn (mixed $match): bool => is_array($match))
            ->filter(function (array $match): bool {
                $role = (string) ($match['candidate_role'] ?? '');

                return $role === 'seller' || isset($match['seller_profile_id']);
            })
            ->map(function (array $match): ?int {
                $id = $match['candidate_role'] ?? null;
                $id = $id === 'seller'
                    ? ($match['candidate_profile_id'] ?? null)
                    : ($match['seller_profile_id'] ?? null);

                return is_numeric($id) ? (int) $id : null;
            })
            ->filter(fn (?int $id): bool => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($sellerIds === []) {
            return false;
        }

        $sellerProfiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereIn('id', $sellerIds)
            ->get();

        foreach ($sellerProfiles as $sellerProfile) {
            $sellerData = is_array($sellerProfile->data) ? $sellerProfile->data : [];
            $minimum = $sellerData['minimum_price'] ?? null;

            if (! is_numeric($minimum) || (float) $minimum <= 0) {
                continue;
            }

            if ($this->priceMentionedNearFloorContext($normalizedAnswer, (float) $minimum)) {
                return true;
            }
        }

        return false;
    }

    private function priceMentionedNearFloorContext(string $text, float $price): bool
    {
        foreach ($this->pricePatterns($price) as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $byteOffset = (int) ($match[1] ?? 0);
                $start = max(0, $byteOffset - 120);
                $context = substr($text, $start, 260);

                foreach (self::FLOOR_CONTEXT_TERMS as $term) {
                    if (str_contains($context, $term)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function pricePatterns(float $price): array
    {
        $rounded = (int) round($price);
        $patterns = [];

        foreach (array_unique([
            (string) $rounded,
            number_format($rounded, 0, '', '.'),
            number_format($rounded, 0, '', ','),
            number_format($rounded, 0, '', ' '),
        ]) as $token) {
            $patterns[] = '/(?<!\d)'.preg_quote($token, '/').'(?!\d)/u';
        }

        if ($price >= 1_000_000) {
            $millions = $price / 1_000_000;
            $canonical = rtrim(rtrim(number_format($millions, 2, '.', ''), '0'), '.');
            $millionPattern = str_replace('\\.', '[.,]', preg_quote($canonical, '/'));
            $patterns[] = '/(?<!\d)'.$millionPattern.'\s*(?:milyon|mn)(?!\p{L})/iu';
        }

        return array_values(array_unique($patterns));
    }

    private function recordReplacement(
        ConversationControl $conversation,
        ?RealEstateProfile $profile,
        string $inboundMessageId,
        string $originalAnswer,
        string $role,
        array $reasons,
    ): ?RealEstateOutboundSafetyEvent {
        if (! Schema::hasTable('real_estate_outbound_safety_events')) {
            return null;
        }

        $messageHash = hash('sha256', $inboundMessageId);
        $responseHash = hash('sha256', $originalAnswer);
        $eventKey = hash('sha256', implode('|', [
            (string) RealEstateIsolationService::USER_ID,
            (string) RealEstateIsolationService::ORGANIZATION_ID,
            (string) RealEstateIsolationService::BOT_ID,
            (string) $conversation->id,
            $messageHash,
            $responseHash,
            implode(',', $reasons),
        ]));

        return RealEstateOutboundSafetyEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile?->id,
                'inbound_message_id_hash' => $messageHash,
                'response_hash' => $responseHash,
                'action' => 'replaced',
                'recipient_role' => $role,
                'reasons' => $reasons,
                'safe_response_version' => self::SAFE_RESPONSE_VERSION,
                'detected_at' => now(),
            ]
        );
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ı' => 'i', 'İ' => 'i',
            'ş' => 's', 'Ş' => 's',
            'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u',
            'ö' => 'o', 'Ö' => 'o',
            'ç' => 'c', 'Ç' => 'c',
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
