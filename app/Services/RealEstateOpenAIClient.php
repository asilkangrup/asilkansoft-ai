<?php

namespace App\Services;

use App\Models\AiBot;
use RuntimeException;

class RealEstateOpenAIClient
{
    public function createResponse(AiBot $aiBot, array $request): mixed
    {
        return $this->client($aiBot)
            ->responses()
            ->create($request);
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
