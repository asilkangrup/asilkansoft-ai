<?php

namespace App\Filament\Pages;

use App\Models\CrmActivity;
use App\Models\RealEstateProfile;
use App\Services\CrmActivityService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakIsMerkezi extends Page
{
    protected string $view = 'filament.pages.emlak-is-merkezi';
    protected static ?string $title = 'Emlak İş Merkezi';
    protected static ?string $navigationLabel = 'Emlak İş Merkezi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static ?int $navigationSort = 31;

    public array $callNotes = [];
    public array $callResults = [];
    public array $offerAmounts = [];
    public array $reminderDates = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()
                ->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)
                ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private function canWrite(): bool
    {
        $user = auth()->user();

        return static::canAccess()
            && $user
            && $user->canManageOrganization(RealEstateIsolationService::ORGANIZATION_ID);
    }

    public function getTasksProperty(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->latest('last_extracted_at')
            ->get();

        $investors = $profiles
            ->filter(fn (RealEstateProfile $profile): bool => in_array($profile->profile_type, ['investor', 'buyer'], true))
            ->keyBy('id');
        $history = $this->latestOperatorHistory();

        return $profiles
            ->where('profile_type', 'seller')
            ->map(function (RealEstateProfile $seller) use ($investors, $history): ?array {
                $data = is_array($seller->data) ? $seller->data : [];
                $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
                    ? $data['investor_offer_handoff_intelligence']
                    : [];
                $property = $this->propertySummary($data);

                if (! (bool) ($handoff['ready_for_operator_handoff'] ?? false)) {
                    return $this->sellerCompletionTask($seller, $property, $handoff);
                }

                $candidateRefs = collect($handoff['candidate_refs'] ?? [])
                    ->filter(fn (mixed $candidate): bool => is_array($candidate))
                    ->sortByDesc(fn (array $candidate): int => (int) ($candidate['match_score'] ?? 0));

                foreach ($candidateRefs as $candidate) {
                    $investor = $investors->get((int) ($candidate['investor_profile_id'] ?? 0));

                    if (! $investor || ! $investor->conversation) {
                        continue;
                    }

                    $task = $this->taskForPair($seller, $investor, $candidate, $property, $history);

                    if ($task !== null) {
                        return $task;
                    }
                }

                return [
                    'key' => 'sourcing-'.$seller->id,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => null,
                    'contact_profile_id' => $seller->id,
                    'target_name' => 'Yeni yatırımcı adayı bul',
                    'phone' => null,
                    'property' => $property,
                    'kind' => 'sourcing',
                    'priority' => 20,
                    'match_score' => null,
                    'offer_amount' => null,
                    'reason' => 'Dosya yatırımcı sunumuna hazır fakat mevcut güçlü adaylardan aktif bir teklif akışı kalmadı.',
                    'action' => 'Bu portföy için yeni yatırımcı kriteri/eşleşmesi oluştur; hazır alıcı varmış gibi satıcıya bilgi verme.',
                    'script' => 'Yeni yatırımcı kaynağı eklenene kadar satıcıya kesin alıcı veya teklif iddiasında bulunma.',
                ];
            })
            ->filter()
            ->map(fn (array $task): array => $this->attachReminder($task, $history))
            ->sortByDesc(fn (array $task): int => $this->taskRank($task))
            ->values();
    }

    public function outcomesFor(string $kind): array
    {
        return match ($kind) {
            'investor' => ['Ulaşılmadı', 'İlgileniyor', 'Teklif verdi', 'Tekrar ara', 'Uygun değil'],
            'seller_offer' => ['Ulaşılmadı', 'Kabul etti', 'Karşı teklif', 'Reddetti', 'Tekrar ara'],
            'investor_counter' => ['Ulaşılmadı', 'Kabul etti', 'Yeni teklif', 'Reddetti', 'Tekrar ara'],
            'seller_final' => ['Ulaşılmadı', 'Kabul etti', 'Reddetti', 'Tekrar ara'],
            'seller' => ['Ulaşılmadı', 'Bilgi tamamlandı', 'Belge/fotoğraf bekleniyor', 'Tekrar ara'],
            default => ['Not alındı'],
        };
    }

    public function saveCall(string $key): void
    {
        abort_unless($this->canWrite(), 403);

        $task = $this->tasks->firstWhere('key', $key);

        if (! is_array($task)) {
            Notification::make()->title('Görev artık güncel değil')->warning()->send();
            return;
        }

        $result = trim((string) ($this->callResults[$key] ?? ''));
        $note = trim((string) ($this->callNotes[$key] ?? ''));

        if ($result === '' || ! in_array($result, $this->outcomesFor((string) $task['kind']), true)) {
            Notification::make()->title('Görüşme sonucunu seçin')->warning()->send();
            return;
        }

        $amount = $this->parseMoney($this->offerAmounts[$key] ?? null);

        if (in_array($result, ['Teklif verdi', 'Karşı teklif', 'Yeni teklif'], true) && $amount === null) {
            Notification::make()->title('Teklif tutarını girin')->warning()->send();
            return;
        }

        $reminderAt = $this->parseReminder($this->reminderDates[$key] ?? null);

        if (in_array($result, ['Ulaşılmadı', 'Tekrar ara'], true) && $reminderAt === null) {
            Notification::make()->title('Tekrar arama tarihini seçin')->warning()->send();
            return;
        }

        $targetProfile = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->whereKey((int) $task['contact_profile_id'])
            ->first();
        $seller = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey((int) $task['seller_profile_id'])
            ->first();
        $conversation = $targetProfile?->conversation;

        if (! $targetProfile || ! $seller || ! $conversation) {
            Notification::make()->title('İzole CRM kaydı bulunamadı')->danger()->send();
            return;
        }

        $entry = now()->format('d.m.Y H:i').' — ['.$task['property'].'] '.$result;
        $entry .= $amount !== null ? ' — '.$this->moneyLabel($amount) : '';
        $entry .= $note !== '' ? ': '.$note : '';

        $conversation->forceFill([
            'notes' => trim(($conversation->notes ? $conversation->notes."\n\n" : '').$entry),
            'last_contact_at' => now(),
            'next_follow_up_at' => null,
        ])->save();

        app(CrmActivityService::class)->log(
            conversation: $conversation,
            type: 'real_estate_operator_call',
            title: $this->activityTitle((string) $task['kind']),
            description: $entry,
            newValue: $result,
            performedBy: auth()->user(),
            meta: [
                'scope' => 'isolated_real_estate',
                'seller_profile_id' => (int) $seller->id,
                'investor_profile_id' => is_numeric($task['investor_profile_id'] ?? null)
                    ? (int) $task['investor_profile_id']
                    : null,
                'contact_profile_id' => (int) $targetProfile->id,
                'task_kind' => (string) $task['kind'],
                'outcome' => $result,
                'offer_amount' => $amount,
                'match_score' => is_numeric($task['match_score'] ?? null) ? (int) $task['match_score'] : null,
                'property_label' => (string) $task['property'],
                'operator_reminder_at' => $reminderAt?->toIso8601String(),
                'operator_reminder_internal_only' => $reminderAt !== null,
                'follow_up_scheduling_allowed' => false,
                'automatic_outbound_allowed' => false,
                'contains_private_seller_floor' => false,
            ],
        );

        $this->appendOperatorMemory($seller, $task, $result, $amount);

        $this->callNotes[$key] = '';
        $this->callResults[$key] = '';
        $this->offerAmounts[$key] = '';
        $this->reminderDates[$key] = '';

        Notification::make()->title('Görüşme ve sonraki aksiyon CRM’e kaydedildi')->success()->send();
    }

    private function taskForPair(RealEstateProfile $seller, RealEstateProfile $investor, array $candidate, string $property, Collection $history): ?array
    {
        $pair = $seller->id.':'.$investor->id;
        $investorCall = $history->get($pair.':investor');
        $sellerOffer = $history->get($pair.':seller_offer');
        $investorCounter = $history->get($pair.':investor_counter');
        $sellerFinal = $history->get($pair.':seller_final');
        $investorOutcome = $this->activityOutcome($investorCall);

        if ($investorOutcome === 'Uygun değil') {
            return null;
        }

        if ($investorOutcome !== 'Teklif verdi') {
            return $this->investorCallTask($seller, $investor, $candidate, $property, $investorOutcome);
        }

        $offerAmount = $this->activityAmount($investorCall);
        $sellerOutcome = $this->activityOutcome($sellerOffer);

        if (! $sellerOffer || $sellerOffer->id < $investorCall->id || in_array($sellerOutcome, ['Ulaşılmadı', 'Tekrar ara'], true)) {
            return $this->sellerOfferTask($seller, $investor, $property, $offerAmount, (int) ($candidate['match_score'] ?? 0), $investorCall->id);
        }

        if (in_array($sellerOutcome, ['Kabul etti', 'Reddetti'], true) || $sellerOutcome !== 'Karşı teklif') {
            return null;
        }

        $counterAmount = $this->activityAmount($sellerOffer);
        $counterOutcome = $this->activityOutcome($investorCounter);

        if (! $investorCounter || $investorCounter->id < $sellerOffer->id || in_array($counterOutcome, ['Ulaşılmadı', 'Tekrar ara'], true)) {
            return $this->investorCounterTask($seller, $investor, $property, $counterAmount, (int) ($candidate['match_score'] ?? 0), $sellerOffer->id);
        }

        if ($counterOutcome === 'Reddetti' || ! in_array($counterOutcome, ['Kabul etti', 'Yeni teklif'], true)) {
            return null;
        }

        $finalAmount = $counterOutcome === 'Yeni teklif' ? $this->activityAmount($investorCounter) : $counterAmount;
        $finalOutcome = $this->activityOutcome($sellerFinal);

        if (! $sellerFinal || $sellerFinal->id < $investorCounter->id || in_array($finalOutcome, ['Ulaşılmadı', 'Tekrar ara'], true)) {
            return $this->sellerFinalTask(
                $seller,
                $investor,
                $property,
                $finalAmount,
                (int) ($candidate['match_score'] ?? 0),
                $investorCounter->id,
                $counterOutcome === 'Kabul etti'
            );
        }

        return null;
    }

    private function investorCallTask(RealEstateProfile $seller, RealEstateProfile $investor, array $candidate, string $property, ?string $priorOutcome): array
    {
        $conversation = $investor->conversation;
        $name = $conversation?->customer_name ?: 'Yatırımcı';
        $score = (int) ($candidate['match_score'] ?? 0);
        $data = is_array($seller->data) ? $seller->data : [];
        $area = $this->firstValue($data, ['area_sqm', 'square_meters', 'property.area_sqm', 'size_sqm']);
        $interested = $priorOutcome === 'İlgileniyor';

        return [
            'key' => 'investor-'.$seller->id.'-'.$investor->id,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'contact_profile_id' => $investor->id,
            'target_name' => $name,
            'phone' => $conversation?->whatsapp_number,
            'property' => $property,
            'kind' => 'investor',
            'priority' => $score,
            'match_score' => $score,
            'offer_amount' => null,
            'reason' => $interested
                ? 'Bu yatırımcı daha önce portföye ilgi gösterdi; şimdi gerçek teklif seviyesini netleştirmek gerekiyor.'
                : 'Bölge, bütçe ve taşınmaz kriterleriyle eşleşiyor. Eşleşme puanı: '.$score.'/100.',
            'action' => $interested
                ? 'Yatırımcıyı tekrar ara ve net nakit teklif tutarını öğren.'
                : 'Yatırımcıyı ara, ilgisini ve verebileceği gerçek nakit teklif aralığını öğren.',
            'script' => $interested
                ? $name.' merhaba, daha önce ilgi gösterdiğiniz '.$property.' dosyası için net nakit teklif seviyenizi öğrenmek istiyoruz.'
                : $name.' merhaba, yatırım kriterlerinize uygun olabilecek '.$property.' dosyamız var'
                    .($area ? ', yaklaşık '.$area.' m²' : '')
                    .'. Uygun görürseniz detaylarını paylaşarak nakit teklif aralığınızı öğrenmek isteriz.',
        ];
    }

    private function sellerOfferTask(RealEstateProfile $seller, RealEstateProfile $investor, string $property, ?int $amount, int $matchScore, int $sourceActivityId): array
    {
        $conversation = $seller->conversation;
        $investorName = $investor->conversation?->customer_name ?: 'yatırımcı';
        $amountLabel = $amount ? $this->moneyLabel($amount) : 'netleştirilmiş teklif';

        return [
            'key' => 'seller-offer-'.$seller->id.'-'.$investor->id.'-'.$sourceActivityId,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'contact_profile_id' => $seller->id,
            'target_name' => $conversation?->customer_name ?: 'Satıcı',
            'phone' => $conversation?->whatsapp_number,
            'property' => $property,
            'kind' => 'seller_offer',
            'priority' => 200 + $matchScore,
            'match_score' => $matchScore,
            'offer_amount' => $amount,
            'reason' => $investorName.' bu portföy için '.$amountLabel.' seviyesinde gerçek teklif bildirdi.',
            'action' => 'Satıcıyı ara; teklifi ilet, kabul/ret veya karşı teklif sonucunu kaydet.',
            'script' => 'Merhaba, '.$property.' için yatırımcı tarafında '.$amountLabel.' seviyesinde bir teklif oluştu. Bu rakamı değerlendirir misiniz?',
        ];
    }

    private function investorCounterTask(RealEstateProfile $seller, RealEstateProfile $investor, string $property, ?int $amount, int $matchScore, int $sourceActivityId): array
    {
        $conversation = $investor->conversation;
        $amountLabel = $amount ? $this->moneyLabel($amount) : 'karşı teklif';

        return [
            'key' => 'investor-counter-'.$seller->id.'-'.$investor->id.'-'.$sourceActivityId,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'contact_profile_id' => $investor->id,
            'target_name' => $conversation?->customer_name ?: 'Yatırımcı',
            'phone' => $conversation?->whatsapp_number,
            'property' => $property,
            'kind' => 'investor_counter',
            'priority' => 300 + $matchScore,
            'match_score' => $matchScore,
            'offer_amount' => $amount,
            'reason' => 'Satıcı ilk teklife karşılık '.$amountLabel.' seviyesini bildirdi.',
            'action' => 'Yatırımcıyı ara; karşı teklifi ilet ve kabul/ret veya yeni teklif sonucunu kaydet.',
            'script' => 'Merhaba, '.$property.' için satıcı '.$amountLabel.' seviyesinde karşı teklif verdi. Bu rakamı değerlendirebilir misiniz?',
        ];
    }

    private function sellerFinalTask(RealEstateProfile $seller, RealEstateProfile $investor, string $property, ?int $amount, int $matchScore, int $sourceActivityId, bool $acceptedCounter): array
    {
        $conversation = $seller->conversation;
        $amountLabel = $amount ? $this->moneyLabel($amount) : 'son teklif';

        return [
            'key' => 'seller-final-'.$seller->id.'-'.$investor->id.'-'.$sourceActivityId,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'contact_profile_id' => $seller->id,
            'target_name' => $conversation?->customer_name ?: 'Satıcı',
            'phone' => $conversation?->whatsapp_number,
            'property' => $property,
            'kind' => 'seller_final',
            'priority' => 400 + $matchScore,
            'match_score' => $matchScore,
            'offer_amount' => $amount,
            'reason' => $acceptedCounter ? 'Yatırımcı satıcının karşı teklifini kabul etti.' : 'Yatırımcı '.$amountLabel.' seviyesinde yeni teklif verdi.',
            'action' => 'Satıcıyı ara ve son teyidi al. Onay olmadan işlemi kesinleşmiş sayma.',
            'script' => $acceptedCounter
                ? 'Merhaba, '.$property.' için ilettiğiniz karşı teklif yatırımcı tarafından kabul edildi. İşleme devam etmek istediğinizi son kez teyit edebilir miyiz?'
                : 'Merhaba, '.$property.' için yatırımcı '.$amountLabel.' seviyesinde son teklif bildirdi. Değerlendirir misiniz?',
        ];
    }

    private function sellerCompletionTask(RealEstateProfile $seller, string $property, array $handoff): array
    {
        $conversation = $seller->conversation;

        return [
            'key' => 'seller-'.$seller->id,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => null,
            'contact_profile_id' => $seller->id,
            'target_name' => $conversation?->customer_name ?: 'Satıcı',
            'phone' => $conversation?->whatsapp_number,
            'property' => $property,
            'kind' => 'seller',
            'priority' => 10,
            'match_score' => null,
            'offer_amount' => null,
            'reason' => 'Dosya henüz yatırımcı aramasına hazır değil.',
            'action' => (string) ($handoff['recommended_operator_action'] ?? 'Satıcı dosyasındaki eksik bilgiyi tamamla.'),
            'script' => 'Merhaba, taşınmaz dosyanızı yatırımcılarımıza doğru şekilde sunabilmemiz için eksik olan bilgiyi tamamlamak istiyoruz.',
        ];
    }

    private function latestOperatorHistory(): Collection
    {
        return CrmActivity::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('type', 'real_estate_operator_call')
            ->whereHas('conversationControl', fn ($query) => $query
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID))
            ->latest('id')
            ->limit(1000)
            ->get()
            ->filter(fn (CrmActivity $activity): bool => is_array($activity->meta)
                && is_numeric($activity->meta['seller_profile_id'] ?? null)
                && is_numeric($activity->meta['investor_profile_id'] ?? null)
                && is_string($activity->meta['task_kind'] ?? null))
            ->unique(fn (CrmActivity $activity): string => (int) $activity->meta['seller_profile_id']
                .':'.(int) $activity->meta['investor_profile_id']
                .':'.$activity->meta['task_kind'])
            ->keyBy(fn (CrmActivity $activity): string => (int) $activity->meta['seller_profile_id']
                .':'.(int) $activity->meta['investor_profile_id']
                .':'.$activity->meta['task_kind']);
    }

    private function appendOperatorMemory(RealEstateProfile $seller, array $task, string $result, ?int $amount): void
    {
        $data = is_array($seller->data) ? $seller->data : [];
        $history = is_array($data['operator_negotiation_history'] ?? null) ? $data['operator_negotiation_history'] : [];
        $history[] = [
            'investor_profile_id' => is_numeric($task['investor_profile_id'] ?? null) ? (int) $task['investor_profile_id'] : null,
            'contact_profile_id' => (int) $task['contact_profile_id'],
            'task_kind' => (string) $task['kind'],
            'outcome' => $result,
            'offer_amount' => $amount,
            'match_score' => is_numeric($task['match_score'] ?? null) ? (int) $task['match_score'] : null,
            'recorded_at' => now()->toIso8601String(),
            'source' => 'operator_crm',
            'follow_up_scheduling_allowed' => false,
            'automatic_outbound_allowed' => false,
        ];
        $data['operator_negotiation_history'] = array_slice($history, -50);
        $seller->forceFill(['data' => $data])->saveQuietly();
    }

    private function propertySummary(array $data): string
    {
        $location = $this->firstValue($data, ['location', 'property.location', 'property_location', 'district', 'city']);
        $type = $this->firstValue($data, ['property_type', 'property.type', 'asset_type']) ?: 'taşınmaz';
        return trim(($location ? $location.' ' : '').$type);
    }

    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        return null;
    }

    private function activityOutcome(?CrmActivity $activity): ?string
    {
        $outcome = $activity?->meta['outcome'] ?? null;
        return is_string($outcome) && trim($outcome) !== '' ? trim($outcome) : null;
    }

    private function activityAmount(?CrmActivity $activity): ?int
    {
        $amount = $activity?->meta['offer_amount'] ?? null;
        return is_numeric($amount) && (int) $amount > 0 ? (int) $amount : null;
    }

    private function attachReminder(array $task, Collection $history): array
    {
        $task['reminder_at'] = null;
        $task['reminder_future'] = false;

        if (! is_numeric($task['investor_profile_id'] ?? null)) {
            return $task;
        }

        $key = (int) $task['seller_profile_id']
            .':'.(int) $task['investor_profile_id']
            .':'.(string) $task['kind'];
        $activity = $history->get($key);
        $raw = $activity?->meta['operator_reminder_at'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return $task;
        }

        try {
            $due = \Carbon\Carbon::parse($raw);
            $task['reminder_at'] = $due;
            $task['reminder_future'] = $due->isFuture();
        } catch (\Throwable) {
            return $task;
        }

        return $task;
    }

    private function parseReminder(mixed $value): ?\Carbon\Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($value);

            return $date->isFuture() ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMoney(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        return is_string($digits) && $digits !== '' && (int) $digits > 0 ? (int) $digits : null;
    }

    private function moneyLabel(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' TL';
    }

    private function activityTitle(string $kind): string
    {
        return match ($kind) {
            'investor' => 'Yatırımcı araması kaydedildi',
            'seller_offer' => 'Yatırımcı teklifi satıcı görüşmesi kaydedildi',
            'investor_counter' => 'Satıcı karşı teklifi yatırımcı görüşmesi kaydedildi',
            'seller_final' => 'Son teklif satıcı görüşmesi kaydedildi',
            'seller' => 'Satıcı dosya tamamlama görüşmesi kaydedildi',
            default => 'Emlak CRM operatör notu kaydedildi',
        };
    }

    private function taskRank(array $task): int
    {
        if ((bool) ($task['reminder_future'] ?? false)) {
            return 0;
        }

        return match ($task['kind'] ?? '') {
            'seller_final' => 5000 + (int) ($task['match_score'] ?? 0),
            'investor_counter' => 4000 + (int) ($task['match_score'] ?? 0),
            'seller_offer' => 3000 + (int) ($task['match_score'] ?? 0),
            'investor' => 2000 + (int) ($task['match_score'] ?? 0),
            'seller' => 1000,
            default => 100,
        };
    }

    public function getHeading(): string
    {
        return '';
    }
}
