<?php

namespace App\Filament\Pages;

use App\Models\AiUsageRecord;
use App\Models\ChatMessage;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AiKullanimMaliyet extends Page
{
    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel =
        'AI Kullanım & Maliyet';

    protected static ?string $title =
        'AI Kullanım & Maliyet';

    protected static ?string $slug =
        'ai-kullanim-maliyet';

    protected static ?int $navigationSort =
        99;

    protected static string|\UnitEnum|null $navigationGroup =
        'Sistem Yönetimi';

    protected string $view =
        'filament.pages.ai-kullanim-maliyet';

    /*
    |--------------------------------------------------------------------------
    | SADECE ADMIN
    |--------------------------------------------------------------------------
    */

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    /*
    |--------------------------------------------------------------------------
    | AYLIK ÖZET
    |--------------------------------------------------------------------------
    */

    public function getMonthlySummaryProperty(): array
    {
        $query =
            AiUsageRecord::query()
                ->where(
                    'created_at',
                    '>=',
                    now()->startOfMonth()
                );

        return [
            'api_calls' =>
                (int) (clone $query)->count(),

            'input_tokens' =>
                (int) (clone $query)->sum(
                    'input_tokens'
                ),

            'cached_tokens' =>
                (int) (clone $query)->sum(
                    'cached_input_tokens'
                ),

            'output_tokens' =>
                (int) (clone $query)->sum(
                    'output_tokens'
                ),

            'total_tokens' =>
                (int) (clone $query)->sum(
                    'total_tokens'
                ),

            'cost_usd' =>
                (float) (clone $query)->sum(
                    'estimated_cost_usd'
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BU AY EN ÇOK HARCAYAN
    |--------------------------------------------------------------------------
    */

    public function getTopCustomerProperty(): ?array
    {
        $row =
            AiUsageRecord::query()
                ->selectRaw(
                    '
                    user_id,
                    SUM(estimated_cost_usd) as cost_usd,
                    SUM(total_tokens) as total_tokens,
                    COUNT(*) as api_calls
                    '
                )
                ->where(
                    'created_at',
                    '>=',
                    now()->startOfMonth()
                )
                ->whereNotNull(
                    'user_id'
                )
                ->groupBy(
                    'user_id'
                )
                ->orderByDesc(
                    'cost_usd'
                )
                ->first();

        if (! $row) {
            return null;
        }

        $user =
            User::find(
                $row->user_id
            );

        return [
            'name' =>
                $user?->name
                ?: 'Bilinmeyen',

            'email' =>
                $user?->email,

            'cost_usd' =>
                (float) $row->cost_usd,

            'total_tokens' =>
                (int) $row->total_tokens,

            'api_calls' =>
                (int) $row->api_calls,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ BAZLI MALİYETLER
    |--------------------------------------------------------------------------
    */

    public function getCustomerRowsProperty(): Collection
    {
        $usageRows =
            AiUsageRecord::query()
                ->selectRaw(
                    '
                    user_id,
                    ai_bot_id,

                    COUNT(*) as api_calls,

                    SUM(input_tokens) as input_tokens,
                    SUM(cached_input_tokens) as cached_tokens,
                    SUM(output_tokens) as output_tokens,
                    SUM(total_tokens) as total_tokens,

                    SUM(
                        CASE
                            WHEN operation = "chat_reply"
                            THEN estimated_cost_usd
                            ELSE 0
                        END
                    ) as chat_cost,

                    SUM(
                        CASE
                            WHEN operation = "finance_extractor"
                            THEN estimated_cost_usd
                            ELSE 0
                        END
                    ) as finance_cost,

                    SUM(
                        CASE
                            WHEN operation = "crm_summary"
                            THEN estimated_cost_usd
                            ELSE 0
                        END
                    ) as crm_cost,

                    SUM(estimated_cost_usd) as total_cost
                    '
                )
                ->where(
                    'created_at',
                    '>=',
                    now()->startOfMonth()
                )
                ->groupBy(
                    'user_id',
                    'ai_bot_id'
                )
                ->orderByDesc(
                    'total_cost'
                )
                ->get();

        return $usageRows
            ->map(
                function ($row): array {
                    $user =
                        User::find(
                            $row->user_id
                        );

                    $bot =
                        \App\Models\AiBot::find(
                            $row->ai_bot_id
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | MÜŞTERİ MESAJ SAYISI
                    |--------------------------------------------------------------------------
                    |
                    | Bu ay kullanıcının gerçek WhatsApp müşteri mesajlarını sayıyoruz.
                    |
                    */

                    $customerMessages =
                        ChatMessage::query()
                            ->where(
                                'user_id',
                                $row->user_id
                            )
                            ->where(
                                'role',
                                'user'
                            )
                            ->where(
                                'created_at',
                                '>=',
                                now()->startOfMonth()
                            )
                            ->count();

                    return [
                        'user_id' =>
                            $row->user_id,

                        'name' =>
                            $user?->name
                            ?: 'Bilinmeyen',

                        'email' =>
                            $user?->email
                            ?: '-',

                        'bot' =>
                            $bot?->company_name
                            ?: $bot?->name
                            ?: '-',

                        'customer_messages' =>
                            (int) $customerMessages,

                        'api_calls' =>
                            (int) $row->api_calls,

                        'input_tokens' =>
                            (int) $row->input_tokens,

                        'cached_tokens' =>
                            (int) $row->cached_tokens,

                        'output_tokens' =>
                            (int) $row->output_tokens,

                        'total_tokens' =>
                            (int) $row->total_tokens,

                        'chat_cost' =>
                            (float) $row->chat_cost,

                        'finance_cost' =>
                            (float) $row->finance_cost,

                        'crm_cost' =>
                            (float) $row->crm_cost,

                        'total_cost' =>
                            (float) $row->total_cost,
                    ];
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT
    |--------------------------------------------------------------------------
    */

    public function formatNumber(
        int|float $value
    ): string {
        if ($value >= 1_000_000) {
            return number_format(
                $value / 1_000_000,
                2,
                ',',
                '.'
            )
            .'M';
        }

        if ($value >= 1_000) {
            return number_format(
                $value / 1_000,
                1,
                ',',
                '.'
            )
            .'K';
        }

        return number_format(
            $value,
            0,
            ',',
            '.'
        );
    }

    public function formatUsd(
        float $value
    ): string {
        return '$'
            .number_format(
                $value,
                4,
                '.',
                ','
            );
    }
}