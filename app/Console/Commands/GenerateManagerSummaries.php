<?php

namespace App\Console\Commands;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\User;
use App\Services\CrmManagerSummaryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('wai:generate-manager-summaries')]
#[Description('WAI CRM günlük yönetici özetlerini oluşturur ve panel bildirimi gönderir.')]
class GenerateManagerSummaries extends Command
{
    public function handle(
        CrmManagerSummaryService $summaryService
    ): int {
        /*
        |--------------------------------------------------------------------------
        | ÖZET OLUŞTURULACAK KULLANICILAR
        |--------------------------------------------------------------------------
        |
        | 1) CRM müşterisi bulunan kullanıcılar
        | 2) AiBot sahibi kullanıcılar
        |
        */

        $conversationUserIds =
            ConversationControl::query()
                ->whereNotNull(
                    'user_id'
                )
                ->distinct()
                ->pluck(
                    'user_id'
                );

        $botUserIds =
            AiBot::query()
                ->whereNotNull(
                    'user_id'
                )
                ->distinct()
                ->pluck(
                    'user_id'
                );

        $userIds =
            $conversationUserIds
                ->merge(
                    $botUserIds
                )
                ->filter()
                ->unique()
                ->values();

        /*
        |--------------------------------------------------------------------------
        | KULLANICI YOK
        |--------------------------------------------------------------------------
        */

        if ($userIds->isEmpty()) {
            $this->info(
                'CRM veya bot kullanıcısı bulunamadı.'
            );

            return self::SUCCESS;
        }

        $users =
            User::query()
                ->whereIn(
                    'id',
                    $userIds
                )
                ->get();

        if ($users->isEmpty()) {
            $this->info(
                'Özet oluşturulacak kullanıcı bulunamadı.'
            );

            return self::SUCCESS;
        }

        $success = 0;

        $failed = 0;

        $notifications = 0;

        foreach ($users as $user) {
            try {
                /*
                |--------------------------------------------------------------------------
                | YÖNETİCİ ÖZETİNİ OLUŞTUR
                |--------------------------------------------------------------------------
                */

                $summary =
                    $summaryService
                        ->generate(
                            $user
                        );

                $success++;

                /*
                |--------------------------------------------------------------------------
                | PANEL BİLDİRİMİ
                |--------------------------------------------------------------------------
                |
                | Aynı gün aynı kullanıcıya birden fazla otomatik yönetici
                | bildirimi göndermiyoruz.
                |
                */

                $cacheKey =
                    'wai:manager-summary-notification:'
                    .$user->id
                    .':'
                    .today()->toDateString();

                if (
                    ! Cache::has(
                        $cacheKey
                    )
                ) {
                    $metrics =
                        is_array(
                            $summary->metrics
                        )
                            ? $summary->metrics
                            : [];

                    $todayLeads =
                        (int) (
                            $metrics['today_leads']
                            ?? 0
                        );

                    $hotLeads =
                        (int) (
                            $metrics['hot_leads']
                            ?? 0
                        );

                    $priorityLeads =
                        (int) (
                            $metrics['priority_leads']
                            ?? 0
                        );

                    $overdueFollowUps =
                        (int) (
                            $metrics['overdue_follow_ups']
                            ?? 0
                        );

                    $revenueToday =
                        (float) (
                            $metrics['revenue_today']
                            ?? 0
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | BİLDİRİM TİPİ
                    |--------------------------------------------------------------------------
                    */

                    $notification =
                        Notification::make()
                            ->title(
                                'WAI günlük yönetici özetiniz hazır'
                            )
                            ->body(
                                $todayLeads
                                .' yeni lead · '
                                .$hotLeads
                                .' sıcak lead · '
                                .$priorityLeads
                                .' öncelikli lead · '
                                .number_format(
                                    $revenueToday,
                                    2,
                                    ',',
                                    '.'
                                )
                                .' TL ciro'
                            )
                            ->actions([
                                Action::make(
                                    'reports'
                                )
                                    ->label(
                                        'Raporları Aç'
                                    )
                                    ->url(
                                        url(
                                            '/admin/raporlar'
                                        )
                                    ),
                            ]);

                    /*
                    |--------------------------------------------------------------------------
                    | ACİL DURUM VARSA WARNING
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $overdueFollowUps > 0
                        || $priorityLeads > 0
                    ) {
                        $notification
                            ->warning();
                    } else {
                        $notification
                            ->success();
                    }

                    $notification
                        ->sendToDatabase(
                            $user
                        );

                    Cache::put(
                        $cacheKey,
                        true,
                        now()->endOfDay()
                    );

                    $notifications++;
                }

            } catch (Throwable $exception) {
                $failed++;

                Log::error(
                    'WAI MANAGER SUMMARY FAILED',
                    [
                        'user_id' =>
                            $user->id,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TERMINAL SONUCU
        |--------------------------------------------------------------------------
        */

        $this->info(
            $success
            .' yönetici özeti oluşturuldu.'
        );

        $this->info(
            $notifications
            .' panel bildirimi gönderildi.'
        );

        if ($failed > 0) {
            $this->warn(
                $failed
                .' özet oluşturulamadı.'
            );
        }

        return self::SUCCESS;
    }
}