<?php

namespace App\Services;

use App\Models\AiBot;
use RuntimeException;

class RealEstateOpenAIClient
{
    public function createResponse(AiBot $aiBot, array $request): mixed
    {
        $apiKey = trim((string) $aiBot->openai_api_key);

        if ($apiKey === '') {
            throw new RuntimeException('Emlak AI için özel OpenAI API anahtarı tanımlı değil.');
        }

        return \OpenAI::client($apiKey)
            ->responses()
            ->create($request);
    }
}
