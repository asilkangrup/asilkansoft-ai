<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstateProfile;

class RealEstateTitleOwnershipIntelligenceService
{
    private const DATA_KEY = 'title_ownership';

    private const ALLOWED_RELATIONS = [
        'seller',
        'spouse',
        'relative',
        'company',
        'other_person',
        'unknown',
    ];

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $this->normalizedStored($data[self::DATA_KEY])
            : [];

        // An operator-confirmed relation is authoritative for CRM purposes.
        // Customer-message extraction must never silently overwrite it.
        if ((bool) ($existing['confirmed_by_operator'] ?? false)) {
            return $existing;
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $message = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $conversation->session_id)
            ->where('role', 'user')
            ->where('sender_type', 'customer')
            ->latest('id')
            ->first();

        if (! $message) {
            return $existing;
        }

        $rawMessage = (string) $message->message;
        $relation = $this->relationFromMessage($rawMessage);

        if ($relation === null) {
            return $existing;
        }

        $currentRelation = (string) ($existing['relation'] ?? 'unknown');
        $pendingRelation = (string) ($existing['pending_relation'] ?? '');
        $sameAsCurrent = $currentRelation !== 'unknown' && $currentRelation === $relation;
        $explicitCorrection = $this->hasCorrectionMarker($rawMessage);
        $pendingConfirmed = $pendingRelation !== '' && $pendingRelation === $relation;

        if ($sameAsCurrent) {
            $stored = [
                ...$existing,
                'relation' => $relation,
                'source' => 'explicit_customer_statement',
                'source_chat_message_id' => $message->id,
                'confirmation_required' => false,
                'pending_relation' => null,
                'legal_verification' => false,
                'updated_at' => now()->toIso8601String(),
            ];
        } elseif (
            $currentRelation !== 'unknown'
            && ! $explicitCorrection
            && ! $pendingConfirmed
        ) {
            // Ownership is a stable seller fact. A single contradictory turn
            // is held for confirmation instead of silently replacing it.
            $stored = [
                ...$existing,
                'confirmation_required' => true,
                'pending_relation' => $relation,
                'pending_source_chat_message_id' => $message->id,
                'legal_verification' => false,
                'updated_at' => now()->toIso8601String(),
            ];
        } else {
            $stored = [
                'relation' => $relation,
                'note' => null,
                'source' => 'explicit_customer_statement',
                'source_chat_message_id' => $message->id,
                'confirmed_by_operator' => false,
                'legal_verification' => false,
                'confirmation_required' => false,
                'pending_relation' => null,
                'pending_source_chat_message_id' => null,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        if ($this->comparable($existing) === $this->comparable($stored)) {
            return $existing ?: $this->normalizedStored($stored);
        }

        $data[self::DATA_KEY] = $stored;
        $profile->forceFill(['data' => $data])->saveQuietly();

        return $this->normalizedStored($stored);
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $stored = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        return $stored !== [] ? $this->normalizedStored($stored) : [];
    }

    private function relationFromMessage(string $message): ?string
    {
        $text = $this->normalizeText($message);

        if ($text === '') {
            return null;
        }

        $hasOwnershipContext = preg_match(
            '/\b(tapu|tapuya|tapunun|tapu\s+senedi|arsa|arsanin|ev|evin|mulk|mulkun|tasinmaz|tasinmazin)\b/u',
            $text
        ) === 1;
        $hasOwnershipPhrase = preg_match(
            '/\b(uzerime|uzerimde|uzerine|uzerinde|adina|adinda|sahibi|maliki)\b/u',
            $text
        ) === 1;

        if (! $hasOwnershipContext || ! $hasOwnershipPhrase) {
            return null;
        }

        // Questions such as "tapu kimin üzerine?" are not evidence about the
        // current owner and must never be turned into a CRM fact.
        if (preg_match('/\b(kimin|kimde|kim\s+adinda|kim\s+uzerine)\b/u', $text) === 1) {
            return null;
        }

        if (preg_match('/\b(benim|bende|kendi|kendim|uzerime|adima)\b/u', $text) === 1) {
            return 'seller';
        }

        if (preg_match('/\b(esim|esimin|karim|karimin|kocam|kocamin)\b/u', $text) === 1) {
            return 'spouse';
        }

        if (preg_match(
            '/\b(babam|babamin|annem|annemin|kardesim|kardesimin|abim|abimin|ablam|ablamin|oglum|oglumun|kizim|kizimin|amcam|amcamin|dayim|dayimin|halam|halamin|teyzem|teyzemin|dedem|dedemin|ninem|ninemin|akrabam|akrabamin|yakinim|yakinimin)\b/u',
            $text
        ) === 1) {
            return 'relative';
        }

        if (preg_match('/\b(sirket|sirketin|firmam|firmamin|firma|firmanin)\b/u', $text) === 1) {
            return 'company';
        }

        if (preg_match(
            '/\b(baska\s+birinin|baskasinin|arkadasim|arkadasimin|ortagim|ortagimin|ucuncu\s+bir\s+kisi)\b/u',
            $text
        ) === 1) {
            return 'other_person';
        }

        return null;
    }

    private function hasCorrectionMarker(string $message): bool
    {
        $text = $this->normalizeText($message);

        return preg_match(
            '/\b(hayir|yanlis|aslinda|pardon|duzeltiyorum|dogrusu|artik|simdi|devredildi|devrettik|guncellendi)\b/u',
            $text
        ) === 1;
    }

    private function normalizedStored(array $stored): array
    {
        $relation = strtolower(trim((string) ($stored['relation'] ?? 'unknown')));
        $pendingRelation = strtolower(trim((string) ($stored['pending_relation'] ?? '')));

        if (! in_array($relation, self::ALLOWED_RELATIONS, true)) {
            $relation = 'unknown';
        }
        if (! in_array($pendingRelation, self::ALLOWED_RELATIONS, true) || $pendingRelation === 'unknown') {
            $pendingRelation = '';
        }

        return [
            'relation' => $relation,
            'note' => filled($stored['note'] ?? null)
                ? trim((string) $stored['note'])
                : null,
            'source' => trim((string) ($stored['source'] ?? 'operator')) ?: 'operator',
            'source_chat_message_id' => is_numeric($stored['source_chat_message_id'] ?? null)
                ? (int) $stored['source_chat_message_id']
                : null,
            'confirmed_by_operator' => (bool) ($stored['confirmed_by_operator'] ?? false),
            'legal_verification' => (bool) ($stored['legal_verification'] ?? false),
            'confirmation_required' => (bool) ($stored['confirmation_required'] ?? false),
            'pending_relation' => $pendingRelation !== '' ? $pendingRelation : null,
            'pending_source_chat_message_id' => is_numeric($stored['pending_source_chat_message_id'] ?? null)
                ? (int) $stored['pending_source_chat_message_id']
                : null,
            'updated_at' => $stored['updated_at'] ?? null,
        ];
    }

    private function comparable(array $stored): array
    {
        $stored = $this->normalizedStored($stored);
        unset($stored['updated_at']);

        return $stored;
    }

    private function normalizeText(string $message): string
    {
        $normalized = mb_strtolower(strtr($message, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
    }

    private function supports(RealEstateProfile $profile): bool
    {
        if (
            $profile->profile_type !== 'seller'
            || (int) $profile->user_id !== RealEstateIsolationService::USER_ID
            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID
        ) {
            return false;
        }

        return app(RealEstateIsolationService::class)
            ->supportsConversation($profile->conversation()->first());
    }
}
