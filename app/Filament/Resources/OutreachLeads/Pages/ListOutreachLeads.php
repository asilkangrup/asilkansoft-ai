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

class ListOutreachLeads extends ListRecords
{
    protected static string $resource = OutreachLeadResource::class;

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
