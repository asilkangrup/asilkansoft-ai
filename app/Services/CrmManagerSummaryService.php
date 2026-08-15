<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmManagerSummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class CrmManagerSummaryService
{
    /*
    |--------------------------------------------------------------------------
    | BUGÜNÜN METRİKLERİ
    |--------------------------------------------------------------------------
    */

    public function metrics(
        int $userId
    ): array {
        $todayStart =
            now()->startOfDay();

        $todayEnd =
            now()->endOfDay();

        $todayLeads =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->whereBetween(
                    'created_at',
                    [
                        $todayStart,
                        $todayEnd,
                    ]
                )
                ->count();

        $hotLeads =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->whereNotIn(
                    'lead_status',
                    [
                        'won',
                        'lost',
                    ]
                )
                ->where(
                    'lead_temperature',
                    'hot'
                )
                ->count();

        $wonToday =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'lead_status',
                    'won'
                )
                ->whereBetween(
                    'won_at',
                    [
                        $todayStart,
                        $todayEnd,
                    ]
                )
                ->count();

        $revenueToday =
            round(
                (float) ConversationControl::query()
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'lead_status',
                        'won'
                    )
                    ->whereBetween(
                        'won_at',
                        [
                            $todayStart,
                            $todayEnd,
                        ]
                    )
                    ->whereNotNull(
                        'actual_value'
                    )
                    ->sum(
                        'actual_value'
                    ),
                2
            );

        $openPipeline =
            round(
                (float) ConversationControl::query()
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->whereNotIn(
                        'lead_status',
                        [
                            'won',
                            'lost',
                        ]
                    )
                    ->whereNotNull(
                        'estimated_value'
                    )
                    ->sum(
                        'estimated_value'
                    ),
                2
            );

        $overdueFollowUps =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->whereNotIn(
                    'lead_status',
                    [
                        'won',
                        'lost',
                    ]
                )
                ->whereNotNull(
                    'next_follow_up_at'
                )
                ->where(
                    'next_follow_up_at',
                    '<',
                    now()
                )
                ->count();

        $priorityLeads =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->whereNotIn(
                    'lead_status',
                    [
                        'won',
                        'lost',
                    ]
                )
                ->where(
                    'lead_score',
                    '>=',
                    85
                )
                ->count();

        $lostReason =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'lead_status',
                    'lost'
                )
                ->whereDate(
                    'lost_at',
                    today()
                )
                ->selectRaw(
                    "
                    COALESCE(
                        NULLIF(
                            TRIM(lost_reason),
                            ''
                        ),
                        'Belirtilmemiş'
                    ) as reason,
                    COUNT(*) as total
                    "
                )
                ->groupBy(
                    'reason'
                )
                ->orderByDesc(
                    'total'
                )
                ->first();

        return [
            'today_leads' =>
                $todayLeads,

            'hot_leads' =>
                $hotLeads,

            'priority_leads' =>
                $priorityLeads,

            'won_today' =>
                $wonToday,

            'revenue_today' =>
                $revenueToday,

            'open_pipeline' =>
                $openPipeline,

            'overdue_follow_ups' =>
                $overdueFollowUps,

            'top_lost_reason' =>
                $lostReason?->reason,

            'top_lost_reason_count' =>
                (int) (
                    $lostReason?->total
                    ?? 0
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | YÖNETİCİ ÖZETİ OLUŞTUR / GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function generate(
        User $user
    ): CrmManagerSummary {
        $metrics =
            $this->metrics(
                $user->id
            );

        $summary =
            $this->generateText(
                user: $user,
                metrics: $metrics
            );

        /*
        |--------------------------------------------------------------------------
        | BUGÜNÜN MEVCUT ÖZETİNİ BUL
        |--------------------------------------------------------------------------
        |
        | SQLite tarafında date / datetime farkından dolayı updateOrCreate
        | bazı durumlarda mevcut kaydı bulamayabiliyor.
        |
        | Bu yüzden whereDate ile bugünkü kayıt özellikle aranıyor.
        |
        */

        $managerSummary =
            CrmManagerSummary::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereDate(
                    'summary_date',
                    today()
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | VARSA GÜNCELLE
        |--------------------------------------------------------------------------
        */

        if ($managerSummary) {
            $managerSummary->update([
                'summary' =>
                    $summary,

                'metrics' =>
                    $metrics,

                'generated_at' =>
                    now(),
            ]);

            $managerSummary->refresh();

            return $managerSummary;
        }

        /*
        |--------------------------------------------------------------------------
        | YOKSA İLK KEZ OLUŞTUR
        |--------------------------------------------------------------------------
        */

        return CrmManagerSummary::query()
            ->create([
                'user_id' =>
                    $user->id,

                'summary_date' =>
                    today()->toDateString(),

                'summary' =>
                    $summary,

                'metrics' =>
                    $metrics,

                'generated_at' =>
                    now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SON ÖZET
    |--------------------------------------------------------------------------
    */

    public function latest(
        int $userId
    ): ?CrmManagerSummary {
        return CrmManagerSummary::query()
            ->where(
                'user_id',
                $userId
            )
            ->latest(
                'summary_date'
            )
            ->latest(
                'generated_at'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | AI METNİ
    |--------------------------------------------------------------------------
    */

    private function generateText(
        User $user,
        array $metrics
    ): string {
        $fallback =
            $this->fallbackText(
                $metrics
            );

        try {
            $input =
                json_encode(
                    $metrics,
                    JSON_UNESCAPED_UNICODE
                    | JSON_PRETTY_PRINT
                );

            $response =
                OpenAI::responses()
                    ->create([
                        'model' =>
                            'gpt-5-mini',

                        'reasoning' => [
                            'effort' =>
                                'low',
                        ],

                        'instructions' =>
                            <<<'PROMPT'
Sen WAI CRM Yönetici Raporu sistemisin.

Görevin verilen CRM metriklerinden kısa ve profesyonel Türkçe yönetici özeti üretmektir.

Kurallar:
- Maksimum 5 kısa cümle.
- Sadece verilen rakamları kullan.
- Rakam uydurma.
- İlk cümlede günün genel durumunu özetle.
- Sıcak veya 85+ öncelikli lead varsa özellikle belirt.
- Geciken takip varsa yöneticiyi aksiyon almaya yönlendir.
- Gerçekleşen ciro ve açık pipeline değerini TL olarak belirt.
- Kayıp nedeni varsa kısa şekilde belirt.
- Gereksiz motive edici veya süslü dil kullanma.
- Kesin satış garantisi verme.
- JSON yazma.
PROMPT,

                        'input' =>
                            "Yönetici: "
                            .($user->name ?: 'Kullanıcı')
                            ."\n\nCRM METRİKLERİ:\n"
                            .$input,
                    ]);

            $text =
                trim(
                    (string) (
                        $response->outputText
                        ?? ''
                    )
                );

            if ($text === '') {
                return $fallback;
            }

            /*
            |--------------------------------------------------------------------------
            | OPENAI BAZEN JSON DÖNDÜREBİLİR
            |--------------------------------------------------------------------------
            */

            $decoded =
                json_decode(
                    $text,
                    true
                );

            if (
                is_array(
                    $decoded
                )
            ) {
                if (
                    isset(
                        $decoded['summary']
                    )
                    && is_string(
                        $decoded['summary']
                    )
                ) {
                    $text =
                        $decoded['summary'];
                } elseif (
                    isset(
                        $decoded['report']
                    )
                ) {
                    if (
                        is_string(
                            $decoded['report']
                        )
                    ) {
                        $text =
                            $decoded['report'];
                    } elseif (
                        is_array(
                            $decoded['report']
                        )
                    ) {
                        $text =
                            implode(
                                ' ',
                                array_filter(
                                    $decoded['report'],
                                    'is_string'
                                )
                            );
                    }
                }
            }

            $text =
                trim(
                    strip_tags(
                        $text
                    )
                );

            if ($text === '') {
                return $fallback;
            }

            return mb_substr(
                $text,
                0,
                1800
            );

        } catch (Throwable $exception) {
            Log::warning(
                'WAI MANAGER SUMMARY AI FAILED',
                [
                    'user_id' =>
                        $user->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return $fallback;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AI ÇALIŞMAZSA YEDEK ÖZET
    |--------------------------------------------------------------------------
    */

    private function fallbackText(
        array $metrics
    ): string {
        $parts = [];

        $parts[] =
            'Bugün '
            .$metrics['today_leads']
            .' yeni lead geldi.';

        $parts[] =
            $metrics['hot_leads']
            .' sıcak lead ve '
            .$metrics['priority_leads']
            .' öncelikli lead bulunuyor.';

        $parts[] =
            'Bugün '
            .$metrics['won_today']
            .' satış kazanıldı ve '
            .number_format(
                $metrics['revenue_today'],
                2,
                ',',
                '.'
            )
            .' TL gerçek ciro oluştu.';

        $parts[] =
            'Açık pipeline değeri '
            .number_format(
                $metrics['open_pipeline'],
                2,
                ',',
                '.'
            )
            .' TL.';

        if (
            $metrics['overdue_follow_ups']
            > 0
        ) {
            $parts[] =
                $metrics['overdue_follow_ups']
                .' müşterinin takip zamanı geçmiş durumda.';
        }

        if (
            ! empty(
                $metrics['top_lost_reason']
            )
        ) {
            $parts[] =
                'Bugünün en sık kayıp nedeni: '
                .$metrics['top_lost_reason']
                .'.';
        }

        return implode(
            ' ',
            $parts
        );
    }
}