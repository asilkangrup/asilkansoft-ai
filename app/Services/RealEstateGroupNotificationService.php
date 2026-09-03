<?php

namespace App\Services;

use App\Models\RealEstateMatchEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateGroupNotificationService
{
    private const INVESTOR_GROUP = 'Emlak AI - Yeni Yatırımcılar';
    private const PORTFOLIO_GROUP = 'Emlak AI - Yeni Portföyler';
    private const MATCH_GROUP = 'Emlak AI - Sıcak Eşleşmeler';

    public function __construct(
        private readonly WhatsAppService $whatsAppService,
    ) {
    }

    public function sync(RealEstateProfile $profile): void
    {
        if (! $profile->belongsToIsolatedProductionScope()) {
            return;
        }

        try {
            $this->notifyInvestorIfReady($profile);
            $profile->refresh();
            $this->notifyPortfolioIfReady($profile);
            $profile->refresh();
            $this->notifyStrongMatches($profile);
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE GROUP NOTIFICATION FAILED', [
                'profile_id' => $profile->id,
                'profile_type' => $profile->profile_type,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyInvestorIfReady(RealEstateProfile $profile): void
    {
        if ($profile->profile_type !== 'investor') {
            return;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $onboarding = is_array($data['investor_onboarding_intelligence'] ?? null)
            ? $data['investor_onboarding_intelligence']
            : [];
        $notifications = is_array($data['group_notifications'] ?? null)
            ? $data['group_notifications']
            : [];

        if (blank($onboarding['completed_at'] ?? null)) {
            return;
        }

        $conversation = $profile->conversation()->first();

        if (
            blank($notifications['investor_verification_notice_sent_at'] ?? null)
            && filled($conversation?->whatsapp_number)
        ) {
            $this->whatsAppService->sendText(
                instanceName: RealEstateIsolationService::INSTANCE,
                number: (string) $conversation->whatsapp_number,
                text: 'Bilgileriniz alınmıştır. Ekip arkadaşlarımız kaydınızı doğrulamak ve detaylı bilgi vermek için kısa süre içinde sizi arayacaktır.',
            );

            $notifications['investor_verification_notice_sent_at'] = now()->toIso8601String();
            $data['group_notifications'] = $notifications;
            $profile->forceFill(['data' => $data])->saveQuietly();
        }

        if (filled($notifications['investor_sent_at'] ?? null)) {
            return;
        }

        $message = implode("\n", array_filter([
            '🟢 YENİ YATIRIMCI',
            '',
            'Ad Soyad: '.($conversation?->customer_name ?: 'Belirtilmedi'),
            'Telefon: '.($conversation?->whatsapp_number ?: 'Belirtilmedi'),
            'Gayrimenkul Türü: '.$this->value($data, ['property_type']),
            'Bölge: '.$this->location($data),
            'Yatırım Hedefi: '.$this->value($data, ['investment_goal']),
            'Bütçe: '.$this->budget($data),
            'Finansman: '.$this->value($data, ['financing']),
            'm² Tercihi: '.$this->area($data),
            'Risk Tercihi: '.$this->value($data, ['risk_preference']),
            'Hisseli Tapu: '.$this->boolValue($data['accepts_shared_title'] ?? null),
            'Hedef İskonto: '.$this->discount($data),
            'İşlem Süresi: '.$this->value($data, ['timeline']),
            '',
            'Durum: Telefonla doğrulama bekliyor.',
            'Sıradaki adım: Ekip arkadaşı yatırımcıyı arayıp doğrulasın ve sisteme dahil etsin.',
        ]));

        if (! $this->sendToNamedGroup(self::INVESTOR_GROUP, $message)) {
            return;
        }

        $notifications['investor_sent_at'] = now()->toIso8601String();
        $data['group_notifications'] = $notifications;
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function notifyPortfolioIfReady(RealEstateProfile $profile): void
    {
        if ($profile->profile_type !== 'seller') {
            return;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $handoff = is_array($data['seller_fast_cash_handoff'] ?? null)
            ? $data['seller_fast_cash_handoff']
            : [];
        $notifications = is_array($data['group_notifications'] ?? null)
            ? $data['group_notifications']
            : [];

        if (blank($handoff['completed_at'] ?? null) || filled($notifications['portfolio_sent_at'] ?? null)) {
            return;
        }

        $conversation = $profile->conversation()->first();
        $message = implode("\n", array_filter([
            '🏠 YENİ PORTFÖY',
            '',
            'Satıcı: '.($conversation?->customer_name ?: 'Belirtilmedi'),
            'Telefon: '.($conversation?->whatsapp_number ?: 'Belirtilmedi'),
            'Taşınmaz Türü: '.$this->value($data, ['property_type']),
            'Konum: '.$this->location($data),
            'Alan: '.$this->singleArea($data),
            'İstenen Fiyat: '.$this->price($data['asking_price'] ?? null),
            'Hızlı Nakit Son Fiyat: '.$this->price($data['minimum_price'] ?? null),
            'Ada/Parsel: '.$this->parcel($data),
            'Satış Aciliyeti: '.$this->value($data, ['urgency', 'seller_urgency', 'timeline']),
            '',
            'Durum: Yatırımcı değerlendirmesine sunulmaya hazır.',
            'Sıradaki adım: Uygun yatırımcılarla eşleştir ve teklif topla.',
        ]));

        if (! $this->sendToNamedGroup(self::PORTFOLIO_GROUP, $message)) {
            return;
        }

        $notifications['portfolio_sent_at'] = now()->toIso8601String();
        $data['group_notifications'] = $notifications;
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function notifyStrongMatches(RealEstateProfile $profile): void
    {
        $events = RealEstateMatchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('status', 'active')
            ->where('grade', 'strong')
            ->where(function ($query) use ($profile): void {
                $query->where('seller_profile_id', $profile->id)
                    ->orWhere('investor_profile_id', $profile->id);
            })
            ->orderByDesc('id')
            ->get()
            ->unique('pair_key');

        foreach ($events as $event) {
            $cacheKey = 'real-estate-match-group-notified:'.$event->event_key;

            if (! Cache::add($cacheKey, true, now()->addDays(30))) {
                continue;
            }

            $seller = RealEstateProfile::query()->isolatedProduction()->find($event->seller_profile_id);
            $investor = RealEstateProfile::query()->isolatedProduction()->find($event->investor_profile_id);

            if (! $seller || ! $investor) {
                continue;
            }

            $sellerData = is_array($seller->data) ? $seller->data : [];
            $investorData = is_array($investor->data) ? $investor->data : [];
            $sellerConversation = $seller->conversation()->first();
            $investorConversation = $investor->conversation()->first();

            $message = implode("\n", [
                '🔥 SICAK EŞLEŞME',
                '',
                'Eşleşme Skoru: '.((int) $event->match_score).'/100',
                'Portföy: '.$this->value($sellerData, ['property_type']).' - '.$this->location($sellerData),
                'Satıcı: '.($sellerConversation?->customer_name ?: 'Belirtilmedi').' / '.($sellerConversation?->whatsapp_number ?: 'Belirtilmedi'),
                'İstenen Fiyat: '.$this->price($sellerData['asking_price'] ?? null),
                'Yatırımcı: '.($investorConversation?->customer_name ?: 'Belirtilmedi').' / '.($investorConversation?->whatsapp_number ?: 'Belirtilmedi'),
                'Yatırımcı Bütçesi: '.$this->budget($investorData),
                'Tahmini İşlem Fiyatı: '.$this->price($event->estimated_transaction_price),
                '',
                'Sıradaki adım: Operatör eşleşmeyi kontrol edip taraflarla iletişime geçsin.',
            ]);

            if (! $this->sendToNamedGroup(self::MATCH_GROUP, $message)) {
                Cache::forget($cacheKey);
            }
        }
    }

    private function sendToNamedGroup(string $groupName, string $message): bool
    {
        $groups = Cache::remember(
            'real-estate-whatsapp-groups:'.RealEstateIsolationService::INSTANCE,
            now()->addMinutes(10),
            fn (): array => $this->whatsAppService->fetchGroups(RealEstateIsolationService::INSTANCE),
        );

        $group = collect($groups)->first(
            fn (array $group): bool => trim((string) ($group['name'] ?? '')) === $groupName
        );

        $jid = is_array($group) ? trim((string) ($group['id'] ?? '')) : '';

        if ($jid === '' || ! str_ends_with($jid, '@g.us')) {
            Log::warning('REAL ESTATE TARGET WHATSAPP GROUP NOT FOUND', [
                'group_name' => $groupName,
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
            $data['location_flexibility'] ?? null,
        ], fn ($value): bool => is_scalar($value) && trim((string) $value) !== '');

        return $parts === [] ? 'Belirtilmedi' : implode(' / ', array_map('strval', $parts));
    }

    private function budget(array $data): string
    {
        $min = $this->price($data['budget_min'] ?? null);
        $max = $this->price($data['budget_max'] ?? null);

        if ($min === 'Belirtilmedi' && $max === 'Belirtilmedi') {
            return 'Belirtilmedi';
        }

        return $min.' - '.$max;
    }

    private function area(array $data): string
    {
        $min = $data['area_min_sqm'] ?? null;
        $max = $data['area_max_sqm'] ?? null;

        if (! is_numeric($min) && ! is_numeric($max)) {
            return 'Esnek / Belirtilmedi';
        }

        return (is_numeric($min) ? number_format((float) $min, 0, ',', '.').' m²' : '—')
            .' - '
            .(is_numeric($max) ? number_format((float) $max, 0, ',', '.').' m²' : '—');
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

    private function boolValue(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'Evet' : 'Hayır') : 'Belirtilmedi';
    }

    private function discount(array $data): string
    {
        $value = $data['target_discount_percent'] ?? null;

        return is_numeric($value) ? '%'.rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',') : 'Belirtilmedi';
    }
}
