<?php

namespace App\Services;

use App\Models\AiUsageRecord;
use Illuminate\Support\Collection;

class RealEstateAiCostService
{
    private const PRICES = [
        'gpt-5.4-mini' => [
            'input' => 0.75,
            'cached_input' => 0.075,
            'output' => 4.50,
        ],
        'gpt-5.4-nano' => [
            'input' => 0.20,
            'cached_input' => 0.02,
            'output' => 1.25,
        ],
        'gpt-5.4' => [
            'input' => 2.50,
            'cached_input' => 0.25,
            'output' => 15.00,
        ],
        'gpt-5-mini' => [
            'input' => 0.25,
            'cached_input' => 0.025,
            'output' => 2.00,
        ],
        'gpt-4.1-mini' => [
            'input' => 0.40,
            'cached_input' => 0.10,
            'output' => 1.60,
        ],
    ];

    /**
     * Aggregate-only cost telemetry for the isolated production account.
     * No message text, phone number, customer name, URL or document data is
     * read or returned by this service.
     *
     * @return array<string,mixed>
     */
    public function summary(int $hours = 24): array
    {
        $hours = max(1, min(24 * 30, $hours));

        $rows = AiUsageRecord::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('operation, model, count(*) as calls, sum(input_tokens) as input_tokens, sum(cached_input_tokens) as cached_input_tokens, sum(output_tokens) as output_tokens, sum(reasoning_tokens) as reasoning_tokens, sum(total_tokens) as total_tokens')
            ->groupBy('operation', 'model')
            ->orderBy('operation')
            ->orderBy('model')
            ->get();

        $breakdown = $rows->map(function ($row): array {
            $input = (int) $row->input_tokens;
            $cached = (int) $row->cached_input_tokens;
            $output = (int) $row->output_tokens;
            $actualCost = $this->calculate(
                model: (string) $row->model,
                inputTokens: $input,
                cachedInputTokens: $cached,
                outputTokens: $output,
            );
            $miniCost = $this->calculate(
                model: 'gpt-5-mini',
                inputTokens: $input,
                cachedInputTokens: $cached,
                outputTokens: $output,
            );

            return [
                'operation' => (string) $row->operation,
                'model' => (string) $row->model,
                'calls' => (int) $row->calls,
                'input_tokens' => $input,
                'cached_input_tokens' => $cached,
                'output_tokens' => $output,
                'reasoning_tokens' => (int) $row->reasoning_tokens,
                'total_tokens' => (int) $row->total_tokens,
                'estimated_token_cost_usd' => $actualCost,
                'same_usage_on_gpt_5_mini_usd' => $miniCost,
            ];
        })->values();

        $actual = round((float) $breakdown->sum('estimated_token_cost_usd'), 6);
        $mini = round((float) $breakdown->sum('same_usage_on_gpt_5_mini_usd'), 6);
        $savings = $actual > 0
            ? round(max(0, (1 - ($mini / $actual)) * 100), 1)
            : 0.0;

        return [
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'hours' => $hours,
            'calls' => (int) $breakdown->sum('calls'),
            'estimated_token_cost_usd' => $actual,
            'same_usage_on_gpt_5_mini_usd' => $mini,
            'projected_token_savings_percent' => $savings,
            'tool_fees_included' => false,
            'note' => 'Web search and other per-tool fees are not included because historical usage rows do not persist exact tool-call counts.',
            'breakdown' => $breakdown->all(),
        ];
    }

    public function calculate(
        string $model,
        int $inputTokens,
        int $cachedInputTokens,
        int $outputTokens,
    ): float {
        $prices = $this->pricesFor($model);

        if ($prices === null) {
            return 0.0;
        }

        $inputTokens = max(0, $inputTokens);
        $cachedInputTokens = max(0, min($inputTokens, $cachedInputTokens));
        $normalInputTokens = $inputTokens - $cachedInputTokens;
        $outputTokens = max(0, $outputTokens);

        return round(
            ($normalInputTokens / 1_000_000) * $prices['input']
            + ($cachedInputTokens / 1_000_000) * $prices['cached_input']
            + ($outputTokens / 1_000_000) * $prices['output'],
            6,
        );
    }

    private function pricesFor(string $model): ?array
    {
        $model = trim($model);

        foreach (self::PRICES as $prefix => $prices) {
            if (str_starts_with($model, $prefix)) {
                return $prices;
            }
        }

        return null;
    }
}
