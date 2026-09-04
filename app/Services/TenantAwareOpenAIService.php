<?php

namespace App\Services;

use App\Models\AiBot;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class TenantAwareOpenAIService extends RealEstateOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        $isolation = app(RealEstateIsolationService::class);

        // İzole Emlak AI mevcut dedicated istemci akışını aynen korur.
        if ($isolation->dedicatedOpenAiOnlyForBot($aiBot)) {
            return parent::cevapVer($mesajlar, $aiBot);
        }

        $apiKey = trim((string) ($aiBot?->openai_api_key ?? ''));

        // Botun özel anahtarı yoksa WAI ortak API istemcisiyle devam et.
        if ($apiKey === '') {
            return parent::cevapVer($mesajlar, $aiBot);
        }

        // Test Sohbeti ve WhatsApp aynı bot-bazlı istemci seçimini kullansın.
        // Facade kökünü sadece bu çağrı süresince değiştirip finally ile geri alırız.
        $sharedClient = OpenAI::getFacadeRoot();

        try {
            OpenAI::swap(\OpenAI::client($apiKey));

            return parent::cevapVer($mesajlar, $aiBot);
        } catch (Throwable $exception) {
            report($exception);

            return 'Yapay zekâ bağlantısında geçici bir sorun oluştu. Lütfen API anahtarınızı kontrol edip tekrar deneyin.';
        } finally {
            if ($sharedClient !== null) {
                OpenAI::swap($sharedClient);
            } else {
                OpenAI::clearResolvedInstance('openai');
            }
        }
    }
}
