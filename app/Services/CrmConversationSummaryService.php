<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class CrmConversationSummaryService
{
    /*
    |--------------------------------------------------------------------------
    | WAI CRM AI ÖZET AYARLARI
    |--------------------------------------------------------------------------
    |
    | Her mesajda OpenAI çağrısı yapmıyoruz.
    |
    | İlk özet:
    | - en az 4 müşteri/AI mesajı olduğunda
    |
    | Sonraki özet:
    | - son özetten sonra en az 6 yeni mesaj geldiyse
    | - veya lead sıcak / öncelikli hale geldiyse
    |
    */

    private const MIN_MESSAGES_FOR_FIRST_SUMMARY = 4;

    private const MIN_NEW_MESSAGES_FOR_REFRESH = 8;

    private const MIN_NEW_MESSAGES_FOR_PRIORITY_REFRESH = 4;

    private const MAX_MESSAGES_TO_ANALYZE = 12;

    /*
    |--------------------------------------------------------------------------
    | GEREKİRSE ÖZETİ GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function updateIfNeeded(
        ConversationControl $conversation,
        bool $force = false
    ): array {
        try {
            $conversation->loadMissing('aiBot');

            if (! $conversation->aiBot) {
                return $this->result(
                    updated: false,
                    reason: 'ai_bot_not_found'
                );
            }

            $messageCount =
                $this->conversationMessageCount(
                    $conversation
                );

            if (
                $messageCount
                < self::MIN_MESSAGES_FOR_FIRST_SUMMARY
            ) {
                return $this->result(
                    updated: false,
                    reason: 'not_enough_messages'
                );
            }

            if (
                ! $force
                && ! $this->shouldRefresh(
                    conversation: $conversation,
                    messageCount: $messageCount,
                )
            ) {
                return $this->result(
                    updated: false,
                    reason: 'refresh_not_needed'
                );
            }

            $messages =
                $this->messagesForSummary(
                    $conversation
                );

            if ($messages === []) {
                return $this->result(
                    updated: false,
                    reason: 'messages_not_found'
                );
            }

            $summaryData =
                $this->generateSummary(
                    conversation: $conversation,
                    messages: $messages,
                );

            if (
                $summaryData['summary'] === null
                && $summaryData['next_best_action'] === null
            ) {
                return $this->result(
                    updated: false,
                    reason: 'empty_ai_result'
                );
            }

            $oldSummary =
                trim(
                    (string) $conversation->ai_summary
                );

            $oldAction =
                trim(
                    (string) $conversation->next_best_action
                );

            /*
            |--------------------------------------------------------------------------
            | MODEL FILLABLE ALANLARINA DOKUNMADAN KAYDET
            |--------------------------------------------------------------------------
            */

            $conversation->forceFill([
                'ai_summary' =>
                    $summaryData['summary'],

                'next_best_action' =>
                    $summaryData['next_best_action'],

                'ai_summary_updated_at' =>
                    now(),

                'ai_summary_message_count' =>
                    $messageCount,
            ])->save();

            $conversation->refresh();

            /*
            |--------------------------------------------------------------------------
            | TIMELINE
            |--------------------------------------------------------------------------
            */

            if (
                $oldSummary
                    !==
                trim(
                    (string) $conversation->ai_summary
                )
                ||
                $oldAction
                    !==
                trim(
                    (string) $conversation->next_best_action
                )
            ) {
                try {
                    app(
                        CrmActivityService::class
                    )->log(
                        conversation: $conversation,
                        type: 'ai_action',
                        title: 'Müşteri özeti WAI tarafından güncellendi',
                        description:
                            'WAI konuşmayı analiz ederek CRM müşteri özetini ve önerilen sonraki aksiyonu güncelledi.',
                        oldValue:
                            $oldSummary !== ''
                                ? $oldSummary
                                : null,
                        newValue:
                            $conversation->ai_summary,
                        performedBy: null,
                        meta: [
                            'source' =>
                                'crm_conversation_summary',

                            'message_count' =>
                                $messageCount,

                            'next_best_action' =>
                                $conversation->next_best_action,

                            'automatic' =>
                                true,
                        ],
                    );
                } catch (Throwable $exception) {
                    Log::warning(
                        'WAI CRM SUMMARY ACTIVITY FAILED',
                        [
                            'conversation_control_id' =>
                                $conversation->id,

                            'message' =>
                                $exception->getMessage(),
                        ]
                    );
                }
            }

            Log::info(
                'WAI CRM SUMMARY UPDATED',
                [
                    'conversation_control_id' =>
                        $conversation->id,

                    'ai_bot_id' =>
                        $conversation->ai_bot_id,

                    'lead_score' =>
                        $conversation->lead_score,

                    'lead_status' =>
                        $conversation->lead_status,

                    'message_count' =>
                        $messageCount,
                ]
            );

            return [
                'updated' => true,
                'reason' => 'updated',
                'summary' =>
                    $conversation->ai_summary,
                'next_best_action' =>
                    $conversation->next_best_action,
            ];
        } catch (Throwable $exception) {
            Log::error(
                'WAI CRM SUMMARY FAILED',
                [
                    'conversation_control_id' =>
                        $conversation->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            report($exception);

            return $this->result(
                updated: false,
                reason: 'exception'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ÖZET YENİLENMELİ Mİ?
    |--------------------------------------------------------------------------
    */

    private function shouldRefresh(
        ConversationControl $conversation,
        int $messageCount
    ): bool {
        $previousCount =
            max(
                0,
                (int) $conversation->ai_summary_message_count
            );

        /*
        |--------------------------------------------------------------------------
        | DAHA ÖNCE ÖZET YOK
        |--------------------------------------------------------------------------
        */

        if (
            trim(
                (string) $conversation->ai_summary
            ) === ''
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | 6 YENİ MESAJ
        |--------------------------------------------------------------------------
        */

        if (
            $messageCount
            >=
            $previousCount
            + self::MIN_NEW_MESSAGES_FOR_REFRESH
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | SICAK / ÖNCELİKLİ LEAD
        |--------------------------------------------------------------------------
        |
        | Lead sıcaklaştığında eski özetin satış personeline gösterilmesini
        | istemiyoruz. Son konuşmayı yeniden analiz ederiz.
        |
        */

        if (
            (int) $conversation->lead_score >= 70
            &&
            $messageCount
            >=
            $previousCount
            + self::MIN_NEW_MESSAGES_FOR_PRIORITY_REFRESH
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | TEKLİF AŞAMASI
        |--------------------------------------------------------------------------
        */

        if (
            $conversation->lead_status === 'proposal'
            &&
            $messageCount
            >=
            $previousCount
            + self::MIN_NEW_MESSAGES_FOR_PRIORITY_REFRESH
        ) {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | TOPLAM MESAJ SAYISI
    |--------------------------------------------------------------------------
    */

    private function conversationMessageCount(
        ConversationControl $conversation
    ): int {
        return ChatMessage::query()
            ->where(
                'user_id',
                $conversation->user_id
            )
            ->where(
                'session_id',
                $conversation->session_id
            )
            ->whereIn(
                'role',
                [
                    'user',
                    'assistant',
                ]
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | SON MESAJLARI HAZIRLA
    |--------------------------------------------------------------------------
    */

    private function messagesForSummary(
        ConversationControl $conversation
    ): array {
        return ChatMessage::query()
            ->where(
                'user_id',
                $conversation->user_id
            )
            ->where(
                'session_id',
                $conversation->session_id
            )
            ->whereIn(
                'role',
                [
                    'user',
                    'assistant',
                ]
            )
            ->latest('id')
            ->limit(
                self::MAX_MESSAGES_TO_ANALYZE
            )
            ->get()
            ->reverse()
            ->values()
            ->map(
                fn (ChatMessage $message): array => [
                    'role' =>
                        $message->role,

                    'content' =>
                        $message->message,
                ]
            )
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | OPENAI CRM ÖZETİ
    |--------------------------------------------------------------------------
    */

    private function generateSummary(
        ConversationControl $conversation,
        array $messages
    ): array {
        $aiBot =
            $conversation->aiBot;

        $model =
            trim(
                (string) (
                    $aiBot?->openai_model
                    ?: 'gpt-5-mini'
                )
            );

        $sector =
            trim(
                (string) (
                    $aiBot?->business_sector
                    ?: 'general'
                )
            );

        $profile =
            trim(
                (string) (
                    $aiBot?->lead_scoring_profile
                    ?: 'general'
                )
            );

        $instructions = <<<PROMPT
Sen WAI CRM satış analiz sistemisin.

Görevin müşteriye cevap vermek değildir.

Sadece verilen WhatsApp konuşmasını analiz ederek satış personeli için kısa CRM özeti oluştur.

Şirket:
{$aiBot?->company_name}

Sektör kodu:
{$sector}

Satış profili:
{$profile}

Mevcut lead puanı:
{$conversation->lead_score}/100

Mevcut satış aşaması:
{$conversation->lead_status}

Mevcut sıcaklık:
{$conversation->lead_temperature}

SADECE aşağıdaki JSON yapısını döndür:

{
  "summary": null,
  "next_best_action": null
}

SUMMARY KURALLARI

- Maksimum 4 kısa cümle.
- Müşterinin ne istediğini açıkça belirt.
- Konuşmada geçen önemli ürün, hizmet, miktar, bütçe, randevu, başvuru veya satın alma niyetini belirt.
- Müşterinin önemli itirazını veya kararsızlığını belirt.
- Uydurma yapma.
- Konuşmada olmayan bilgiyi ekleme.
- Önceki AI mesajındaki doğrulanmamış firma bilgisini müşteri gerçeği olarak yazma.
- Müşterinin kendi söylediği bilgiler kullanılabilir.
- Gereksiz sohbet ayrıntılarını alma.

NEXT_BEST_ACTION KURALLARI

- Satış personelinin şimdi yapması gereken EN DEĞERLİ tek aksiyonu yaz.
- Maksimum 2 kısa cümle.
- Somut ve uygulanabilir olsun.
- Örneğin:
  "Müşteriyi bugün arayıp 5 litre ürün için sipariş ve teslimat detaylarını netleştirin."
  "Findeks başvurusu için eksik şehir bilgisini tamamlayın."
  "Randevu isteyen müşteriye uygun gün ve saat seçeneklerini sunun."
  "Fiyat itirazı olan müşterinin ihtiyacını netleştirip uygun alternatifi görüşün."
- Şirket verilerinde olmayan fiyat, kampanya, indirim veya garanti üretme.
- Kesin satış / kesin onay / kesin sonuç vaat etme.

GİZLİLİK

- T.C. kimlik numarasını özete yazma.
- Kart numarası yazma.
- Şifre, doğrulama kodu veya hassas kimlik bilgisini yazma.
- Anne kızlık soyadı gibi hassas verileri özete yazma.
- Telefon ve e-posta zaten CRM alanlarında tutulabileceği için özete gereksiz yere tekrar yazma.

JSON dışında hiçbir metin yazma.
PROMPT;

        $request = [
            'model' =>
                $model,

            'instructions' =>
                $instructions,

            'input' =>
                $messages,

            'max_output_tokens' =>
                500,
        ];

        if (
            str_starts_with(
                $model,
                'gpt-5'
            )
            ||
            preg_match(
                '/^o\d/i',
                $model
            )
        ) {
            $request['reasoning'] = [
                'effort' =>
                    'low',
            ];
        }

        $response =
            OpenAI::responses()
                ->create(
                    $request
                );

        app(AiUsageService::class)->record(
            response: $response,
            operation: 'crm_summary',
            aiBot: $aiBot,
            conversation: $conversation,
            meta: [
                'message_count' => count($messages),
                'lead_score' => (int) $conversation->lead_score,
                'lead_status' => $conversation->lead_status,
            ],
        );

        $text =
            trim(
                (string) (
                    $response->outputText
                    ?? ''
                )
            );

        if ($text === '') {
            return [
                'summary' => null,
                'next_best_action' => null,
            ];
        }

        $data =
            json_decode(
                $this->cleanJson(
                    $text
                ),
                true
            );

        if (! is_array($data)) {
            return [
                'summary' => null,
                'next_best_action' => null,
            ];
        }

        return [
            'summary' =>
                $this->cleanText(
                    $data['summary']
                    ?? null,
                    1200
                ),

            'next_best_action' =>
                $this->cleanText(
                    $data['next_best_action']
                    ?? null,
                    600
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | JSON TEMİZLE
    |--------------------------------------------------------------------------
    */

    private function cleanJson(
        string $text
    ): string {
        $text =
            trim(
                $text
            );

        if (
            str_starts_with(
                $text,
                '```'
            )
        ) {
            $text =
                preg_replace(
                    '/^```(?:json)?\s*/i',
                    '',
                    $text
                )
                ?? $text;

            $text =
                preg_replace(
                    '/\s*```$/',
                    '',
                    $text
                )
                ?? $text;
        }

        return trim(
            $text
        );
    }

    /*
    |--------------------------------------------------------------------------
    | METİN TEMİZLE
    |--------------------------------------------------------------------------
    */

    private function cleanText(
        mixed $value,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SONUÇ
    |--------------------------------------------------------------------------
    */

    private function result(
        bool $updated,
        string $reason
    ): array {
        return [
            'updated' =>
                $updated,

            'reason' =>
                $reason,

            'summary' =>
                null,

            'next_best_action' =>
                null,
        ];
    }
}