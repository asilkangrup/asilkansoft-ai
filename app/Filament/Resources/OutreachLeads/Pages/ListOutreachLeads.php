<?php

namespace App\Filament\Resources\OutreachLeads\Pages;

use App\Filament\Resources\OutreachLeads\OutreachLeadResource;
use App\Models\OutreachLead;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListOutreachLeads extends ListRecords
{
    protected static string $resource = OutreachLeadResource::class;

    protected string $view = 'filament.resources.outreach-leads.pages.list-outreach-leads';

    private function mobileQuery(): Builder
    {
        $user = Filament::auth()->user();

        return OutreachLead::query()
            ->when(! $user?->is_admin, fn (Builder $query) => $query->where('user_id', $user?->id ?? 0));
    }

    public function getSelectedSector(): ?string
    {
        $sector = trim((string) request()->query('sector', ''));

        return $sector !== '' ? $sector : null;
    }

    public function getSectorSummaries(): Collection
    {
        return $this->mobileQuery()
            ->selectRaw("COALESCE(NULLIF(TRIM(sector), ''), 'Diğer') AS sector_name")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready")
            ->selectRaw("SUM(CASE WHEN whatsapp_status = 'verified' THEN 1 ELSE 0 END) AS verified")
            ->groupByRaw("COALESCE(NULLIF(TRIM(sector), ''), 'Diğer')")
            ->orderByDesc('total')
            ->get();
    }

    public function getMobileLeads(): LengthAwarePaginator
    {
        $sector = $this->getSelectedSector();
        $search = trim((string) request()->query('q', ''));
        $onlyReady = request()->boolean('ready', true);

        return $this->mobileQuery()
            ->when($sector === 'Diğer', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->whereNull('sector')
                ->orWhereRaw("TRIM(sector) = ''")))
            ->when($sector && $sector !== 'Diğer', fn (Builder $query) => $query->where('sector', $sector))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('company_name', 'ilike', '%'.$search.'%')
                ->orWhere('phone_e164', 'like', '%'.$search.'%')))
            ->when($onlyReady, fn (Builder $query) => $query->where('status', 'ready'))
            ->freshFirst()
            ->paginate(30)
            ->withQueryString();
    }

    public function getMobileStats(): array
    {
        $base = $this->mobileQuery();

        return [
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'opened' => (clone $base)->where('status', 'opened')->count(),
            'replied' => (clone $base)->whereIn('status', ['replied', 'ai_active'])->count(),
            'verified' => (clone $base)->where('whatsapp_status', 'verified')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkImport')
                ->label('Toplu Lead Aktar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->schema([
                    Textarea::make('rows')
                        ->label('Lead Listesi')
                        ->helperText('Her satır: İşletme Adı ; Telefon ; Kaynak ; Kaynak Linki ; Tarih (YYYY-MM-DD) ; WhatsApp (verified/unknown/unavailable)')
                        ->placeholder("ABC Mobilya ; 05321234567 ; firma sitesi ; https://ornek.com ; 2026-08-20 ; verified")
                        ->rows(14)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = Filament::auth()->user();
                    $created = 0;
                    $updated = 0;
                    $skipped = 0;

                    foreach (preg_split('/\r\n|\r|\n/', (string) ($data['rows'] ?? '')) ?: [] as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        $parts = array_map('trim', str_getcsv($line, ';'));
                        if (count($parts) < 2) {
                            $skipped++;
                            continue;
                        }

                        [$company, $rawPhone] = [$parts[0] ?? '', $parts[1] ?? ''];
                        $digits = preg_replace('/\D+/', '', $rawPhone) ?: '';

                        if (str_starts_with($digits, '0')) {
                            $digits = '90'.substr($digits, 1);
                        } elseif (str_starts_with($digits, '5')) {
                            $digits = '90'.$digits;
                        }

                        if (! preg_match('/^905\d{9}$/', $digits) || $company === '') {
                            $skipped++;
                            continue;
                        }

                        $source = $parts[2] ?? 'bulk';
                        $sourceUrl = filter_var($parts[3] ?? null, FILTER_VALIDATE_URL) ?: null;
                        $sourceDate = null;
                        if (! empty($parts[4])) {
                            try {
                                $sourceDate = Carbon::parse($parts[4]);
                            } catch (\Throwable) {
                                $sourceDate = null;
                            }
                        }

                        $whatsappStatus = strtolower($parts[5] ?? 'unknown');
                        if (! in_array($whatsappStatus, ['verified', 'unknown', 'unavailable'], true)) {
                            $whatsappStatus = 'unknown';
                        }

                        $priority = 0;
                        if ($sourceDate?->gte(now()->subYear())) {
                            $priority += 100;
                        } elseif ($sourceDate) {
                            $priority += 20;
                        }
                        if ($whatsappStatus === 'verified') {
                            $priority += 50;
                        }

                        $lead = OutreachLead::query()->firstOrNew([
                            'user_id' => $user->id,
                            'phone_e164' => '+'.$digits,
                        ]);
                        $wasNew = ! $lead->exists;

                        $lead->fill([
                            'company_name' => $company,
                            'source' => $source ?: 'bulk',
                            'source_url' => $sourceUrl,
                            'source_published_at' => $sourceDate,
                            'source_checked_at' => now(),
                            'whatsapp_status' => $whatsappStatus,
                            'whatsapp_verified_at' => $whatsappStatus === 'verified' ? now() : null,
                            'priority_score' => $priority,
                            'status' => $lead->status ?: 'ready',
                            'first_message_text' => $lead->first_message_text
                                ?: 'Merhaba kolay gelsin, '.$company.' doğru mudur?',
                        ])->save();

                        $wasNew ? $created++ : $updated++;
                    }

                    Notification::make()
                        ->title('Toplu aktarım tamamlandı')
                        ->body("Yeni: {$created} · Güncellendi: {$updated} · Atlandı: {$skipped}")
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Lead Ekle'),
        ];
    }
}
