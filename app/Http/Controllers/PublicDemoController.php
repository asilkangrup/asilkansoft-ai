<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class PublicDemoController extends Controller
{
    /**
     * Public WAI demo sohbeti.
     *
     * Bu endpoint:
     * - Veritabanına kayıt atmaz.
     * - AiBot oluşturmaz.
     * - WhatsApp / Evolution API kullanmaz.
     * - Sadece landing page üzerindeki demo için OpenAI cevabı üretir.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => [
                'required',
                'string',
                'min:2',
                'max:80',
            ],

            'company_description' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],

            'role' => [
                'required',
                'string',
                'in:sales,support,assistant,technical',
            ],

            'message' => [
                'required',
                'string',
                'min:1',
                'max:500',
            ],

            'messages' => [
                'nullable',
                'array',
                'max:10',
            ],

            'messages.*.role' => [
                'required_with:messages',
                'string',
                'in:user,assistant',
            ],

            'messages.*.content' => [
                'required_with:messages',
                'string',
                'max:1000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SUNUCU TARAFI DEMO MESAJ LİMİTİ
        |--------------------------------------------------------------------------
        |
        | Frontend'deki 5 mesaj sınırına güvenmiyoruz.
        | Kullanıcı JS'i değiştirirse bile session başına en fazla
        | 5 public demo isteği yapılabilsin.
        |
        */

        $sessionKey = 'wai_public_demo_message_count';

        $usedMessages = (int) $request->session()->get(
            $sessionKey,
            0
        );

        if ($usedMessages >= 5) {
            return response()->json([
                'success' => false,
                'limit_reached' => true,
                'message' => 'Ücretsiz demo mesaj hakkınız tamamlandı.',
            ], 429);
        }

        /*
        |--------------------------------------------------------------------------
        | TEMEL BİLGİLER
        |--------------------------------------------------------------------------
        */

        $companyName = trim(
            $validated['company_name']
        );

        $companyDescription = trim(
            $validated['company_description']
        );

        $role = $validated['role'];

        $userMessage = trim(
            $validated['message']
        );

        $previousMessages = $validated['messages'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | ROL TALİMATLARI
        |--------------------------------------------------------------------------
        */

        $roleInstruction = match ($role) {
            'sales' => <<<TEXT
Sen satış uzmanısın.

Müşterinin ne istediğini doğal şekilde anlamaya çalış.
Ürün veya hizmetle ilgileniyorsa satış görüşmesini ilerlet.
Gereksiz baskıcı satış dili kullanma.
Müşteriye aynı anda çok fazla soru sorma.
Uygun olduğunda bir sonraki mantıklı soruyu sor.
TEXT,

            'support' => <<<TEXT
Sen müşteri temsilcisisin.

Müşterinin sorusunu dikkatle anlamaya çalış.
Elindeki işletme bilgileriyle net ve yardımcı cevap ver.
Bilmediğin bir bilgiyi uydurma.
Gerekirse kısa bir açıklayıcı soru sor.
TEXT,

            'assistant' => <<<TEXT
Sen işletmenin WhatsApp sekreteri ve asistanısın.

Müşteriyi karşıla.
Ne istediğini anlamaya çalış.
Gerekli bilgileri kısa ve doğal sorularla sırayla topla.
Müşteriye aynı anda çok fazla soru sorma.
TEXT,

            'technical' => <<<TEXT
Sen teknik destek uzmanısın.

Müşterinin yaşadığı problemi anlamaya çalış.
Sorunu teşhis etmek için gerekiyorsa kısa ve hedefli sorular sor.
Bilmediğin teknik bilgileri uydurma.
Net ve uygulanabilir şekilde yardımcı ol.
TEXT,

            default => '',
        };

        /*
        |--------------------------------------------------------------------------
        | SYSTEM INSTRUCTIONS
        |--------------------------------------------------------------------------
        */

        $instructions = <<<PROMPT
Sen WAI isimli WhatsApp yapay zeka platformunun canlı demo asistanısın.

Şu anda gerçek bir işletmenin WhatsApp müşterisiyle konuşuyormuş gibi davranacaksın.

İŞLETME ADI:
{$companyName}

İŞLETME HAKKINDA BİLDİĞİN BİLGİLER:
{$companyDescription}

GÖREVİN:
{$roleInstruction}

GENEL KONUŞMA KURALLARI:

- Türkçe konuş.
- WhatsApp'a uygun doğal ve kısa mesajlar yaz.
- Robot gibi konuşma.
- Gereksiz uzun açıklamalar yapma.
- Müşterinin sorduğu soruya doğrudan cevap ver.
- İşletme açıklamasında olmayan fiyat, kampanya, stok, teslimat süresi, adres veya başka bir bilgiyi ASLA uydurma.
- Bilmediğin bir bilgi sorulursa bunu açıkça belirt ve gerekiyorsa müşteriden ek bilgi iste.
- Daha önce konuşmada verilen bilgileri tekrar sorma.
- Kullanıcının son mesajını konuşmanın bağlamına göre yorumla.
- Her mesajda "Nasıl yardımcı olabilirim?" gibi genel cümleleri tekrar etme.
- Müşteri tek kelimelik cevap verse bile önceki konuşmayla bağlantısını kur.
- Müşteri "sineklik" gibi tek bir ürün adı yazarsa bunun önceki mesajın devamı olduğunu anla.
- Satış görevin varsa müşteriyi doğal biçimde bir sonraki satış adımına ilerlet.
- Bir cevap çoğu durumda 1-4 kısa cümleyi geçmesin.
- Emoji kullanabilirsin fakat abartma.
- Bu bir demo olduğunu müşteriye her mesajda söyleme.
- Kendini OpenAI, ChatGPT veya başka bir model olarak tanıtma.
- Sadece işletmenin WhatsApp yapay zeka çalışanı gibi davran.

ÇOK ÖNEMLİ:
Yalnızca sana verilen işletme bilgilerine dayan.
Gerçek olmayan fiyat, stok, kampanya, garanti veya işletme politikası üretme.
PROMPT;

        /*
        |--------------------------------------------------------------------------
        | CONVERSATION INPUT
        |--------------------------------------------------------------------------
        |
        | Önce önceki konuşmaları, ardından yeni müşteri mesajını gönderiyoruz.
        |
        */

        $input = [];

        foreach ($previousMessages as $message) {
            $input[] = [
                'role' => $message['role'],
                'content' => trim(
                    $message['content']
                ),
            ];
        }

        $input[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        /*
        |--------------------------------------------------------------------------
        | OPENAI
        |--------------------------------------------------------------------------
        */

        try {
            $response = OpenAI::responses()->create([
                'model' => 'gpt-5-mini',
                'instructions' => $instructions,
                'input' => $input,
            ]);

            $answer = trim(
                (string) $response->outputText
            );

            if ($answer === '') {
                throw new \RuntimeException(
                    'OpenAI boş demo cevabı döndürdü.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | MESAJ HAKKINI ARTIR
            |--------------------------------------------------------------------------
            |
            | Yalnızca başarılı OpenAI cevabından sonra hakkı düşürüyoruz.
            |
            */

            $usedMessages++;

            $request->session()->put(
                $sessionKey,
                $usedMessages
            );

            $remainingMessages = max(
                0,
                5 - $usedMessages
            );

            return response()->json([
                'success' => true,
                'message' => $answer,
                'remaining_messages' => $remainingMessages,
                'limit_reached' => $remainingMessages <= 0,
            ]);
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | LOG
            |--------------------------------------------------------------------------
            |
            | Kullanıcıya teknik hata ayrıntısı göstermiyoruz.
            | Gerçek hata Laravel loguna yazılır.
            |
            */

            Log::error('WAI public demo OpenAI hatası', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'company_name' => $companyName,
                'role' => $role,
            ]);

            /*
            |--------------------------------------------------------------------------
            | QUOTA / RATE LIMIT
            |--------------------------------------------------------------------------
            */

            $errorMessage = strtolower(
                $exception->getMessage()
            );

            if (
                str_contains($errorMessage, 'rate limit') ||
                str_contains($errorMessage, 'quota') ||
                str_contains($errorMessage, 'credit')
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demo yapay zekâ servisine şu anda ulaşılamıyor. Lütfen kısa bir süre sonra tekrar deneyin.',
                ], 503);
            }

            return response()->json([
                'success' => false,
                'message' => 'WAI demo cevabı oluşturulurken geçici bir sorun oluştu. Lütfen tekrar deneyin.',
            ], 500);
        }
    }
}