<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\OrganizationAccessService;
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

    public static function canAccess(): bool
    {
        return app(OrganizationAccessService::class)->can('tasks');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTasksProperty(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->latest('last_extracted_at')
            ->get();

        $investors = $profiles->where('profile_type', 'investor')->keyBy('id');

        return $profiles->where('profile_type', 'seller')->map(function (RealEstateProfile $seller) use ($investors): array {
            $data = is_array($seller->data) ? $seller->data : [];
            $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
                ? $data['investor_offer_handoff_intelligence']
                : [];
            $candidate = collect($handoff['candidate_refs'] ?? [])->first();
            $investor = is_array($candidate)
                ? $investors->get((int) ($candidate['investor_profile_id'] ?? 0))
                : null;

            $location = $this->firstValue($data, ['location', 'property.location', 'property_location', 'district', 'city']);
            $type = $this->firstValue($data, ['property_type', 'property.type', 'asset_type']) ?: 'taşınmaz';
            $area = $this->firstValue($data, ['area_sqm', 'square_meters', 'property.area_sqm', 'size_sqm']);
            $label = trim(($location ? $location.' ' : '').$type);
            $ready = (bool) ($handoff['ready_for_operator_handoff'] ?? false) && $investor;

            if ($ready) {
                $investorConversation = $investor->conversation;
                $name = $investorConversation?->customer_name ?: 'Yatırımcı';
                $script = $name.' merhaba, yatırım kriterlerinize uygun olabilecek '
                    .$label.' dosyamız var'
                    .($area ? ', yaklaşık '.$area.' m²' : '')
                    .'. Uygun görürseniz detaylarını paylaşarak nakit teklif aralığınızı öğrenmek isteriz.';

                return [
                    'key' => 'investor-'.$seller->id.'-'.$investor->id,
                    'seller_id' => $seller->id,
                    'target_profile_id' => $investor->id,
                    'target_conversation_id' => $investorConversation?->id,
                    'target_name' => $name,
                    'phone' => $investorConversation?->whatsapp_number,
                    'property' => $label,
                    'kind' => 'investor',
                    'priority' => (int) ($candidate['match_score'] ?? 0),
                    'reason' => 'Bölge, bütçe ve taşınmaz kriterleriyle eşleşiyor. Eşleşme puanı: '.((int) ($candidate['match_score'] ?? 0)).'/100.',
                    'action' => 'Yatırımcıyı ara, ilgisini ve verebileceği gerçek nakit teklif aralığını öğren.',
                    'script' => $script,
                ];
            }

            $conversation = $seller->conversation;

            return [
                'key' => 'seller-'.$seller->id,
                'seller_id' => $seller->id,
                'target_profile_id' => $seller->id,
                'target_conversation_id' => $conversation?->id,
                'target_name' => $conversation?->customer_name ?: 'Satıcı',
                'phone' => $conversation?->whatsapp_number,
                'property' => $label,
                'kind' => 'seller',
                'priority' => 0,
                'reason' => 'Dosya henüz yatırımcı aramasına hazır değil.',
                'action' => (string) ($handoff['recommended_operator_action'] ?? 'Satıcı dosyasındaki eksik bilgiyi tamamla.'),
                'script' => 'Merhaba, taşınmaz dosyanızı yatırımcılarımıza doğru şekilde sunabilmemiz için eksik olan bilgiyi tamamlamak istiyoruz.',
            ];
        })->sortByDesc(fn (array $task): int => $task['kind'] === 'investor' ? 1000 + $task['priority'] : $task['priority'])->values();
    }

    public function saveCall(string $key, int $conversationId): void
    {
        abort_unless(app(OrganizationAccessService::class)->canWriteCrm(), 403);

        $conversation = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversationId)
            ->with('conversation')
            ->first()?->conversation;

        if (! $conversation) {
            Notification::make()->title('Kayıt bulunamadı')->danger()->send();
            return;
        }

        $result = trim((string) ($this->callResults[$key] ?? ''));
        $note = trim((string) ($this->callNotes[$key] ?? ''));

        if ($result === '' && $note === '') {
            Notification::make()->title('Sonuç veya not girin')->warning()->send();
            return;
        }

        $entry = now()->format('d.m.Y H:i').' — '.($result ?: 'Not');
        if ($note !== '') {
            $entry .= ': '.$note;
        }

        $conversation->forceFill([
            'notes' => trim(($conversation->notes ? $conversation->notes."\n\n" : '').$entry),
            'last_contact_at' => now(),
        ])->save();

        $this->callNotes[$key] = '';
        $this->callResults[$key] = '';

        Notification::make()->title('Görüşme CRM’e kaydedildi')->success()->send();
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

    public function getHeading(): string
    {
        return '';
    }
}
