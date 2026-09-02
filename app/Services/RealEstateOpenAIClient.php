<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RealEstateOpenAIClient
{
    public function createResponse(AiBot $aiBot, array $request): mixed
    {
        // Bot 35 can receive many simultaneous advertisement leads. The
        // dedicated OpenAI project rate-limits concurrent bursts, so serialize
        // only its model boundary while leaving webhook intake and CRM writes
        // parallel. Waiting jobs remain safely queued instead of failing and
        // leaving a customer's first message unanswered.
        return Cache::lock(
            'real-estate-openai:bot:'.RealEstateIsolationService::BOT_ID,
            300,
        )->block(180, function () use ($aiBot, $request): mixed {
            return $this->client($aiBot)
                ->responses()
                ->create($request);
        });
    }

    public function transcribeAudio(
        AiBot $aiBot,
        string $filePath,
        string $model,
        ?string $language = null,
        ?string $prompt = null,
    ): mixed {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Transkripsiyon ses dosyası okunamıyor.');
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Transkripsiyon ses dosyası açılamadı.');
        }

        $parameters = [
            'model' => trim($model),
            'file' => $handle,
        ];

        if (filled($language)) {
            $parameters['language'] = trim((string) $language);
        }

        if (filled($prompt)) {
            $parameters['prompt'] = trim((string) $prompt);
        }

        try {
            return $this->client($aiBot)
                ->audio()
                ->transcribe($parameters);
        } finally {
            fclose($handle);
        }
    }

    private function client(AiBot $aiBot): \OpenAI\Client
    {
        if (! app(RealEstateIsolationService::class)->supportsBotIdentity($aiBot)) {
            throw new RuntimeException('İzole Emlak AI OpenAI istemcisi kapsam dışı bot için kullanılamaz.');
        }

        $apiKey = trim((string) $aiBot->openai_api_key);

        if ($apiKey === '') {
            throw new RuntimeException('Emlak AI için özel OpenAI API anahtarı tanımlı değil.');
        }

        return \OpenAI::client($apiKey);
    }
}
