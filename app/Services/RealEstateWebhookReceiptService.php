<?php

namespace App\Services;

use App\Models\RealEstateWebhookReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class RealEstateWebhookReceiptService
{
    private const PROCESSING_STALE_AFTER_MINUTES = 5;

    /**
     * @return array{receipt:?RealEstateWebhookReceipt, should_process:bool, reason:?string}
     */
    public function begin(
        string $instance,
        string $event,
        string $messageId,
        ?string $phoneNumber = null,
    ): array {
        $messageId = trim($messageId);

        if ($messageId === '') {
            return [
                'receipt' => null,
                'should_process' => true,
                'reason' => null,
            ];
        }

        $receiptKey = hash('sha256', $instance.'|'.$event.'|'.$messageId);

        try {
            return $this->beginLocked(
                receiptKey: $receiptKey,
                instance: $instance,
                event: $event,
                messageId: $messageId,
                phoneNumber: $phoneNumber,
            );
        } catch (QueryException $exception) {
            // A concurrent delivery can win the unique receipt_key insert. In
            // that narrow race, resolve the already-created row instead of
            // surfacing a 500 that would trigger another Evolution retry.
            if (! $this->looksLikeUniqueViolation($exception)) {
                throw $exception;
            }

            return $this->beginLocked(
                receiptKey: $receiptKey,
                instance: $instance,
                event: $event,
                messageId: $messageId,
                phoneNumber: $phoneNumber,
            );
        }
    }

    public function complete(
        ?RealEstateWebhookReceipt $receipt,
        array $result,
    ): void {
        if (! $receipt) {
            return;
        }

        $ignored = (bool) ($result['ignored'] ?? false);
        $status = $ignored ? 'ignored' : 'replied';

        $receipt->forceFill([
            'status' => $status,
            'last_error' => null,
            'processed_at' => now(),
            'replied_at' => $status === 'replied' ? now() : null,
        ])->save();
    }

    public function fail(
        ?RealEstateWebhookReceipt $receipt,
        Throwable $exception,
    ): void {
        if (! $receipt) {
            return;
        }

        $receipt->forceFill([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'processed_at' => now(),
        ])->save();
    }

    private function beginLocked(
        string $receiptKey,
        string $instance,
        string $event,
        string $messageId,
        ?string $phoneNumber,
    ): array {
        return DB::transaction(function () use (
            $receiptKey,
            $instance,
            $event,
            $messageId,
            $phoneNumber,
        ): array {
            $receipt = RealEstateWebhookReceipt::query()
                ->where('receipt_key', $receiptKey)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                $receipt = RealEstateWebhookReceipt::query()->create([
                    'user_id' => RealEstateIsolationService::USER_ID,
                    'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                    'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                    'instance' => $instance,
                    'event' => $event,
                    'receipt_key' => $receiptKey,
                    'whatsapp_message_id' => $messageId,
                    'phone_number' => $phoneNumber,
                    'status' => 'processing',
                    'attempts' => 1,
                    'processing_started_at' => now(),
                ]);

                return [
                    'receipt' => $receipt,
                    'should_process' => true,
                    'reason' => null,
                ];
            }

            if (in_array($receipt->status, ['replied', 'ignored', 'processed'], true)) {
                return [
                    'receipt' => $receipt,
                    'should_process' => false,
                    'reason' => 'duplicate_'.$receipt->status,
                ];
            }

            $stillProcessing = $receipt->status === 'processing'
                && $receipt->processing_started_at
                && $receipt->processing_started_at->gt(
                    now()->subMinutes(self::PROCESSING_STALE_AFTER_MINUTES)
                );

            if ($stillProcessing) {
                return [
                    'receipt' => $receipt,
                    'should_process' => false,
                    'reason' => 'already_processing',
                ];
            }

            $receipt->forceFill([
                'status' => 'processing',
                'attempts' => (int) $receipt->attempts + 1,
                'phone_number' => $phoneNumber ?: $receipt->phone_number,
                'last_error' => null,
                'processing_started_at' => now(),
                'processed_at' => null,
                'replied_at' => null,
            ])->save();

            return [
                'receipt' => $receipt,
                'should_process' => true,
                'reason' => null,
            ];
        }, 3);
    }

    private function looksLikeUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, '23000')
            || str_contains($message, '23505');
    }
}
