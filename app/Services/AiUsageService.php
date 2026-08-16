<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\AiUsageRecord;
use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageService
{
    /*
    |--------------------------------------------------------------------------
    | MODEL FİYATLARI
    |--------------------------------------------------------------------------
    |
    | USD / 1.000.000 token.
    |
    | Fiyat değiştiğinde yalnızca burası güncellenir.
    |
    */

    private const MODEL_PRICES = [
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

    /*
    |--------------------------------------------------------------------------
    | KULLANIM KAYDET
    |--------------------------------------------------------------------------
    */

    public function record(
        mixed $response,
        string $operation,
        ?AiBot $aiBot = null,
        ?ConversationControl $conversation = null,
        array $meta = []
    ): ?AiUsageRecord {
        try {
            $usage = $this->usageArray(
                $response
            );

            $inputTokens =
                (int) (
                    $usage['input_tokens']
                    ?? 0
                );

            $cachedInputTokens =
                (int) (
                    data_get(
                        $usage,
                        'input_tokens_details.cached_tokens',
                        0
                    )
                );

            $outputTokens =
                (int) (
                    $usage['output_tokens']
                    ?? 0
                );

            $reasoningTokens =
                (int) (
                    data_get(
                        $usage,
                        'output_tokens_details.reasoning_tokens',
                        0
                    )
                );

            $totalTokens =
                (int) (
                    $usage['total_tokens']
                    ?? (
                        $inputTokens
                        + $outputTokens
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | MODEL
            |--------------------------------------------------------------------------
            */

            $model =
                trim(
                    (string) (
                        data_get(
                            $this->responseArray($response),
                            'model'
                        )
                        ?: $aiBot?->openai_model
                        ?: 'unknown'
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | MALİYET
            |--------------------------------------------------------------------------
            */

            $estimatedCostUsd =
                $this->calculateCost(
                    model: $model,
                    inputTokens: $inputTokens,
                    cachedInputTokens: $cachedInputTokens,
                    outputTokens: $outputTokens,
                );

            /*
            |--------------------------------------------------------------------------
            | REQUEST ID
            |--------------------------------------------------------------------------
            */

            $requestId =
                data_get(
                    $this->responseArray($response),
                    'id'
                );

            /*
            |--------------------------------------------------------------------------
            | KAYIT
            |--------------------------------------------------------------------------
            */

            return AiUsageRecord::create([
                'user_id' =>
                    $aiBot?->user_id
                    ?? $conversation?->user_id,

                'ai_bot_id' =>
                    $aiBot?->id
                    ?? $conversation?->ai_bot_id,

                'conversation_control_id' =>
                    $conversation?->id,

                'provider' =>
                    'openai',

                'model' =>
                    $model,

                'operation' =>
                    $operation,

                'input_tokens' =>
                    $inputTokens,

                'cached_input_tokens' =>
                    $cachedInputTokens,

                'output_tokens' =>
                    $outputTokens,

                'reasoning_tokens' =>
                    $reasoningTokens,

                'total_tokens' =>
                    $totalTokens,

                'estimated_cost_usd' =>
                    $estimatedCostUsd,

                'request_id' =>
                    is_string($requestId)
                        ? $requestId
                        : null,

                'meta' =>
                    $meta !== []
                        ? $meta
                        : null,
            ]);

        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | KULLANIM KAYDI ANA AI AKIŞINI ASLA BOZMASIN
            |--------------------------------------------------------------------------
            */

            Log::warning(
                'WAI AI USAGE RECORD FAILED',
                [
                    'operation' =>
                        $operation,

                    'ai_bot_id' =>
                        $aiBot?->id,

                    'conversation_control_id' =>
                        $conversation?->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MALİYET HESAPLA
    |--------------------------------------------------------------------------
    */

    public function calculateCost(
        string $model,
        int $inputTokens,
        int $cachedInputTokens,
        int $outputTokens
    ): float {
        $prices =
            $this->pricesForModel(
                $model
            );

        if ($prices === null) {
            return 0.0;
        }

        /*
        |--------------------------------------------------------------------------
        | INPUT TOKEN AYRIMI
        |--------------------------------------------------------------------------
        |
        | input_tokens toplam input miktarıdır.
        | cached_input_tokens bunun içindeki cache'lenmiş bölümdür.
        |
        */

        $cachedInputTokens =
            max(
                0,
                min(
                    $inputTokens,
                    $cachedInputTokens
                )
            );

        $normalInputTokens =
            max(
                0,
                $inputTokens
                - $cachedInputTokens
            );

        $inputCost =
            (
                $normalInputTokens
                / 1_000_000
            )
            * $prices['input'];

        $cachedInputCost =
            (
                $cachedInputTokens
                / 1_000_000
            )
            * $prices['cached_input'];

        $outputCost =
            (
                max(
                    0,
                    $outputTokens
                )
                / 1_000_000
            )
            * $prices['output'];

        return round(
            $inputCost
            + $cachedInputCost
            + $outputCost,
            6
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL FİYATI BUL
    |--------------------------------------------------------------------------
    */

    private function pricesForModel(
        string $model
    ): ?array {
        foreach (
            self::MODEL_PRICES
            as $modelPrefix => $prices
        ) {
            if (
                str_starts_with(
                    $model,
                    $modelPrefix
                )
            ) {
                return $prices;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE -> ARRAY
    |--------------------------------------------------------------------------
    */

    private function responseArray(
        mixed $response
    ): array {
        if (is_array($response)) {
            return $response;
        }

        if (
            is_object($response)
            && method_exists(
                $response,
                'toArray'
            )
        ) {
            $data =
                $response->toArray();

            return is_array($data)
                ? $data
                : [];
        }

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | USAGE
    |--------------------------------------------------------------------------
    */

    private function usageArray(
        mixed $response
    ): array {
        $responseData =
            $this->responseArray(
                $response
            );

        $usage =
            $responseData['usage']
            ?? [];

        return is_array($usage)
            ? $usage
            : [];
    }
}