<?php

namespace App\Jobs;

use App\Models\ConversationControl;
use App\Services\CrmConversationSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateCrmConversationSummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $conversationControlId
    ) {
    }

    public function handle(
        CrmConversationSummaryService $summaryService
    ): void {
        try {
            $conversation =
                ConversationControl::query()
                    ->with('aiBot')
                    ->find(
                        $this->conversationControlId
                    );

            if (! $conversation) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CRM SADECE ANA YÖNETİCİ HESABINDA ÇALIŞIR
            |--------------------------------------------------------------------------
            |
            | WAI normal müşteri hesaplarında CRM AI özeti oluşturulmaz.
            | Böylece bu job normal müşteriler için OpenAI / API maliyeti üretmez.
            |
            */

            if ((int) $conversation->user_id !== 1) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | KAPANMIŞ SATIŞLAR
            |--------------------------------------------------------------------------
            |
            | Kazanılmış / kaybedilmiş fırsatlar için otomatik CRM özeti
            | yenilemiyoruz.
            |
            */

            if (
                in_array(
                    $conversation->lead_status,
                    [
                        'won',
                        'lost',
                    ],
                    true
                )
            ) {
                return;
            }

            $summaryService
                ->updateIfNeeded(
                    conversation: $conversation
                );

        } catch (Throwable $exception) {
            Log::error(
                'WAI CRM SUMMARY JOB FAILED',
                [
                    'conversation_control_id' =>
                        $this->conversationControlId,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }
}