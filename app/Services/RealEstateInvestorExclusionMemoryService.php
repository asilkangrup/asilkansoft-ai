<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstateProfile;
use Illuminate\Support\Collection;

class RealEstateInvestorExclusionMemoryService
{
    private const DATA_KEY = 'investor_exclusion_intelligence';

    private const PROPERTY_TYPES = [
        'arsa', 'tarla', 'daire', 'villa', 'dükkan', 'dukkan', 'ofis',
        'depo', 'fabrika', 'işyeri', 'isyeri', 'konut', 'ticari',
    ];

    private const CITIES = [
        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara',
        'Antalya', 'Artvin', 'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl',
        'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum',
        'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum',
        'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay',
        'Isparta', 'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu',
        'Kayseri', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya',
        'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş',
        'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt',
        'Sinop', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa',
        'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman',
        'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova',
        'Karabük', 'Kilis', 'Osmaniye', 'Düzce',
    ];

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $messages = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $conversation->session_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('message')
            ->latest('id')
            ->limit(120)
            ->get(['id', 'message'])
            ->reverse()
            ->values();

        $data = is_array($profile->data) ? $profile->data : [];
        $summary = $this->build($data, $messages);
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) === $summary) {
            return $existing;
        }

        $stored = [
            ...$summary,
            'updated_at' => now()->toIso8601String(),
        ];
        $data[self::DATA_KEY] = $stored;
        $profile->data = $data;
        $profile->saveQuietly();

        return $stored;
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        return $existing !== [] ? $existing : $this->sync($profile);
    }

    public function blocks(
        RealEstateProfile $investor,
        RealEstateProfile $seller,
    ): bool {
        return $this->blockingReasons($investor, $seller) !== [];
    }

    public function blockingReasons(
        RealEstateProfile $investor,
        RealEstateProfile $seller,
    ): array {
        if (! $this->supports($investor) || ! $seller->belongsToIsolatedProductionScope()) {
            return ['scope_invalid'];
        }

        $summary = $this->sync($investor);
        $sellerData = is_array($seller->data) ? $seller->data : [];
        $sellerTypes = $this->normalizedValues($sellerData['property_type'] ?? null);
        $sellerCities = $this->normalizedValues($sellerData['city'] ?? null);
        $sellerDistricts = $this->normalizedValues($sellerData['district'] ?? null);
        $reasons = [];

        if ($this->overlaps($sellerTypes, $summary['excluded_property_types'] ?? [])) {
            $reasons[] = 'explicit_property_type_exclusion';
        }

        if ($this->overlaps($sellerCities, $summary['excluded_cities'] ?? [])) {
            $reasons[] = 'explicit_city_exclusion';
        }

        if ($this->overlaps($sellerDistricts, $summary['excluded_districts'] ?? [])) {
            $reasons[] = 'explicit_district_exclusion';
        }

        return $reasons;
    }

    private function build(array $profileData, Collection $messages): array
    {
        $propertyCandidates = $this->canonicalCandidates(self::PROPERTY_TYPES);
        $cityCandidates = $this->canonicalCandidates(self::CITIES);
        $districtCandidates = $this->canonicalCandidates(
            $this->normalizedValues($profileData['district'] ?? null)
        );

        $propertyState = [];
        $cityState = [];
        $districtState = [];
        $sourceIds = [];

        foreach ($messages as $message) {
            $text = $this->normalizeText((string) $message->message);

            if ($text === '') {
                continue;
            }

            $changed = false;
            $changed = $this->applyMessage($text, $propertyCandidates, $propertyState) || $changed;
            $changed = $this->applyMessage($text, $cityCandidates, $cityState) || $changed;
            $changed = $this->applyMessage($text, $districtCandidates, $districtState) || $changed;

            if ($changed) {
                $sourceIds[] = (int) $message->id;
            }
        }

        $excludedPropertyTypes = $this->activeValues($propertyState);
        $excludedCities = $this->activeValues($cityState);
        $excludedDistricts = $this->activeValues($districtState);

        return [
            'excluded_property_types' => $excludedPropertyTypes,
            'excluded_cities' => $excludedCities,
            'excluded_districts' => $excludedDistricts,
            'explicit_no_go_count' => count($excludedPropertyTypes)
                + count($excludedCities)
                + count($excludedDistricts),
            'source_message_count' => count(array_unique($sourceIds)),
            'latest_source_message_id' => $sourceIds !== [] ? max($sourceIds) : null,
            'explicit_only' => true,
            'guardrails' => [
                'unknown_preferences_are_not_exclusions' => true,
                'assistant_messages_are_not_evidence' => true,
                're_inclusion_overrides_older_exclusion' => true,
                'no_binding_offer_inferred' => true,
                'follow_up_scheduling_allowed' => false,
            ],
        ];
    }

    private function applyMessage(
        string $message,
        array $candidates,
        array &$state,
    ): bool {
        $changed = false;

        foreach ($candidates as $normalized => $display) {
            if (! $this->mentions($message, $normalized)) {
                continue;
            }

            $negative = $this->hasCueNear(
                message: $message,
                criterion: $normalized,
                cues: [
                    'istemiyorum', 'istemem', 'olmasin', 'haric', 'bakmiyorum',
                    'dusunmuyorum', 'kabul etmiyorum', 'uygun degil',
                ],
            );
            $positive = $this->hasCueNear(
                message: $message,
                criterion: $normalized,
                cues: [
                    'olur', 'olabilir', 'bakabilirim', 'bakarim', 'kabul ederim',
                    'uygun', 'dahil', 'ekleyelim',
                ],
            );

            if ($negative === $positive) {
                continue;
            }

            $next = $negative;
            $previous = $state[$normalized]['excluded'] ?? null;
            $state[$normalized] = [
                'display' => $display,
                'excluded' => $next,
            ];
            $changed = $changed || $previous !== $next;
        }

        return $changed;
    }

    private function hasCueNear(
        string $message,
        string $criterion,
        array $cues,
    ): bool {
        $criterionPattern = preg_quote($criterion, '/').'[\\pL]{0,5}';
        $cuePattern = implode('|', array_map(
            fn (string $cue): string => preg_quote($cue, '/'),
            $cues
        ));

        return preg_match(
            '/(?:'.$criterionPattern.'.{0,28}(?:'.$cuePattern.')|(?:'.$cuePattern.').{0,28}'.$criterionPattern.')/u',
            $message
        ) === 1;
    }

    private function mentions(string $message, string $criterion): bool
    {
        return preg_match(
            '/(?<![\\pL\\pN])'.preg_quote($criterion, '/').'[\\pL]{0,5}(?![\\pL\\pN])/u',
            $message
        ) === 1;
    }

    private function activeValues(array $state): array
    {
        return collect($state)
            ->filter(fn (array $item): bool => (bool) ($item['excluded'] ?? false))
            ->map(fn (array $item): string => (string) ($item['display'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function canonicalCandidates(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $display = trim((string) $value);
            $normalized = $this->normalizeText($display);

            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }

            $result[$normalized] = $display;
        }

        return $result;
    }

    private function normalizedValues(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item): array => $this->normalizedValues($item))
                ->unique()
                ->values()
                ->all();
        }

        if (! is_scalar($value)) {
            return [];
        }

        $text = trim((string) $value);

        if ($text === '') {
            return [];
        }

        return collect(preg_split('/\\s*(?:,|;|\\/|\\||\\bveya\\b|\\bve\\b)\\s*/iu', $text) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function overlaps(array $sellerValues, mixed $excludedValues): bool
    {
        if (! is_array($excludedValues) || $sellerValues === []) {
            return false;
        }

        $seller = collect($sellerValues)
            ->map(fn ($value): string => $this->normalizeText((string) $value))
            ->filter()
            ->unique();
        $excluded = collect($excludedValues)
            ->map(fn ($value): string => $this->normalizeText((string) $value))
            ->filter()
            ->unique();

        return $seller->intersect($excluded)->isNotEmpty();
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);
        $text = preg_replace('/[^\\pL\\pN\\s]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\\s+/u', ' ', $text) ?? '');
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return in_array($profile->profile_type, ['investor', 'buyer'], true)
            && $profile->belongsToIsolatedProductionScope();
    }
}
